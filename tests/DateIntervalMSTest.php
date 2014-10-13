<?php

class DateIntervalMSTest extends PHPUnit_Framework_TestCase
{
    /**
     * Provides interval specifications to construct DateIntervalMS from.
     */
    public function provideIntervalspecs()
    {
        return array(
            array("PT59.9S")
        );
    }
    
    /**
     * Tests if a DateIntervalMS is constructed without errors.
     * @covers DateInterval::__construct
     * @dataProvider provideIntervalspecs
     * @param string $intervalSpec Interval specification
     */
    public function testConstruct($intervalSpec)
    {
        $interval = new DateIntervalMS($intervalSpec);
        $this->assertInstanceOf('DateIntervalMS', $interval);
    }
}
