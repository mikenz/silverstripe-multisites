<?php

use SilverStripe\Admin\CMSMenu;
use SilverStripe\Reports\Report;
use AirNZ\SimpleSubsites\Reports\SubsiteReportWrapper;


$excluded_reports = Report::config()->get('excluded_reports');
$excluded_reports[] = SubsiteReportWrapper::class;
Report::config()->set('excluded_reports', $excluded_reports);

// Display in cms menu
CMSMenu::remove_menu_item('SubsiteXHRController');
