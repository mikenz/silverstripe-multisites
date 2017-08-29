<?php

namespace AirNZ\SimpleSubsites\Tests;

use SilverStripe\Security\Group;
use SilverStripe\Forms\FieldList;
use AirNZ\SimpleSubsites\Extensions\GroupExtension;
use AirNZ\SimpleSubsites\Model\Subsite;

class GroupSubsitesTest extends BaseSubsiteTest
{
    public static $fixture_file = 'simplesubsites/tests/SubsiteTest.yml';

    protected $requireDefaultRecordsFrom = array(GroupExtension::class);

    public function setUp()
    {
        parent::setUp();
        // parent::setUp disables subsite filter by default to not impact other module's tests
        Subsite::disable_subsite_filter(false);
    }

    public function testTrivialFeatures()
    {
        $this->assertTrue(is_array(singleton(GroupExtension::class)->extraStatics()));
        $this->assertTrue(is_array(singleton(GroupExtension::class)->providePermissions()));
        $this->assertTrue(singleton(Group::class)->getCMSFields() instanceof FieldList);
    }

    public function testAlternateTreeTitle()
    {
        $group = new Group();
        $group->Title = 'The A Team';
        $group->AccessAllSubsites = true;
        $this->assertEquals('The A Team <i>(global group)</i>', $group->getTreeTitle());
        $group->AccessAllSubsites = false;
        $group->write();
        $group->Subsites()->add($this->objFromFixture(Subsite::class, 'main'));
        $group->Subsites()->add($this->objFromFixture(Subsite::class, 'domaintest1'));
        $group->Subsites()->add($this->objFromFixture(Subsite::class, 'domaintest2'));
        $this->assertEquals('The A Team <i>(Template, Test 1, Test 2)</i>', $group->getTreeTitle());
    }
}
