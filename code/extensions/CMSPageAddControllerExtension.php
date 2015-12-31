<?php
class CMSPageAddControllerExtension extends Extension
{

    public function updatePageOptions(&$fields)
    {
        // Any blacklisted page types?
        $blacklisted = array();
        $subsite = Subsite::currentSubsite();
        if ($subsite && $subsite->exists() && $subsite->PageTypeBlacklist) {
            $blacklisted = explode(',', $subsite->PageTypeBlacklist);
        }

        if ($blacklisted) {
            // Remove blacklisted page types
            $pageTypes = $fields->fieldByName('PageType');
            $source = $pageTypes->getSource();
            foreach ($source as $k => $v) {
                if (in_array($k, $blacklisted)) {
                    unset($source[$k]);
                }
            }
            $pageTypes->setSource($source);
        }

        $fields->push(new HiddenField('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
    }
}
