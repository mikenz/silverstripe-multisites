<?php

use SilverStripe\Security\Group;
use SilverStripe\Forms\FieldList;

class GroupSubsitesTest extends BaseSubsiteTest
{
    public static $fixture_file = 'simplesubsites/tests/SubsiteTest.yml';

    protected $requireDefaultRecordsFrom = array('GroupSubsites');

    public function setUp()
    {
        parent::setUp();
        // parent::setUp disables subsite filter by default to not impact other module's tests
        Subsite::disable_subsite_filter(false);
    }

    public function testTrivialFeatures()
    {
        $this->assertTrue(is_array(singleton('GroupSubsites')->extraStatics()));
        $this->assertTrue(is_array(singleton('GroupSubsites')->providePermissions()));
        $this->assertTrue(singleton(Group::class)->getCMSFields() instanceof FieldList);
    }

    public function testAlternateTreeTitle()
    {
        $group = new Group();
        $group->Title = 'The A Team';
        $group->AccessAllSubsites = true;
        $this->assertEquals($group->getTreeTitle(), 'The A Team <i>(global group)</i>');
        $group->AccessAllSubsites = false;
        $group->write();
        $group->Subsites()->add($this->objFromFixture('Subsite', 'main'));
        $group->Subsites()->add($this->objFromFixture('Subsite', 'domaintest1'));
        $group->Subsites()->add($this->objFromFixture('Subsite', 'domaintest2'));
        $this->assertEquals($group->getTreeTitle(), 'The A Team <i>(Template, Test 1, Test 2)</i>');
    }
}
