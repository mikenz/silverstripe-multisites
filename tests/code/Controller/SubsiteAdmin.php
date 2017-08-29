<?php

namespace AirNZ\SimpleSubsites\Controller;

use AirNZ\SimpleSubsites\Model\Subsite;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldPaginator;

/**
 * Admin interface to manage and create {@link AirNZ\SimpleSubsites\Model\Subsite} instances.
 */
class SubsiteAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $url_segment = 'subsites';

    /**
     * @var string
     */
    private static $menu_title = 'Subsites';

    /**
     * @var array
     */
    private static $managed_models = [Subsite::class];

    /**
     * @var boolean
     */
    public $showImportForm = false;

    /**
     * @var string
     */
    private static $menu_icon_class = 'font-icon-sitemap';

    /**
     * @var string
     */
    private static $tree_class = Subsite::class;

    /**
     * Produces an edit form for editing a subsite
     *
     * @param int|null $id
     * @param \SilverStripe\Forms\FieldList $fields
     * @return \SilverStripe\Forms\Form A Form object with one tab per {@link \SilverStripe\Forms\GridField\GridField}
     */
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $grid = $form->Fields()->dataFieldByName('AirNZ-SimpleSubsites-Model-Subsite');
        if ($grid) {
            $grid->getConfig()->getComponentByType(GridFieldPaginator::class)->setItemsPerPage(100);
            $grid->getConfig()->removeComponentsByType(GridFieldDetailForm::class);
            $grid->getConfig()->addComponent(new GridFieldDetailForm());
        }

        return $form;
    }
}
