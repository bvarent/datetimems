<?php

class DateTimeMSTest extends PHPUnit_Framework_TestCase
{
    /**
     * Default formatting string to test against.
     * @var string
     */
    protected static $defaultFormat = "Y-m-d H:i:s.u";

    public function setUp()
    {
        // Set a temporary default timezone, if missing.
        // To prevent errors about this.
        if (empty(date_default_timezone_get())) {
            date_default_timezone_set('UTC');
        }
    }
    
    /**
     * Tests if a DateTime can be constructed
     *  and if microseconds are parsed.
     * @covers DateTimeMS::__construct
     * @covers DateTimeMS::format
     */
    public function testConstruct()
    {
        $str = '2014-10-09 09:17:50.34';
        $dt = new DateTimeMS($str);
        $this->assertStringStartsWith($str, $dt->format(static::$defaultFormat));
    }
    
    /**
     * @return array Array of arrays with two date strings and their difference.
     */
    public function provideDiffDates()
    {
        $intv1 = new DateIntervalMS("PT0.2S");
        $intv1->invert = true;
        $intv3 = new DateIntervalMS("PT0.6S");
        $intv3->invert = true;
        
        return array(
            array('12:00:00.1', '11:59:59.9', $intv1),
            array('11:59:59.9', '12:00:00.1', new DateIntervalMS("PT0.2S")),
            array('12:00:00.8', '12:00:00.2', $intv3),
            array('12:00:00.2', '12:00:00.8', new DateIntervalMS("PT0.6S")),
            array('10:00:00.4', '12:00:00.399999', new DateIntervalMS("PT1H59M59.999999S")),
            array('2014-10-10 10:00:00.0', '2014-10-11 09:59:59.999999', new DateIntervalMS("PT23H59M59.999999S")),
            array('2014-10-10 11:00:00.0', '2014-10-11 11:00:00.999999', new DateIntervalMS("P1DT0.999999S"))
        );
    }
    
    /**
     * Tests if the difference between two dates is correctly calculated.
     * @covers DateTimeMS::diff
     * @covers DateTimeMS::__construct
     * @dataProvider provideDiffDates
     * @param string $strA Date string A.
     * @param string $strB Date string B.
     * @param DateIntervalMS $answer The interval that should be the result of the operation.
     */
    public function testDiff($strA, $strB, DateIntervalMS $answer)
    {
        $dtA = new DateTimeMS($strA);
        $dtB = new DateTimeMS($strB);
        $diff = $dtA->diff($dtB);
        $this->assertEquals($answer, $diff);
    }
    
    /**
     * @return array Array of arrays with a date string, a modify string
     *  and their difference.
     */
    public function provideModifiers()
    {
        return array(
            array('2014-10-09 09:17:50.34',
                "+1 day previous microsecond",
                '2014-10-10 09:17:50.339999'),
            array('2014-10-09 09:17:50.500000',
                "-3 microseconds tomorrow next microsecond",
                '2014-10-09 23:59:59.999998'),
        );
    }
    
    /**
     * Test if modify handles microseconds.
     * @covers DateTimeMS::modify
     * @dataProvider provideModifiers
     * @param string $date The date(string) to modify.
     * @param string $modify The modification to apply.
     * @param string $answer The formatted date that should be the result.
     */
    public function testModify($date, $modify, $answer)
    {
        $dt = new DateTimeMS($date);
        $dt->modify($modify);
        $this->assertEquals($answer,
                $dt->format(static::$defaultFormat));
    }
    
    /**
     * Test if add method works correctly.
     * @covers DateTimeMS::add
     * @dataProvider provideDiffDates
     * @param string $strSubject string The date(string) to modify.
     * @param string $strAnswer The date(string) that should be the result.
     * @param DateInterval $interval The interval to apply.
     */
    public function testAdd($strSubject, $strAnswer, DateInterval $interval)
    {
        $dtSubject = new DateTimeMS($strSubject);
        $dtAnswer = new DateTimeMS($strAnswer);
        $dtSubject->add($interval);
        $this->assertEquals($dtAnswer->format(static::$defaultFormat), 
                $dtSubject->format(static::$defaultFormat));
    }
    
    /**
     * Test if sub(tract) method works correctly.
     * @depends testAdd
     * @covers DateTimeMS::sub
     * @dataProvider provideDiffDates
     * @param string $strSubject string The date(string) to modify.
     * @param string $strAnswer The date(string) that should be the result.
     * @param DateInterval $interval The interval to apply.
     */
    public function testSub($strAnswer, $strSubject, DateInterval $interval)
    {
        $dtSubject = new DateTimeMS($strSubject);
        $dtAnswer = new DateTimeMS($strAnswer);
        $dtSubject->sub($interval);
        $this->assertEquals($dtAnswer->format(static::$defaultFormat), 
                $dtSubject->format(static::$defaultFormat));
    }
}
