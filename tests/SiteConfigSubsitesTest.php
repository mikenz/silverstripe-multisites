<?php

namespace AirNZ\SimpleSubsites\Tests;

use SilverStripe\SiteConfig\SiteConfig;
use AirNZ\SimpleSubsites\Extensions\SiteConfigExtension;
use AirNZ\SimpleSubsites\Model\Subsite;

class SiteConfigSubsitesTest extends BaseSubsiteTest
{
    public static $fixture_file = 'simplesubsites/tests/SubsiteTest.yml';

    public function setUp()
    {
        parent::setUp();
        // parent::setUp disables subsite filter by default to not impact other module's tests
        Subsite::disable_subsite_filter(false);
    }

    public function testEachSubsiteHasAUniqueSiteConfig()
    {
        $subsite1 = $this->objFromFixture(Subsite::class, 'domaintest1');
        $subsite2 = $this->objFromFixture(Subsite::class, 'domaintest2');

        $this->assertTrue(is_array(singleton(SiteConfigExtension::class)->extraStatics()));

        Subsite::changeSubsite($subsite1->ID);
        $sc = SiteConfig::current_site_config();
        $sc->Title = 'Subsite1';
        $sc->write();

        Subsite::changeSubsite($subsite2->ID);
        $sc = SiteConfig::current_site_config();
        $sc->Title = 'Subsite2';
        $sc->write();

        Subsite::changeSubsite($subsite1->ID);
        $this->assertEquals(SiteConfig::current_site_config()->Title, 'Subsite1');
        Subsite::changeSubsite($subsite2->ID);
        $this->assertEquals(SiteConfig::current_site_config()->Title, 'Subsite2');

        $keys = SiteConfig::current_site_config()->extend('cacheKeyComponent');
        $this->assertContains('subsite-' . $subsite2->ID, $keys);
    }
}
