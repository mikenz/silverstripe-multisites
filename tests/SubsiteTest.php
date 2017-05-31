<?php

use SilverStripe\ORM\DataObject;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\Director;
use SilverStripe\Dev\TestOnly;

class SubsiteTest extends BaseSubsiteTest
{
    public static $fixture_file = 'simplesubsites/tests/SubsiteTest.yml';

    /**
     * Original value of {@see SubSite::$strict_subdomain_matching}
     *
     * @var bool
     */
    protected $origStrictSubdomainMatching = null;

    /**
     * Original value of $_REQUEST
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
        $this->origStrictSubdomainMatching = Subsite::$strict_subdomain_matching;
        $this->origServer = $_SERVER;
        Subsite::$strict_subdomain_matching = false;
    }

    public function tearDown()
    {
        $_SERVER = $this->origServer;
        Subsite::$strict_subdomain_matching = $this->origStrictSubdomainMatching;

        parent::tearDown();
    }

    /**
     * Duplicate a subsite
     */
    public function testSubsiteCreation()
    {
        Subsite::$write_hostmap = false;

        // Create the instance
        $template = $this->objFromFixture('Subsite', 'main');

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
        // Clear existing fixtures
        foreach (DataObject::get('Subsite') as $subsite) {
            $subsite->delete();
        }
        foreach (DataObject::get('SubsiteDomain') as $domain) {
            $domain->delete();
        }

        // Much more expressive than YML in this case
        $subsite1 = $this->createSubsiteWithDomains(array(
            'one.example.org' => true,
            'one.*' => false,
        ));
        $subsite2 = $this->createSubsiteWithDomains(array(
            'two.mysite.com' => true,
            '*.mysite.com' => false,
            'subdomain.onmultiplesubsites.com' => false,
        ));
        $subsite3 = $this->createSubsiteWithDomains(array(
            'three.*' => true, // wildcards in primary domain are not recommended
            'subdomain.unique.com' => false,
            '*.onmultiplesubsites.com' => false,
        ));

        $this->assertEquals(
            $subsite3->ID,
            Subsite::getSubsiteIDForDomain('subdomain.unique.com'),
            'Full unique match'
        );

        $this->assertEquals(
            $subsite1->ID,
            Subsite::getSubsiteIDForDomain('one.example.org'),
            'Full match, doesn\'t complain about multiple matches within a single subsite'
        );

        $this->assertFalse(
            Subsite::getSubsiteIDForDomain('subdomain.onmultiplesubsites.com'),
            'Fails on multiple matches with wildcard vs. www across multiple subsites'
        );

        $this->assertEquals(
            $subsite1->ID,
            Subsite::getSubsiteIDForDomain('one.unique.com'),
            'Fuzzy match suffixed with wildcard (rule "one.*")'
        );

        $this->assertEquals(
            $subsite2->ID,
            Subsite::getSubsiteIDForDomain('two.mysite.com'),
            'Matches correct subsite for rule'
        );

        $this->assertEquals(
            $subsite2->ID,
            Subsite::getSubsiteIDForDomain('other.mysite.com'),
            'Fuzzy match prefixed with wildcard (rule "*.mysite.com")'
        );

        $this->assertFalse(
            Subsite::getSubsiteIDForDomain('unknown.madeup.com'),
            "Doesn't match unknown subsite"
        );
    }

