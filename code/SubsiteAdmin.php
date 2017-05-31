<?php

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldDetailForm;

/**
 * Admin interface to manage and create {@link Subsite} instances.
 *
 * @package subsites
 */
class SubsiteAdmin extends ModelAdmin
{
    private static $managed_models = ['Subsite'];

    private static $url_segment = 'subsites';

    private static $menu_title = 'Subsites';

    public $showImportForm = false;

    private static $menu_icon_class = 'font-icon-sitemap';

    private static $tree_class = 'Subsite';

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $grid = $form->Fields()->dataFieldByName('Subsite');
        if ($grid) {
            $grid->getConfig()->getComponentByType('SilverStripe\\Forms\\GridField\\GridFieldPaginator')->setItemsPerPage(100);
            $grid->getConfig()->removeComponentsByType('SilverStripe\\Forms\\GridField\\GridFieldDetailForm');
            $grid->getConfig()->addComponent(new GridFieldDetailForm());
        }

        return $form;
    }
}
