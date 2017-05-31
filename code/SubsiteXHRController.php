<?php

use SilverStripe\Admin\LeftAndMain;

/**
 * Section-agnostic PJAX controller.
 */
class SubsiteXHRController extends LeftAndMain
{

    private static $url_segment = 'SubsiteXHRController';

    public function getResponseNegotiator()
    {
        $negotiator = parent::getResponseNegotiator();
        $self = $this;

        // Register a new callback
        $negotiator->setCallback('SubsiteList', function () use (&$self) {
            return $self->SubsiteList();
        });

        return $negotiator;
    }

    /**
     * Provide the list of available subsites as a cms-section-agnostic PJAX handler.
     */
    public function SubsiteList()
    {
        return $this->renderWith('Includes/SubsiteList');
    }
}
