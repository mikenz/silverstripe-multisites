<?php

namespace AirNZ\SimpleSubsites\Tests;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\SiteConfig\SiteConfigLeftAndMain;
use SilverStripe\Core\Injector\Injector;
use AirNZ\SimpleSubsites\Model\Subsite;

class LeftAndMainSubsitesTest extends FunctionalTest
{
    public static $fixture_file = 'simplesubsites/tests/SubsiteTest.yml';

    public function setUp()
    {
        parent::setUp();
        // parent::setUp disables subsite filter by default to not impact other module's tests
        Subsite::disable_subsite_filter(false);
    }

    /**
     * Avoid subsites filtering on fixture fetching.
     */
    public function objFromFixture($class, $id)
    {
        Subsite::disable_subsite_filter(true);
        $obj = parent::objFromFixture($class, $id);
        Subsite::disable_subsite_filter(false);

        return $obj;
    }

    public function testSectionSites()
    {
        $member = $this->objFromFixture(Member::class, 'subsite1member');

        $cmsmain = Injector::inst()->create(CMSMain::class);
        $this->assertDOSEquals(
            [
                array('Title' =>'Subsite1 Template')
            ],
            $cmsmain->sectionSites($member),
            'Lists member-accessible sites for the accessible controller.'
        );

        $siteconfigadmin = Injector::inst()->create(SiteConfigLeftAndMain::class);
        $this->assertDOSEquals(
            [],
            $siteconfigadmin->sectionSites($member),
            'Does not list any sites for forbidden controller.'
        );
    }

    public function testAccessChecksDontChangeCurrentSubsite()
    {
        $admin = $this->objFromFixture(Member::class, "admin");
        $this->loginAs($admin);
        $ids = array();

        $subsite1 = $this->objFromFixture(Subsite::class, 'domaintest1');
        $subsite2 = $this->objFromFixture(Subsite::class, 'domaintest2');
        $subsite3 = $this->objFromFixture(Subsite::class, 'domaintest3');

        $ids[] = $subsite1->ID;
        $ids[] = $subsite2->ID;
        $ids[] = $subsite3->ID;
        $ids[] = 0;

        // Enable session-based subsite tracking.
        Subsite::$use_session_subsiteid = true;

        foreach ($ids as $id) {
            Subsite::changeSubsite($id);
            $this->assertEquals($id, Subsite::currentSubsiteID());

            $left = new CMSMain();
            $this->assertTrue($left->canView(), "Admin user can view subsites LeftAndMain with id = '$id'");
            $this->assertEquals(
                $id,
                Subsite::currentSubsiteID(),
                "The current subsite has not been changed in the process of checking permissions for admin user."
            );
        }
    }

    public function testShouldChangeSubsite()
    {
        $l = new CMSMain();
        $this->assertTrue($l->shouldChangeSubsite('SilverStripe\\CMS\\Controllers\\CMSPageEditController', 0, 5));
        $this->assertFalse($l->shouldChangeSubsite('SilverStripe\\CMS\\Controllers\\CMSPageEditController', 0, 0));
        $this->assertTrue($l->shouldChangeSubsite('SilverStripe\\CMS\\Controllers\\CMSPageEditController', 1, 5));
        $this->assertFalse($l->shouldChangeSubsite('SilverStripe\\CMS\\Controllers\\CMSPageEditController', 1, 1));
    }
}
