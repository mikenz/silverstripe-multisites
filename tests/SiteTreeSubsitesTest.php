<?php

namespace AirNZ\SimpleSubsites\Tests;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Forms\FieldList;
use SilverStripe\Control\Session;
use SilverStripe\Dev\TestOnly;
use AirNZ\SimpleSubsites\Extensions\SiteTreeExtension;
use AirNZ\SimpleSubsites\Model\Subsite;
use AirNZ\SimpleSubsites\Tests\SiteTreeSubsitesTest_ClassA;
use AirNZ\SimpleSubsites\Tests\SiteTreeSubsitesTest_ClassB;
use AirNZ\SimpleSubsites\Tests\SubsiteTest_Page;

class SiteTreeSubsitesTest extends BaseSubsiteTest
{
    public static $fixture_file = 'simplesubsites/tests/SubsiteTest.yml';

    protected static $extra_dataobjects = [
        SiteTreeSubsitesTest_ClassA::class,
        SiteTreeSubsitesTest_ClassB::class,
    ];

    protected static $illegal_extensions = [
        'SilverStripe\\CMS\\Model\\SiteTree' => array('Translatable')
    ];

    public function setUp()
    {
        parent::setUp();
        // parent::setUp disables subsite filter by default to not impact other module's tests
        Subsite::disable_subsite_filter(false);
    }

    public function testPagesInDifferentSubsitesCanShareURLSegment()
    {
        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
        $subsite2 = $this->objFromFixture(Subsite::class, 'subsite2');

        Subsite::changeSubsite($subsite2->ID);
        $pageMain = new SiteTree();
        $pageMain->URLSegment = 'testpage';
        $pageMain->write();
        $pageMain->publish('Stage', 'Live');

        $pageMainOther = new SiteTree();
        $pageMainOther->URLSegment = 'testpage';
        $pageMainOther->write();
        $pageMainOther->publish('Stage', 'Live');

        $this->assertNotEquals(
            $pageMain->URLSegment,
            $pageMainOther->URLSegment,
            'Pages in same subsite cant share the same URL'
        );

        Subsite::changeSubsite($subsite1->ID);

        $pageSubsite1 = new SiteTree();
        $pageSubsite1->URLSegment = 'testpage';
        $pageSubsite1->write();
        $pageSubsite1->publish('Stage', 'Live');

        $this->assertEquals(
            $pageMain->URLSegment,
            $pageSubsite1->URLSegment,
            'Pages in different subsites can share the same URL'
        );
    }

    public function testBasicSanity()
    {
        $this->assertTrue(singleton('SilverStripe\\CMS\\Model\\SiteTree')->getSiteConfig() instanceof SiteConfig);
        // The following assert is breaking in Translatable.
        $this->assertTrue(singleton('SilverStripe\\CMS\\Model\\SiteTree')->getCMSFields() instanceof FieldList);
        $this->assertTrue(is_array(singleton(SiteTreeExtension::class)->extraStatics()));
    }

    public function testCanEditSiteTree()
    {
        $admin = $this->objFromFixture('SilverStripe\\Security\\Member', 'admin');
        $subsite1member = $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite1member');
        $subsite2member = $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite2member');
        $mainpage = $this->objFromFixture(SubsiteTest_Page::class, 'home');
        $subsite1page = $this->objFromFixture(SubsiteTest_Page::class, 'subsite1_home');
        $subsite2page = $this->objFromFixture(SubsiteTest_Page::class, 'subsite2_home');
        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
        $subsite2 = $this->objFromFixture(Subsite::class, 'subsite2');

        // Cant pass member as arguments to canEdit() because of GroupSubsites
        $this->logInAs($admin->ID);
        $this->assertTrue(
            (bool)$subsite1page->canEdit(),
            'Administrators can edit all subsites'
        );

        // @todo: Workaround because GroupSubsites->augmentSQL() is relying on session state
        Subsite::changeSubsite($subsite1);

        $this->logInAs($subsite1member->ID);
        $this->assertTrue(
            (bool)$subsite1page->canEdit(),
            'Members can edit pages on a subsite if they are in a group belonging to this subsite'
        );

        $this->logInAs($subsite2member->ID);
        $this->assertFalse(
            (bool)$subsite1page->canEdit(),
            'Members cant edit pages on a subsite if they are not in a group belonging to this subsite'
        );
    }

