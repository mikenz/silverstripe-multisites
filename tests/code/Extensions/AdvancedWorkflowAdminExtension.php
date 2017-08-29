<?php

namespace AirNZ\SimpleSubsites\Extensions;

use SilverStripe\Core\Extension;
use AirNZ\SimpleSubsites\Model\Subsite;

/**
 * Extension to disable Subsites in AdvancedWorkflowAdmin
 */
class AdvancedWorkflowAdminExtension extends Extension
{
    public function init()
    {
        Subsite::disable_subsite_filter(true);
    }
}
