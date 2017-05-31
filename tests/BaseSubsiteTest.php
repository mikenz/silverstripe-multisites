<?php

use SilverStripe\Dev\SapphireTest;

class BaseSubsiteTest extends SapphireTest
{

    public static $fixture_file = 'simplesubsites/tests/BaseSubsiteTest.yml';

    public function setUp()
    {
        parent::setUp();

        Subsite::disable_subsite_filter(false);
        Subsite::$use_session_subsiteid = true;
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

    /**
     * Tests the initial state of disable_subsite_filter
     */
    public function testDisableSubsiteFilter()
    {
        $this->assertFalse(Subsite::$disable_subsite_filter);
    }
}
