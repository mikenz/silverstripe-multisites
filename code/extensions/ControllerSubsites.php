<?php

use SilverStripe\View\SSViewer;
use SilverStripe\Core\Extension;

/**
 * @package subsites
 */
class ControllerSubsites extends Extension
{
    public function CurrentSubsite()
    {
        if ($subsite = Subsite::currentSubsite()) {
            return $subsite;
        }
    }
}