    public function testStrictSubdomainMatching()
    {
        // Clear existing fixtures
        foreach (DataObject::get('Subsite') as $subsite) {
            $subsite->delete();
        }
        foreach (DataObject::get('SubsiteDomain') as $domain) {
            $domain->delete();
        }

        // Much more expressive than YML in this case
        $subsite1 = $this->createSubsiteWithDomains(array(
            'example.org' => true,
            'example.com' => false,
            '*.wildcard.com' => false,
        ));
        $subsite2 = $this->createSubsiteWithDomains(array(
            'www.example.org' => true,
            'www.wildcard.com' => false,
        ));
        $subsite3 = $this->createSubsiteWithDomains(array(
            'test.www.example.org' => true,
        ));

        Subsite::$strict_subdomain_matching = false;

        $this->assertEquals(
            $subsite1->ID,
            Subsite::getSubsiteIDForDomain('example.org'),
            'Exact matches without strict checking when not using www prefix'
        );
        $this->assertEquals(
            $subsite1->ID,
            Subsite::getSubsiteIDForDomain('www.example.org'),
            'Matches without strict checking when using www prefix, still matching first domain regardless of www prefix  (falling back to subsite primary key ordering)'
        );
        $this->assertEquals(
            $subsite1->ID,
            Subsite::getSubsiteIDForDomain('www.example.com'),
            'Fuzzy matches without strict checking with www prefix'
        );

        $this->assertFalse(
            Subsite::getSubsiteIDForDomain('www.wildcard.com'),
            'Doesn\'t match www prefix without strict check, even if a wildcard subdomain is in place'
        );

        Subsite::$strict_subdomain_matching = true;

        $this->assertEquals(
            $subsite1->ID,
            Subsite::getSubsiteIDForDomain('example.org'),
            'Matches with strict checking when not using www prefix'
        );
        $this->assertEquals(
            $subsite2->ID, // not 1
            Subsite::getSubsiteIDForDomain('www.example.org'),
            'Matches with strict checking when using www prefix'
        );
        $this->assertEquals(
            $subsite3->ID,
            Subsite::getSubsiteIDForDomain('test.www.example.org'),
            'Exact matches without strict checking when using www section'
        );
        $this->assertFalse(
            Subsite::getSubsiteIDForDomain('test.example.org'),
            'Doesn\'t fuzzy with strict checking when not using www section'
        );
        $this->assertFalse(
            Subsite::getSubsiteIDForDomain('www.example.com'),
            'Doesn\'t fuzzy match with strict checking when using www prefix'
        );
        $this->assertEquals(
            $subsite2->ID,
            Subsite::getSubsiteIDForDomain('www.wildcard.com'),
            'Ignores the wildcard domain and selects the exact match'
        );
    }

    protected function createSubsiteWithDomains($domains)
    {
        $subsite = new Subsite(array(
            'Title' => 'My Subsite'
        ));
        $subsite->write();
        foreach ($domains as $domainStr => $isPrimary) {
            $domain = new SubsiteDomain(array(
                'Domain' => $domainStr,
                'IsPrimary' => $isPrimary,
                'SubsiteID' => $subsite->ID
            ));
            $domain->write();
        }

        return $subsite;
    }

    /**
     * Test the Subsite->domain() method
     */
    public function testDefaultDomain()
    {
        $this->assertEquals('one.example.org',
            $this->objFromFixture('Subsite', 'domaintest1')->domain());

        $this->assertEquals('two.mysite.com',
            $this->objFromFixture('Subsite', 'domaintest2')->domain());

        Subsite::$strict_subdomain_matching = true;
        $this->assertEquals('four.mysite.com',
            $this->objFromFixture('Subsite', 'domaintest4')->domain());

        Subsite::$strict_subdomain_matching = false;
        $this->assertEquals('four.mysite.com',
            $this->objFromFixture('Subsite', 'domaintest4')->domain());

        $originalHTTPHost = $_SERVER['HTTP_HOST'];

        $_SERVER['HTTP_HOST'] = "www.example.org";
        $this->assertEquals('three.example.org',
            $this->objFromFixture('Subsite', 'domaintest3')->domain());

        $_SERVER['HTTP_HOST'] = "mysite.example.org";
        $this->assertEquals('three.mysite.example.org',
            $this->objFromFixture('Subsite', 'domaintest3')->domain());

        $this->assertEquals($_SERVER['HTTP_HOST'], singleton('Subsite')->PrimaryDomain);
        $this->assertEquals('http://'.$_SERVER['HTTP_HOST'].Director::baseURL(), singleton('Subsite')->absoluteBaseURL());
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
        $member = $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite1member');

        $subsites = Subsite::all_accessible_sites($member);
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
            $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite1member')
        );

        $member1SiteTitles = $member1Sites->column("Title");
        sort($member1SiteTitles);
        $this->assertEquals('Subsite1 Template', $member1SiteTitles[0], 'Member can get to a subsite via a group');

        $adminSites = Subsite::accessible_sites(
            "CMS_ACCESS_CMSMain",
            $this->objFromFixture('SilverStripe\\Security\\Member', 'admin')
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
            "CMS_ACCESS_CMSMain", false, null,
            $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite1member2')
        );
        $member2SiteTitles = $member2Sites->column("Title");
        sort($member2SiteTitles);
        $this->assertEquals('Subsite1 Template', $member2SiteTitles[0], 'Member can get to subsite via a group role');
    }

    public function testhasMainSitePermission()
    {
        $admin = $this->objFromFixture('SilverStripe\\Security\\Member', 'admin');
        $subsite1member = $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite1member');
        $subsite1admin = $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite1admin');
        $allsubsitesauthor = $this->objFromFixture('SilverStripe\\Security\\Member', 'allsubsitesauthor');

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

class SubsiteTest_Page extends SiteTree implements TestOnly
{
}
