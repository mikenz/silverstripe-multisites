<?php

use SilverStripe\Admin\CMSMenu;
use SilverStripe\Reports\Report;

Report::add_excluded_reports('SubsiteReportWrapper');

//Display in cms menu
CMSMenu::remove_menu_item('SubsiteXHRController');
