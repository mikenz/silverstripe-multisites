<?php

namespace AirNZ\SimpleSubsites\Tests;

use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\Control\Session;
use SilverStripe\Control\Director;
use SilverStripe\Security\Member;
use AirNZ\SimpleSubsites\Model\Subsite;

class SubsiteAdminTest extends BaseSubsiteTest
{
    public static $fixture_file = 'simplesubsites/tests/SubsiteTest.yml';

    public function setUp()
    {
        parent::setUp();
        // parent::setUp disables subsite filter by default to not impact other module's tests
        Subsite::disable_subsite_filter(false);
    }

    public function adminLoggedInSession()
    {
        $this->logInAs($this->idFromFixture(Member::class, 'admin'));
    }

    /**
     * Test generation of the view
     */
    public function testBasicView()
    {
        Subsite::$write_hostmap = false;
        $subsite1ID = $this->objFromFixture(Subsite::class, 'domaintest1')->ID;

        // Open the admin area logged in as admin
        $response1 = Director::test('admin/subsites/', null, $this->adminLoggedInSession());

        // Confirm that this URL gets you the entire page, with the edit form loaded
        $response2 = Director::test("admin/subsites/AirNZ-SimpleSubsites-Model-Subsite/EditForm/field/AirNZ-SimpleSubsites-Model-Subsite/item/$subsite1ID/edit", null, $this->adminLoggedInSession());
        $this->assertTrue(strpos($response2->getBody(), 'id="Form_ItemEditForm_ID"') !== false, "Testing Form_ItemEditForm_ID exists");
        $this->assertTrue(strpos($response2->getBody(), '<head') !== false, "Testing <head> exists");
    }


    /**
     * Test that the main-site user with ADMIN permissions can access all subsites, regardless
     * of whether he is in a subsite-specific group or not.
     */
    public function testMainsiteAdminCanAccessAllSubsites()
    {
        $member = $this->objFromFixture(Member::class, 'admin');
        $this->logInAs($member->ID);

        $cmsMain = new CMSMain();
        $ids = [];
        foreach (Subsite::all_accessible_sites() as $subsite) {
            $ids[$subsite->ID] = true;
        }

        $this->assertArrayHasKey($this->idFromFixture(Subsite::class, 'main'), $ids, "Site with no groups inaccesible");
        $this->assertArrayHasKey($this->idFromFixture(Subsite::class, 'subsite1'), $ids, "Subsite1 Template inaccessible");
        $this->assertArrayHasKey($this->idFromFixture(Subsite::class, 'subsite2'), $ids, "Subsite2 Template inaccessible");
    }
}
