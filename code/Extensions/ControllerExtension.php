<?php

namespace AirNZ\SimpleSubsites\Extensions;

use AirNZ\SimpleSubsites\Model\Subsite;
use SilverStripe\Core\Extension;
use SilverStripe\View\SSViewer;

/**
 * Make the current subsite available in templates
 */
class ControllerExtension extends Extension
{
    public function CurrentSubsite()
    {
        return Subsite::currentSubsite() ?: null;
    }
}
