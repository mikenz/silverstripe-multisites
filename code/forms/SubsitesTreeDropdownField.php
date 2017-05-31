<?php

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\Requirements;
use SilverStripe\CMS\Model\SiteTree;

/**
 * Wraps around a TreedropdownField to add ability for temporary
 * switching of subsite sessions.
 *
 * @package subsites
 */
class SubsitesTreeDropdownField extends TreeDropdownField
{
    private static $allowed_actions = array(
        'tree'
    );

    protected $subsiteID = 0;

    protected $extraClasses = array('SubsitesTreeDropdownField');

    public function Field($properties = array())
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
        $oldSubsiteID = Session::get('SubsiteID');
        if ($request->getVar($this->name . '_SubsiteID')) {
            $this->subsiteID = $request->getVar($this->name . '_SubsiteID');
        }
        Session::set('SubsiteID', $this->subsiteID);

        $results = parent::tree($request);

        Session::set('SubsiteID', $oldSubsiteID);

        return $results;
    }

    /**
     * Changes this field to the readonly field.
     */
    public function performReadonlyTransformation()
    {
        $pageName = $this->value;

        $oldSubsiteID = Session::get('SubsiteID');
        Session::set('SubsiteID', $this->subsiteID);
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
        Session::set('SubsiteID', $oldSubsiteID);

        return new ReadonlyField($this->name, $this->title, $pageName);
    }
}
