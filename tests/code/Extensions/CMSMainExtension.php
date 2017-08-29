<?php

namespace AirNZ\SimpleSubsites\Extensions;

use AirNZ\SimpleSubsites\Model\Subsite;
use SilverStripe\Admin\CMSMenu;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;

/**
 * Decorator designed to add subsites support to LeftAndMain
 */
class CMSMainExtension extends Extension
{
    /**
     * Set the title of the CMS tree
     */
    public function getCMSTreeTitle()
    {
        $subsite = Subsite::currentSubSite();
        return $subsite ? Convert::raw2xml($subsite->Title) : '';
    }

    public function updatePageOptions(FieldList $fields)
    {
        $fields->push(new HiddenField('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
    }

    /**
     * Find all subsites accessible for current user on this controller.
     *
     * @return ArrayList of {@link Subsite} instances.
     */
    public function sectionSites($member = null)
    {
        // Rationalise member arguments
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        if (!$member) {
            return new ArrayList();
        }
        if (!is_object($member)) {
            $member = DataObject::get_by_id(Member::class, $member);
        }

        // Collect permissions - honour the LeftAndMain::required_permission_codes, current model requires
        // us to check if the user satisfies ALL permissions. Code partly copied from LeftAndMain::canView.
        $codes = [];
        $extraCodes = Config::inst()->get(get_class($this->owner), 'required_permission_codes');
        if ($extraCodes !== false) {
            if ($extraCodes) {
                $codes = array_merge($codes, (array)$extraCodes);
            } elseif (get_class($this->owner) == 'SilverStripe\CMS\Controllers\CMSMain') {
                $codes[] = "CMS_ACCESS_CMSMain";
            } elseif (get_class($this->owner) == 'SilverStripe\Admin\LeftAndMain') {
                $codes[] = "CMS_ACCESS_CMSMain";
            } else {
                $codes[] = "CMS_ACCESS_" . get_class($this->owner);
            }
        } else {
            // Check overriden - all subsites accessible.
            return Subsite::all_sites();
        }

        // Find subsites satisfying all permissions for the Member.
        $codesPerSite = [];
        $sitesArray = [];
        foreach ($codes as $code) {
            $sites = Subsite::accessible_sites($code, $member);
            foreach ($sites as $site) {
                // Build the structure for checking how many codes match.
                $codesPerSite[$site->ID][$code] = true;

                // Retain Subsite objects for later.
                $sitesArray[$site->ID] = $site;
            }
        }

        // Find sites that satisfy all codes conjuncitvely.
        $accessibleSites = new ArrayList();
        foreach ($codesPerSite as $siteID => $siteCodes) {
            if (count($siteCodes)==count($codes)) {
                $accessibleSites->push($sitesArray[$siteID]);
            }
        }

        return $accessibleSites;
    }

    public function CanAddSubsites()
    {
        return Permission::check("ADMIN", "any", null, "all");
    }

    /**
     * Helper for testing if the subsite should be adjusted.
     */
    public function shouldChangeSubsite($adminClass, $recordSubsiteID, $currentSubsiteID)
    {
        if ($recordSubsiteID!=$currentSubsiteID) {
            return true;
        }
        return false;
    }

    /**
     * Check if the current controller is accessible for this user on this subsite.
     */
    public function canAccess($member = null)
    {
        if (!$member && $member !== false) {
            $member = Security::getCurrentUser();
        }

        // Admin can access everything, no point in checking.
        if ($member &&
        (
            Permission::checkMember($member, 'ADMIN') || // 'Full administrative rights' in SecurityAdmin
            Permission::checkMember($member, 'CMS_ACCESS_CMSMain') // 'Access to all CMS sections' in SecurityAdmin
        )) {
            return true;
        }

        // Check if we have access to current section on the current subsite.
        $accessibleSites = $this->owner->sectionSites($member);
        if ($accessibleSites->count() && (Subsite::currentSubsiteID() === 0 || $accessibleSites->find('ID', Subsite::currentSubsiteID()))) {
            // Current section can be accessed on the current site, all good.
            return true;
        }

        return false;
    }

    /**
     * Prevent accessing disallowed resources. This happens after onBeforeInit has executed,
     * so all redirections should've already taken place.
     */
    public function alternateAccessCheck($member = null)
    {
        if (!$member && $member !== false) {
            $member = Security::getCurrentUser();
        }
        return $this->owner->canAccess($member);
    }

    /**
     * Redirect the user to something accessible if the current section/subsite is forbidden.
     *
     * This is done via onBeforeInit as it needs to be done before the LeftAndMain::init has a
     * chance to forbids access via alternateAccessCheck.
     *
     * If we need to change the subsite we force the redirection to /admin/ so the frontend is
     * fully re-synchronised with the internal session. This is better than risking some panels
     * showing data from another subsite.
     */
    public function onBeforeInit()
    {
        if (isset($_GET['SubsiteID'])) {
            // LeftAndMainExtension will redirect already if this is present
            return;
        }

        // Automatically redirect the session to appropriate subsite when requesting a record.
        // This is needed to properly initialise the session in situations where someone opens the CMS via a link.
        $record = $this->owner->currentPage();
        if ($record && isset($record->SubsiteID) && is_numeric($record->SubsiteID) && isset($this->owner->urlParams['ID'])) {
            if ($this->shouldChangeSubsite(get_class($this->owner), $record->SubsiteID, Subsite::currentSubsiteID())) {
                // Update current subsite in session
                Subsite::changeSubsite($record->SubsiteID);

                if ($this->owner->canView(Security::getCurrentUser())) {
                    //Redirect to clear the current page
                    return $this->owner->redirect($this->owner->getRequest()->getURL());
                }
                //Redirect to the default CMS section
                return $this->owner->redirect($_SERVER['REQUEST_URI']);
            }
        }

        // SECOND, check if we need to change subsites due to lack of permissions.

        if (!$this->owner->canAccess()) {
            $member = Security::getCurrentUser();

            // Current section is not accessible, try at least to stick to the same subsite.
            if ($member) {
                $menu = CMSMenu::get_menu_items();
                foreach ($menu as $candidate) {
                    if ($candidate->controller && $candidate->controller != get_class($this->owner)) {
                        if (!singleton($candidate->controller)->hasExtension(self::class)) {
                            // Section isn't restricted by subsite, redirect there
                            return $this->owner->redirect(singleton($candidate->controller)->Link());
                        }
                        $accessibleSites = singleton($candidate->controller)->sectionSites($member);
                        if ($accessibleSites->count() && $accessibleSites->find('ID', Subsite::currentSubsiteID())) {
                            // Section is accessible, redirect there.
                            return $this->owner->redirect(singleton($candidate->controller)->Link());
                        }
                    }
                }

                // If no section is available, look for other accessible subsites.
                foreach ($menu as $candidate) {
                    if ($candidate->controller) {
                        $accessibleSites = singleton($candidate->controller)->sectionSites($member);
                        if ($accessibleSites->count()) {
                            Subsite::changeSubsite($accessibleSites->First()->ID);
                            return $this->owner->redirect(singleton($candidate->controller)->Link());
                        }
                    }
                }
            }

            // We have not found any accessible section or subsite. User should be denied access.
            return Security::permissionFailure($this->owner);
        }

        // Current site is accessible. Allow through.
        return;
    }

    public function augmentNewSiteTreeItem(&$item)
    {
        $item->SubsiteID = isset($_POST['SubsiteID']) ? $_POST['SubsiteID'] : Subsite::currentSubsiteID();
    }

    public function onAfterSave($record)
    {
        if ($record->hasMethod('NormalRelated') && ($record->NormalRelated() || $record->ReverseRelated())) {
            $this->owner->response->addHeader('X-Status', rawurlencode(_t('LeftAndMainSubsites.Saved', 'Saved, please update related pages.')));
        }
    }
}
