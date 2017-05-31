<?php

use SilverStripe\View\Requirements;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Security\Member;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Config\Config;
use SilverStripe\View\ArrayData;
use SilverStripe\Security\Permission;
use SilverStripe\Control\Session;
use SilverStripe\Admin\CMSMenu;
use SilverStripe\Security\Security;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;

/**
 * Decorator designed to add subsites support to LeftAndMain
 *
 * @package subsites
 */
class LeftAndMainSubsites extends Extension
{
    public function init()
    {
        Requirements::css('simplesubsites/css/LeftAndMain_Subsites.css');
        Requirements::javascript('simplesubsites/javascript/LeftAndMain_Subsites.js');
    }

    /**
     * Set the title of the CMS tree
     */
    public function getCMSTreeTitle()
    {
        $subsite = Subsite::currentSubSite();
        return $subsite ? Convert::raw2xml($subsite->Title) : '';
    }

    public function updatePageOptions(&$fields)
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
            $member = Member::currentUser();
        }
        if (!$member) {
            return new ArrayList();
        }
        if (!is_object($member)) {
            $member = DataObject::get_by_id(Member::class, $member);
        }

        // Collect permissions - honour the LeftAndMain::required_permission_codes, current model requires
        // us to check if the user satisfies ALL permissions. Code partly copied from LeftAndMain::canView.
        $codes = array();
        $extraCodes = Config::inst()->get(get_class($this->owner), 'required_permission_codes');
        if ($extraCodes !== false) {
            if ($extraCodes) {
                $codes = array_merge($codes, (array)$extraCodes);
            } elseif (get_class($this->owner) == 'SilverStripe\CMS\Controllers\CMSMain') {
                $codes[] = "CMS_ACCESS_CMSMain";
            } else {
                $codes[] = "CMS_ACCESS_{get_class($this->owner)}";
            }
        } else {
            // Check overriden - all subsites accessible.
            return Subsite::all_sites();
        }

        // Find subsites satisfying all permissions for the Member.
        $codesPerSite = array();
        $sitesArray = array();
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

    /*
     * Returns a list of the subsites accessible to the current user.
     * It's enough for any section to be accessible for the section to be included.
     */
    public function Subsites()
    {
        return Subsite::all_accessible_sites();
    }

    /*
     * Generates a list of subsites with the data needed to
     * produce a dropdown site switcher
     * @return ArrayList
     */

    public function ListSubsites()
    {
        $list = $this->Subsites();
        $currentSubsiteID = Subsite::currentSubsiteID();

        if ($list == null || $list->Count() == 1) {
            return false;
        }

        Requirements::javascript('simplesubsites/javascript/LeftAndMain_Subsites.js');

        $output = new ArrayList();

        foreach ($list as $subsite) {
            $CurrentState = $subsite->ID == $currentSubsiteID ? 'selected' : '';

            $output->push(new ArrayData(array(
                'CurrentState' => $CurrentState,
                'ID' => $subsite->ID,
                'Title' => Convert::raw2xml($subsite->Title)
            )));
        }

        return $output;
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
    public function canAccess()
    {
        // Admin can access everything, no point in checking.
        $member = Member::currentUser();
        if ($member &&
        (
            Permission::checkMember($member, 'ADMIN') || // 'Full administrative rights' in SecurityAdmin
            Permission::checkMember($member, 'CMS_ACCESS_LeftAndMain') // 'Access to all CMS sections' in SecurityAdmin
        )) {
            return true;
        }

        // Check if we have access to current section on the current subsite.
        $accessibleSites = $this->owner->sectionSites($member);
        if ($accessibleSites->count() && $accessibleSites->find('ID', Subsite::currentSubsiteID())) {
            // Current section can be accessed on the current site, all good.
            return true;
        }

        return false;
    }

    /**
     * Prevent accessing disallowed resources. This happens after onBeforeInit has executed,
     * so all redirections should've already taken place.
     */
    public function alternateAccessCheck()
    {
        return $this->owner->canAccess();
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
        // We are accessing the CMS, so we need to let Subsites know we will be using the session.
        Subsite::$use_session_subsiteid = true;

        // FIRST, check if we need to change subsites due to the URL.

        // Catch forced subsite changes that need to cause CMS reloads.
        if (isset($_GET['SubsiteID'])) {
            // Clear current page when subsite changes (or is set for the first time)
            if (!Session::get('SubsiteID') || $_GET['SubsiteID'] != Session::get('SubsiteID')) {
                Session::clear(get_class($this->owner) . ".currentPage");
            }

            // Update current subsite in session
            Subsite::changeSubsite($_GET['SubsiteID']);

            //Redirect to clear the current page
            if ($this->owner->canView(Member::currentUser())) {
                //Redirect to clear the current page
                return $this->owner->redirect($this->owner->Link());
            }
            //Redirect to the default CMS section
            return $this->owner->redirect('admin/');
        }

        // Automatically redirect the session to appropriate subsite when requesting a record.
        // This is needed to properly initialise the session in situations where someone opens the CMS via a link.
        $record = $this->owner->currentPage();
        if ($record && isset($record->SubsiteID) && is_numeric($record->SubsiteID) && isset($this->owner->urlParams['ID'])) {
            if ($this->shouldChangeSubsite(get_class($this->owner), $record->SubsiteID, Subsite::currentSubsiteID())) {
                // Update current subsite in session
                Subsite::changeSubsite($record->SubsiteID);

                if ($this->owner->canView(Member::currentUser())) {
                    //Redirect to clear the current page
                    return $this->owner->redirect($this->owner->getRequest()->getURL());
                }
                //Redirect to the default CMS section
                return $this->owner->redirect($_SERVER['REQUEST_URI']);
            }
        }

        // SECOND, check if we need to change subsites due to lack of permissions.

        if (!$this->owner->canAccess()) {
            $member = Member::currentUser();

            // Current section is not accessible, try at least to stick to the same subsite.
            $menu = CMSMenu::get_menu_items();
            foreach ($menu as $candidate) {
                if ($candidate->controller && $candidate->controller!=get_class($this->owner)) {
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
