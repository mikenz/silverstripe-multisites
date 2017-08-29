<?php

namespace AirNZ\SimpleSubsites\Extensions;

use AirNZ\SimpleSubsites\Model\Subsite;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\Queries\SQLSelect;
use UnexpectedValueException;

/**
 * Extension for the SiteConfig object to add subsites support
 */
class SiteConfigExtension extends DataExtension
{
    private static $has_one = [
        'Subsite' => Subsite::class, // The subsite that this page belongs to
    ];

    /**
     * Update any requests to limit the results to the current site
     */
    public function augmentSQL(SQLSelect $query, DataQuery $dataQuery = null)
    {
        if (Subsite::$disable_subsite_filter) {
            return;
        }

        // If you're querying by ID, ignore the sub-site - this is a bit ugly...
        if ($query->filtersOnID()) {
            return;
        }
        $regexp = '/^(.*\.)?("|`)?SubsiteID("|`)?\s?=/';
        foreach ($query->getWhereParameterised($parameters) as $predicate) {
            if (preg_match($regexp, $predicate)) {
                return;
            }
        }

        try {
            $subsiteID = (int)Subsite::currentSubsiteID();

            $froms=$query->getFrom();
            $froms=array_keys($froms);
            $tableName = array_shift($froms);
            /** @skipUpgrade */
            if ($tableName != 'SiteConfig') {
                return;
            }
            $query->addWhere("\"$tableName\".\"SubsiteID\" IN ($subsiteID)");
        } catch (UnexpectedValueException $e) {
            // No subsites exist yet
        }
    }

    public function onBeforeWrite()
    {
        if ((!is_numeric($this->owner->ID) || !$this->owner->ID) && !$this->owner->SubsiteID) {
            $this->owner->SubsiteID = Subsite::currentSubsiteID();
        }
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific
     */
    public function cacheKeyComponent()
    {
        try {
            return 'subsite-' . Subsite::currentSubsiteID();
        } catch (UnexpectedValueException $e) {
            return 'subsite-none';
        }
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->push(new HiddenField('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
    }
}
