<?php

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\Requirements;
use SilverStripe\CMS\Model\SiteTree;
use AirNZ\SimpleSubsites\Model\Subsite;

/**
 * Wraps around a TreedropdownField to add ability for temporary
 * switching of subsite sessions.
 */
class SubsitesTreeDropdownField extends TreeDropdownField
{
    private static $allowed_actions = [
        'tree'
    ];

    protected $subsiteID = 0;

    protected $extraClasses = [
        'SubsitesTreeDropdownField'
    ];

    public function Field($properties = [])
    {
        $html = parent::Field($properties);

        Requirements::javascript('simplesubsites/javascript/SubsitesTreeDropdownField.js');

        return $html;
    }

    public function setSubsiteID($id)
    {
        $this->subsiteID = $id;
    }

    public function getSubsiteID()
    {
        return $this->subsiteID;
    }

    public function tree(HTTPRequest $request)
    {
        $session = Controller::curr()->getRequest()->getSession();
        $oldSubsiteID = Subsite::currentSubsiteID();
        if ($request->getVar($this->name . '_SubsiteID')) {
            $this->subsiteID = $request->getVar($this->name . '_SubsiteID');
        }
        $session->set('SubsiteID', $this->subsiteID);

        $results = parent::tree($request);

        $session->set('SubsiteID', $oldSubsiteID);

        return $results;
    }

    /**
     * Changes this field to the readonly field.
     */
    public function performReadonlyTransformation()
    {
        $pageName = $this->value;

        $session = Controller::curr()->getRequest()->getSession();
        $oldSubsiteID = Subsite::currentSubsiteID();
        $session->set('SubsiteID', (int)$this->subsiteID);
        if ($this->value) {
            $page = DataObject::get_by_id(SiteTree::class, $this->value);
            if ($page) {
                $pageName = $page->MenuTitle;
                do {
                    $page = $page->Parent();
                    if (!$page || !$page->ID) {
                        break;
                    }
                    $pageName = $page->MenuTitle . ' > ' . $pageName;
                } while ($page->ParentID);
            }
        }
        $session->set('SubsiteID', (int)$oldSubsiteID);

        return new ReadonlyField($this->name, $this->title, $pageName);
    }
}
