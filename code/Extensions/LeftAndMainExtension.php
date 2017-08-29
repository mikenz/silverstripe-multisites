<?php

namespace AirNZ\SimpleSubsites\Extensions;

use AirNZ\SimpleSubsites\Model\Subsite;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Controller;
use SilverStripe\Security\Security;

/**
 * Decorator designed to add subsites support to LeftAndMain
 */
class LeftAndMainExtension extends Extension
{
    public function init()
    {
        Requirements::css('simplesubsites/css/LeftAndMain_Subsites.css');
        Requirements::javascript('simplesubsites/javascript/LeftAndMain_Subsites.js');
    }

    public function onBeforeInit()
    {
        // We are accessing the CMS, so we need to let Subsites know we will be using the session.
        Subsite::$use_session_subsiteid = true;

        // FIRST, check if we need to change subsites due to the URL.

        // Catch forced subsite changes that need to cause CMS reloads.
        if (isset($_GET['SubsiteID'])) {
            $session = Controller::curr()->getRequest()->getSession();

            // Clear current page when subsite changes (or is set for the first time)
            if (!$session->get('SubsiteID') || $_GET['SubsiteID'] != $session->get('SubsiteID')) {
                $session->clear(get_class($this->owner) . ".currentPage");
            }

            // Update current subsite in session
            Subsite::changeSubsite($_GET['SubsiteID']);

            // Redirect to clear the current page
            if ($this->owner->canView(Security::getCurrentUser())) {
                // Redirect to clear the current page
                return $this->owner->redirect($this->owner->Link());
            } elseif (Security::getCurrentUser()) {
                //Redirect to the default CMS section
                return $this->owner->redirect('admin/');
            }
        }
    }

    /*
     * Generates a list of subsites with the data needed to
     * produce a dropdown site switcher
     * @return ArrayList
     */
    public function ListSubsites()
    {
        $list = Subsite::all_accessible_sites();
        if ($list == null || $list->Count() == 1) {
            return false;
        }

        $currentSubsiteID = Subsite::currentSubsiteID();
        $output = new ArrayList();
        foreach ($list as $subsite) {
            $CurrentState = $subsite->ID == $currentSubsiteID ? 'selected' : '';
            $output->push(new ArrayData([
                'CurrentState' => $CurrentState,
                'ID' => $subsite->ID,
                'Title' => Convert::raw2xml($subsite->Title)
            ]));
        }

        return $output;
    }
}
