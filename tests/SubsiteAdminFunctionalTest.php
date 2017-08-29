<?php

namespace AirNZ\SimpleSubsites\Tests;

use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\Member;
use AirNZ\SimpleSubsites\Model\Subsite;
use AirNZ\SimpleSubsites\Tests\SubsiteTest_Page;

class SubsiteAdminFunctionalTest extends FunctionalTest
{
    public static $fixture_file = 'simplesubsites/tests/SubsiteTest.yml';
    public static $use_draft_site = true;

    protected $autoFollowRedirection = false;

    public function setUp()
    {
        parent::setUp();
        // parent::setUp disables subsite filter by default to not impact other module's tests
        Subsite::disable_subsite_filter(false);
    }

    /**
     * Helper: FunctionalTest is only able to follow redirection once, we want to go all the way.
     */
    public function getAndFollowAll($url)
    {
        $response = $this->get($url);
        while ($location = $response->getHeader('Location')) {
            $response = $this->mainSession->followRedirection();
        }
        echo $response->getHeader('Location');

        return $response;
    }

    /**
     * Anonymous user cannot access anything.
     */
    public function testAnonymousIsForbiddenAdminAccess()
    {
        $response = $this->getAndFollowAll('admin/pages/?SubsiteID=0');
        $this->assertRegExp('#^Security/login.*#', $this->mainSession->lastUrl(), 'Admin is disallowed');

        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
        $response = $this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
        $this->assertRegExp('#^Security/login.*#', $this->mainSession->lastUrl(), 'Admin is disallowed');

        $response = $this->getAndFollowAll('SubsiteXHRController');
        $this->assertRegExp(
            '#^Security/login.*#',
            $this->mainSession->lastUrl(),
            'SubsiteXHRController is disallowed'
        );
    }

    /**
     * Admin should be able to access all subsites and the main site
     */
    public function testAdminCanAccessAllSubsites()
    {
        $member = $this->objFromFixture(Member::class, 'admin');
        $this->logInAs($member->ID);

        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
        $this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
        $this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Can access other subsite.');
        $this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Lands on the correct section');

        $response = $this->getAndFollowAll('SubsiteXHRController');
        $this->assertNotRegExp(
            '#^Security/login.*#',
            $this->mainSession->lastUrl(),
            'SubsiteXHRController is reachable'
        );
    }

    public function testAdminIsRedirectedToObjectsSubsite()
    {
        $member = $this->objFromFixture(Member::class, 'admin');
        $this->logInAs($member->ID);

        $subsite1Home = $this->objFromFixture(SubsiteTest_Page::class, 'subsite1_home');
        $subsite2 = $this->objFromFixture(Subsite::class, 'subsite2');

        Subsite::changeSubsite($subsite2->ID);
        $this->getAndFollowAll("admin/pages/edit/show/$subsite1Home->ID");
        $this->assertEquals(Subsite::currentSubsiteID(), $subsite1Home->SubsiteID, 'Loading an object switches the subsite');
        $this->assertRegExp("#^admin/pages.*#", $this->mainSession->lastUrl(), 'Lands on the correct section');
    }

    /**
     * User which has AccessAllSubsites set to 1 should be able to access all subsites and main site,
     * even though he does not have the ADMIN permission.
     */
    public function testEditorCanAccessAllSubsites()
    {
        $member = $this->objFromFixture(Member::class, 'editor');
        $this->logInAs($member->ID);

        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');
        $this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
        $this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Can access other subsite.');
        $this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Lands on the correct section');

        $response = $this->getAndFollowAll('SubsiteXHRController');
        $this->assertNotRegExp(
            '#^Security/login.*#',
            $this->mainSession->lastUrl(),
            'SubsiteXHRController is reachable'
        );
    }

    /**
     * Test a member who only has access to one subsite (subsite1) and only some sections (pages and security).
     */
    public function testSubsiteAdmin()
    {
        $member = $this->objFromFixture(Member::class, 'subsite1member');
        $this->logInAs($member->ID);

        $subsite1 = $this->objFromFixture(Subsite::class, 'subsite1');

        // Check allowed URL.
        $this->getAndFollowAll("admin/pages/?SubsiteID={$subsite1->ID}");
        $this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Can access own subsite.');
        $this->assertRegExp('#^admin/pages.*#', $this->mainSession->lastUrl(), 'Can access permitted section.');

        // Check forbidden section in allowed subsite.
        $this->getAndFollowAll("admin/assets/?SubsiteID={$subsite1->ID}");
        $this->assertEquals(Subsite::currentSubsiteID(), $subsite1->ID, 'Is redirected within subsite.');
        $this->assertNotRegExp(
            '#^admin/assets/.*#',
            $this->mainSession->lastUrl(),
            'Is redirected away from forbidden section'
        );

        // Check the standalone XHR controller.
        $response = $this->getAndFollowAll('SubsiteXHRController');
        $this->assertNotRegExp(
            '#^Security/login.*#',
            $this->mainSession->lastUrl(),
            'SubsiteXHRController is reachable'
        );
    }
}
