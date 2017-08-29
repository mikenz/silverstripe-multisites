<?php

namespace AirNZ\SimpleSubsites\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Director;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Security\Member;
use AirNZ\SimpleSubsites\Model\Subsite;

class SubsiteTest extends BaseSubsiteTest
{
    public static $fixture_file = 'simplesubsites/tests/SubsiteTest.yml';

    /**
     * Original value of $_SERVER
     *
     * @var array
     */
    protected $origServer = array();

    public function setUp()
    {
        parent::setUp();

        // parent::setUp disables subsite filter by default to not impact other module's tests
        Subsite::disable_subsite_filter(false);

        Config::inst()->update(Director::class, 'alternate_base_url', '/');
        $this->origServer = $_SERVER;
    }

    public function tearDown()
    {
        $_SERVER = $this->origServer;

        parent::tearDown();
    }

    /**
     * Duplicate a subsite
     */
    public function testSubsiteCreation()
    {
        Subsite::$write_hostmap = false;

        // Create the instance
        $template = $this->objFromFixture(Subsite::class, 'main');

        // Test that changeSubsite is working
        Subsite::changeSubsite($template->ID);

        // Publish all the pages in the template, testing that DataObject::get only returns pages from the chosen subsite
        $pages = DataObject::get(SiteTree::class);
        $totalPages = $pages->Count();
        foreach ($pages as $page) {
            $this->assertEquals($template->ID, $page->SubsiteID);
            $page->publish('Stage', 'Live');
        }

        // Create a new site
        $subsite = $template->duplicate();

        // Check title
        $this->assertEquals($subsite->Title, $template->Title);

        // Another test that changeSubsite is working
        $subsite->activate();

        // Ensure no pages copied with the subsite
        $pages = DataObject::get(SiteTree::class);
        $this->assertEquals($pages->Count(), 0, 'Ensure No pages copied when duplicating subsite');

        Subsite::changeSubsite($template->ID);
    }

    /**
     * Confirm that domain lookup is working
     */
    public function testDomainLookup()
    {
        $domaintest1 = $this->objFromFixture(Subsite::class, 'domaintest1');
        $domaintest2 = $this->objFromFixture(Subsite::class, 'domaintest2');
        $domaintest4 = $this->objFromFixture(Subsite::class, 'domaintest4');

        $this->assertEquals(
            $domaintest1->ID,
            Subsite::getSubsiteIDForDomain('one.example.org')
        );
        $this->assertEquals(
            $domaintest2->ID,
            Subsite::getSubsiteIDForDomain('two.mysite.com')
        );
        $this->assertEquals(
            $domaintest4->ID,
            Subsite::getSubsiteIDForDomain('four.mysite.com')
        );

        $this->assertFalse(Subsite::getSubsiteIDForDomain('*.example.org'));
        $this->assertFalse(Subsite::getSubsiteIDForDomain('www.example.org'));
        $this->assertFalse(Subsite::getSubsiteIDForDomain('www.one.example.org'));
    }

    public function testAllSites()
    {
        $subsites = Subsite::all_sites();
        $this->assertDOSEquals([
            ['Title' =>'Template'],
            ['Title' =>'Subsite1 Template'],
            ['Title' =>'Subsite2 Template'],
            ['Title' =>'Test 1'],
            ['Title' =>'Test 2'],
            ['Title' =>'Test 3'],
            ['Title' => 'Test 4'],
        ], $subsites, 'Lists all subsites');
    }

    public function testAllAccessibleSites()
    {
        $this->logInAs($this->objFromFixture(Member::class, 'subsite1member'));
        $subsites = Subsite::all_accessible_sites();
        $this->assertDOSEquals([
            ['Title' =>'Subsite1 Template']
        ], $subsites, 'Lists member-accessible sites.');
    }

    /**
     * Test Subsite::accessible_sites()
     */
    public function testAccessibleSites()
    {
        $member1Sites = Subsite::accessible_sites(
            "CMS_ACCESS_CMSMain",
            $this->objFromFixture(Member::class, 'subsite1member')
        );

        $member1SiteTitles = $member1Sites->column("Title");
        sort($member1SiteTitles);
        $this->assertEquals('Subsite1 Template', $member1SiteTitles[0], 'Member can get to a subsite via a group');

        $adminSites = Subsite::accessible_sites(
            "CMS_ACCESS_CMSMain",
            $this->objFromFixture(Member::class, 'admin')
        );
        $adminSiteTitles = $adminSites->column("Title");
        sort($adminSiteTitles);

        $this->assertEquals([
            'Subsite1 Template',
            'Subsite2 Template',
            'Template',
            'Test 1',
            'Test 2',
            'Test 3',
            'Test 4',
        ], array_values($adminSiteTitles));

        $member2Sites = Subsite::accessible_sites(
            "CMS_ACCESS_CMSMain",
            $this->objFromFixture(Member::class, 'subsite1member2')
        );
        $member2SiteTitles = $member2Sites->column("Title");
        sort($member2SiteTitles);
        $this->assertEquals('Subsite1 Template', $member2SiteTitles[0], 'Member can get to subsite via a group role');
    }

    public function testhasMainSitePermission()
    {
        $admin = $this->objFromFixture(Member::class, 'admin');
        $subsite1member = $this->objFromFixture(Member::class, 'subsite1member');
        $subsite1admin = $this->objFromFixture(Member::class, 'subsite1admin');
        $allsubsitesauthor = $this->objFromFixture(Member::class, 'allsubsitesauthor');

        $this->assertTrue(
            Subsite::hasMainSitePermission($admin),
            'Default permissions granted for super-admin'
        );
        $this->assertTrue(
            Subsite::hasMainSitePermission($admin, array("ADMIN")),
            'ADMIN permissions granted for super-admin'
        );
        $this->assertFalse(
            Subsite::hasMainSitePermission($subsite1admin, array("ADMIN")),
            'ADMIN permissions (on main site) denied for subsite1 admin'
        );
        $this->assertFalse(
            Subsite::hasMainSitePermission($subsite1admin, array("CMS_ACCESS_CMSMain")),
            'CMS_ACCESS_CMSMain (on main site) denied for subsite1 admin'
        );
        $this->assertFalse(
            Subsite::hasMainSitePermission($allsubsitesauthor, array("ADMIN")),
            'ADMIN permissions (on main site) denied for CMS author with edit rights on all subsites'
        );
        $this->assertTrue(
            Subsite::hasMainSitePermission($allsubsitesauthor, array("CMS_ACCESS_CMSMain")),
            'CMS_ACCESS_CMSMain (on main site) granted for CMS author with edit rights on all subsites'
        );
        $this->assertFalse(
            Subsite::hasMainSitePermission($subsite1member, array("ADMIN")),
            'ADMIN (on main site) denied for subsite1 subsite1 cms author'
        );
        $this->assertFalse(
            Subsite::hasMainSitePermission($subsite1member, array("CMS_ACCESS_CMSMain")),
            'CMS_ACCESS_CMSMain (on main site) denied for subsite1 cms author'
        );
    }
}
