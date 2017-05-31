<?php

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;

/**
 * @property string $Domain domain name of this subsite. Can include wildcards. Do not include the URL scheme here
 * that any links to this subsite should use the current protocol, and that both are supported.
 * @property string $SubstitutedDomain Domain name with all wildcards filled in
 * @property string $FullProtocol Full protocol including ://
 * @property bool $IsPrimary Is this the primary subdomain?
 */
class SubsiteDomain extends DataObject
{
    /**
     *
     * @var string
     */
    private static $default_sort = "\"IsPrimary\" DESC";

    /**
     *
     * @var array
     */
    private static $db = array(
        "Domain" => "Varchar(255)",
        "IsPrimary" => "Boolean",
    );

    private static $indexes = [
        'Domain' => true,
        'IsPrimary' => true,
    ];

    /**
     * Get the descriptive title for this domain
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->Domain;
    }

    /**
     *
     * @var array
     */
    private static $has_one = array(
        "Subsite" => "Subsite",
    );

    /**
     * @config
     * @var array
     */
    private static $summary_fields=array(
        'Domain',
        'IsPrimary',
    );

    /**
     * @config
     * @var array
     */
    private static $casting = array(
        'SubstitutedDomain' => 'Varchar',
        'FullProtocol' => 'Varchar',
        'AbsoluteLink' => 'Varchar',
    );

    /**
     *
     * @return \FieldList
     */
    public function getCMSFields()
    {
        $fields = new FieldList(
            WildcardDomainField::create('Domain', $this->fieldLabel('Domain'), null, 255)
                ->setDescription(_t(
                    'SubsiteDomain.DOMAIN_DESCRIPTION',
                    'Hostname of this subsite (exclude protocol). Allows wildcards (*).'
                )),
            CheckboxField::create('IsPrimary', $this->fieldLabel('IsPrimary'))
                ->setDescription(_t(
                    'SubsiteDomain.PRIMARY_DESCRIPTION',
                    'Mark this as the default domain for this subsite'
                ))
        );

        $this->extend('updateCMSFields', $fields);
        return $fields;
    }

    /**
     *
     * @param bool $includerelations
     * @return array
     */
    public function fieldLabels($includerelations = true)
    {
        $labels = parent::fieldLabels($includerelations);
        $labels['Domain'] = _t('SubsiteDomain.DOMAIN', 'Domain');
        $labels['IsPrimary'] = _t('SubsiteDomain.IS_PRIMARY', 'Is Primary Domain?');

        return $labels;
    }

    /**
     * Get the link to this subsite
     *
     * @return string
     */
    public function Link()
    {
        return $this->getFullProtocol() . $this->Domain;
    }

    /**
     * Gets the full protocol (including ://) for this domain
     *
     * @return string
     */
    public function getFullProtocol()
    {
        return Director::protocol();
    }

    /**
     * Retrieves domain name with wildcards substituted with actual values
     *
     * @todo Refactor domains into separate wildcards / primary domains
     *
     * @return string
     */
    public function getSubstitutedDomain()
    {
        $currentHost = $_SERVER['HTTP_HOST'];

        // If there are wildcards in the primary domain (not recommended), make some
        // educated guesses about what to replace them with:
        $domain = preg_replace('/\.\*$/', ".{$currentHost}", $this->Domain);

        // Default to "subsite." prefix for first wildcard
        // TODO Whats the significance of "subsite" in this context?!
        $domain = preg_replace('/^\*\./', "subsite.", $domain);

        if (!Subsite::$strict_subdomain_matching) {
            // *Only* removes "intermediate" subdomains, so 'subdomain.www.domain.com' becomes 'subdomain.domain.com'
            $domain = str_replace('.www.', '.', $domain);
        }

        return $domain;
    }

    /**
     * Get absolute link for this domain
     *
     * @return string
     */
    public function getAbsoluteLink()
    {
        return $this->getFullProtocol() . $this->getSubstitutedDomain();
    }

    /**
     * Get absolute baseURL for this domain
     *
     * @return string
     */
    public function absoluteBaseURL()
    {
        return Controller::join_links(
            $this->getAbsoluteLink(),
            Director::baseURL()
        );
    }
}