    public function testCanDeleteSiteTree()
    {
        $admin = $this->objFromFixture('SilverStripe\\Security\\Member', 'admin');
        $subsite1member = $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite1member');
        $subsite2member = $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite2member');
        $mainpage = $this->objFromFixture(SubsiteTest_Page::class, 'home');
        $subsite1page = $this->objFromFixture(SubsiteTest_Page::class, 'subsite1_home');
        $subsite2page = $this->objFromFixture(SubsiteTest_Page::class, 'subsite2_home');
        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
        $subsite2 = $this->objFromFixture(Subsite::class, 'subsite2');

        // Cant pass member as arguments to canEdit() because of GroupSubsites
        $this->logInAs($admin->ID);
        $this->assertTrue(
            (bool)$subsite1page->canDelete(),
            'Administrators can delete on all subsites'
        );

        // @todo: Workaround because GroupSubsites->augmentSQL() is relying on session state
        Subsite::changeSubsite($subsite1);

        $this->logInAs($subsite1member->ID);
        $this->assertTrue(
            (bool)$subsite1page->canDelete(),
            'Members can delete pages on a subsite if they are in a group belonging to this subsite'
        );

        $this->logInAs($subsite2member->ID);
        $this->assertFalse(
            (bool)$subsite1page->canDelete(),
            'Members cant delete pages on a subsite if they are not in a group belonging to this subsite'
        );
    }

    public function testcanAddChildren()
    {
        $admin = $this->objFromFixture('SilverStripe\\Security\\Member', 'admin');
        $subsite1member = $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite1member');
        $subsite2member = $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite2member');
        $mainpage = $this->objFromFixture(SubsiteTest_Page::class, 'home');
        $subsite1page = $this->objFromFixture(SubsiteTest_Page::class, 'subsite1_home');
        $subsite2page = $this->objFromFixture(SubsiteTest_Page::class, 'subsite2_home');
        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
        $subsite2 = $this->objFromFixture(Subsite::class, 'subsite2');

        // Cant pass member as arguments to canEdit() because of GroupSubsites
        $this->logInAs($admin->ID);
        $this->assertTrue(
            (bool)$subsite1page->canAddChildren(),
            'Administrators can add children on all subsites'
        );

        // @todo: Workaround because GroupSubsites->augmentSQL() is relying on session state
        Subsite::changeSubsite($subsite1);

        $this->logInAs($subsite1member->ID);
        $this->assertTrue(
            (bool)$subsite1page->canAddChildren(),
            'Members can add children pages on a subsite if they are in a group belonging to this subsite'
        );

        $this->logInAs($subsite2member->ID);
        $this->assertFalse(
            (bool)$subsite1page->canAddChildren(),
            'Members cant add children pages on a subsite if they are not in a group belonging to this subsite'
        );
    }

    public function testcanPublish()
    {
        $admin = $this->objFromFixture('SilverStripe\\Security\\Member', 'admin');
        $subsite1member = $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite1member');
        $subsite2member = $this->objFromFixture('SilverStripe\\Security\\Member', 'subsite2member');
        $mainpage = $this->objFromFixture(SubsiteTest_Page::class, 'home');
        $subsite1page = $this->objFromFixture(SubsiteTest_Page::class, 'subsite1_home');
        $subsite2page = $this->objFromFixture(SubsiteTest_Page::class, 'subsite2_home');
        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
        $subsite2 = $this->objFromFixture(Subsite::class, 'subsite2');

        $this->logInAs($admin->ID);
        $this->assertTrue(
            (bool)$subsite1page->canPublish(),
            'Administrators can publish on all subsites'
        );

        // @todo: Workaround because GroupSubsites->augmentSQL() is relying on session state
        Subsite::changeSubsite($subsite1);

        $this->logInAs($subsite1member->ID);
        $this->assertTrue(
            (bool)$subsite1page->canPublish(),
            'Members can publish pages on a subsite if they are in a group belonging to this subsite'
        );

        $this->logInAs($subsite2member->ID);
        $this->assertFalse(
            (bool)$subsite1page->canPublish(),
            'Members cant publish pages on a subsite if they are not in a group belonging to this subsite'
        );
    }

    /**
     * Similar to {@link SubsitesVirtualPageTest->testSubsiteVirtualPageCanHaveSameUrlsegmentAsOtherSubsite()}.
     */
    public function testTwoPagesWithSameURLOnDifferentSubsites()
    {
        // Set up a couple of pages with the same URL on different subsites
        $s1 = $this->objFromFixture(Subsite::class, 'domaintest1');
        $s2 = $this->objFromFixture(Subsite::class, 'domaintest2');

        $p1 = new SiteTree();
        $p1->Title = $p1->URLSegment = "test-page";
        $p1->SubsiteID = $s1->ID;
        $p1->write();

        $p2 = new SiteTree();
        $p2->Title = $p1->URLSegment = "test-page";
        $p2->SubsiteID = $s2->ID;
        $p2->write();

        // Check that the URLs weren't modified in our set-up
        $this->assertEquals($p1->URLSegment, 'test-page');
        $this->assertEquals($p2->URLSegment, 'test-page');

        // Check that if we switch between the different subsites, we receive the correct pages
        Subsite::changeSubsite($s1);
        $this->assertEquals($p1->ID, SiteTree::get_by_link('test-page')->ID);

        Subsite::changeSubsite($s2);
        $this->assertEquals($p2->ID, SiteTree::get_by_link('test-page')->ID);
    }
}
