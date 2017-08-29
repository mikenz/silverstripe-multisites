<?php

namespace AirNZ\SimpleSubsites\Extensions;

use AirNZ\SimpleSubsites\Model\Subsite;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;

/**
 * Extension to add the current SubsiteID as a hidden field when creating a new page
 */
class CMSPageAddControllerExtension extends Extension
{
    public function updatePageOptions(FieldList $fields)
    {
        $fields->push(new HiddenField('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
    }
}
