<?php

/**
 * DateTime with (partial) support for microseconds.
 * The original DateTime already supports microseconds a bit, but does not 
 *  calculate with it.
 *
 * @todo Comparison operators (< > = etc) should account for microseconds.
 * @todo Test for errors on constructing DateTime. (DateTime::getLastErrors)
 * @author Roel Arents <r.arents@bva-auctions.com>
 */
class DateTimeMS extends DateTime
{
    /**
     * The default format to use for __toString.
     * @var string
     */
    protected static $defaultFormat = DateTime::ISO8601;
    
    /**
     * Number of microseconds / 1,000,000
     * @var float
     */
    protected $microInSeconds = 0.0;
    
    /**
     * Ordinal strings that are recognized in a relative date string.
     */
    protected static $ordinalSymbols = array(
        'first' => 1,
        'second' => 2,
        'third' => 3,
        'fourth' => 4,
        'fifth' => 5,
        'sixth' => 6,
        'seventh' => 7,
        'eighth' => 8,
        'ninth' => 9,
        'tenth' => 10,
        'eleventh' => 11,
        'twelfth' => 12,
        'next' => 1,
        'last' => -1,
        'previous' => -1,
        'this' => 0
    );
    
    /**
     * Some keywords reset the time of a DateTime if used in a relative date string.
     * @var List<string> 
     */
    protected static $timeResettingKeywords = array(
        'yesterday',
        'midnight',
        'today',
        'noon',
        'tomorrow'
    );

    /**
     * @param DateInterval $interval
     */
    public function add($interval)
    {
        // Handle the microseconds of a microseconds aware DateInterval.
        if ($interval instanceof DateIntervalMS) {
            $microsecondsDiff = intval($interval->u);
            if ($interval->invert) {
                $microsecondsDiff *= -1;
            }
            $this->modMicroseconds($microsecondsDiff);
        }
        
        // Let parent do the rest.
        return parent::add($interval);
    }
    
    /**
     * Sets the microseconds to the specified value.
     * @param int $microseconds
     * @return self chain
     */
    public function setMicroseconds($microseconds)
    {
        $this->microInSeconds = DateIntervalMS::microsecondsToSeconds($microseconds);
        
        return $this;
    }
    
    /**
     * Adds (or subtracts) microseconds.
     * @param int $microseconds Number of microseconds to add (or subtract if negative).
     * @return self chain
     */
    protected function modMicroseconds($microseconds)
    {
        if ($microseconds == 0) {
            return $this;
        }
        
        // Express the input as seconds instead of microseconds.
        $microInSeconds = DateIntervalMS::microsecondsToSeconds($microseconds);
        
        // Calculate the new number of microseconds, which might be more than 1 second.
        $newMicroInSeconds = $this->microInSeconds + $microInSeconds;
        $secondsDiff = 0;
        
        // Borrow or give a second if the new microseconds is out of bounds [0,1)
        if ($newMicroInSeconds < 0) {
            $borrow = ceil(0 - $newMicroInSeconds);
            $newMicroInSeconds += $borrow;
            $secondsDiff -= $borrow;
        }
        elseif($newMicroInSeconds >= 1)
        {
            $overflow = floor(2-$newMicroInSeconds);
            $newMicroInSeconds -= $overflow;
            $secondsDiff += $overflow;
        }
        
        // Set the microseconds to the new value and process the whole seconds.
        parent::modify(sprintf("%+d seconds", $secondsDiff));
        $this->microInSeconds = $newMicroInSeconds;
        
        return $this;
    }
    
    /**
     * @param \DateTimeZone $timezone
     */
    public static function createFromFormat($format, $time, $timezone = null)
    {
        if (is_null($timezone)) {
            $timezone = new \DateTimeZone(date_default_timezone_get());
        }
        
        // Legacy DateTime does parse microseconds. Extract these.
        $datetime = DateTime::createFromFormat($format, $time, $timezone);
        $microseconds = $datetime->format('u');
        
        // Inject microseconds into DateTimeMS.
        $datetimems = legacyDateTimeToMS($datetime);
        unset($datetime); // Discard old object.
        /* @var $datetimems DateTimeMS */
        $datetimems->setMicroseconds($microseconds);
        
        return $datetimems;
    }
    
    /**
     * @param DateTimeInterface $datetime
     * @return DateIntervalMS
     */
    public function diff($datetime, $absolute = false)
    {
        $dateA = $this;
        $dateB = clone $datetime;
        
        $msA = DateIntervalMS::secondsToMicroseconds($dateA->microInSeconds);
        $msB = intval($dateB->format('u'));
        
        $bLessThanA = ($this->compare($dateB) > 0);
        
        // Start a 'long subtraction' of the microseconds to calculate the overflow into B's seconds.
        $overflow = 0;
        // If B is smaller than A, swap.
        if ($bLessThanA) {
            list($msB, $msA) = array($msA, $msB);
        }
        $msDiff = $msB - $msA;
        if ($msDiff < 0)
        {
            // Borrow from A or B (swapped?). I.a.w. give or take a second from B.
            $overflow = $bLessThanA ? +1 : -1;
            $msDiff += DateIntervalMS::MS_IN_S;
        }
        
        // Instead of modifying DateInterval with the borrow, 
        //  modify dateA and recalculate legacy difference.
        //  (Because DateInterval has no modify function but DateTime does.)
        if ($overflow) {
            $dateB->modify(sprintf('%+d seconds', $overflow));
        }
        
        // Legacy DateTime will finish the long subtraction.
        $legacyDiff = parent::diff($dateB);
        
        // Convert legacy diff to self
        $diff = DateIntervalMS::castIntervalToMS($legacyDiff);
        unset($legacyDiff);
        
        // Set the negative sign if B and A were swapped.
        $diff->invert = $bLessThanA;
        
        // Inject microseconds into the interval.
        $diff->u = $msDiff;
        
        // Set positive sign if an absolute value was required.
        if ($absolute) {
            $diff->invert = false;
        }
        
        return $diff;
    }
    
    /**
     * Converts cq casts a DateTime to self.
     * @param DateTime $datetime
     * @return DateTimeMS
     */
    public static function castDateTimeToMS(DateTime $datetime)
    {
        $datetimeMs = new self();
        
        // Copy all properties.
        $datetimeMs->date = $datetime->date;
        $datetimeMs->timezone_type = $datetime->timezone_type;
        $datetimeMs->timezone = $datetime->timezone;
        $datetimeMs->microInSeconds = DateIntervalMS::microsecondsToSeconds($datetime->format('u'));
        
        return $datetimeMs;
    }
    
    /**
     * Checks whether the input is greater, smaller or the same.
     * @param DateTimeInterface $datetime Input date.
     * @return int Equal: 0, greater: -1, smaller: 1.
     */
    public function compare($datetime)
    {
        $dateA = $this;
        $dateB = $datetime;
        
        // Legacy DateTime already performs a check on the seconds level.
        // A further inspection is necessary on microseconds if the dates are equal.
        switch (true) {
            case ($dateA < $dateB):
                return -1;
            case ($dateA > $dateB):
                return 1;
            default:
                $msA = DateIntervalMS::secondsToMicroseconds($dateA->microInSeconds);
                $msB = floatval($dateB->format('u'));
                switch (true) {
                    case ($msA < $msB):
                        return -1;
                    case ($msA > $msB):
                        return 1;
                    default:
                        return 0;
                }
        }
    }
    
    public function format($format)
    {
        // Substitute 'u' with our own microseconds.
        $microseconds = DateIntervalMS::secondsToMicroseconds($this->microInSeconds);
        $format = str_replace('u', sprintf('%06d', $microseconds), $format);
        
        // Let parent do the rest.
        return parent::format($format);
    }
    
    /**
     * @param boolean $microseconds Include microseconds? (Will return a float.)
     * @return int|float Number of seconds since Unix epoch.
     */
    public function getTimestamp($microseconds = false)
    {
        $timestamp = parent::getTimestamp();
        if ($microseconds) {
            $timestamp += $this->microInSeconds;
        }
        return $timestamp;
    }
    
    /**
     * @todo Recognize fractions cq microseconds in absolute time formats.
     */
    public function modify($modify)
    {
        // Extract relative microseconds operations
        $operationsMs = static::extractRelativeMicrosecondsOperations($modify);
        
        // Let parent handle Date, Time and Compound formats. Relative micro-
        //  seconds operations are not supported by parent.
        parent::modify($modify);
        
        // Set the microseconds to 0 if a resetting keyword was found.
        $keywordsRegex = "/(?:^|[ \t]+)(?:" . implode('|', static::$timeResettingKeywords) . ")(?:$|[ \t])/";
        if (preg_match($keywordsRegex, $modify)) {
            $this->microInSeconds = 0.0;
        }
        
        // Execute the relative microseconds operations.
        $diffMs = static::sumRelativeOperations($operationsMs);
        $this->modMicroseconds($diffMs);
        
        return $this;
    }
    
    /**
     * Extracts (and removes) the relative operations on microseconds from a
     *  datetime string.
     * @param string $datetimeString This string will have some operations removed.
     * @return List<string>
     * @link http://php.net/manual/en/datetime.formats.relative.php
     */
    protected static function extractRelativeMicrosecondsOperations( &$datetimeString)
    {
        // Get all operations.
        $operations = array();
        $datetimeString = preg_replace_callback(static::getRelativeMicrosecondsOperationsRegex(), 
            function ($matches) use (&$operations) {
                $operations[] = end($matches);
                return "";
            },
            $datetimeString
        );
        
        return $operations;
    }
    
    /**
     * The regex to match relative microseconds operations.
     *  Will be initialized by getRelativeMicrosecondsOperationsRegex.
     * @var string
     */
    protected static $relativeMicrosecondsOperationsRegex;
    
    /**
     * Gets (and builds the first time) the regex for recognizing relative 
     *  operation on microseconds.
     * @return string
     */
    public static function getRelativeMicrosecondsOperationsRegex()
    {
        if (!isset(static::$relativeMicrosecondsOperationsRegex)) {
            // Create a regex to match number|ordinal space microsecond(s).
            $number = '[+-]?[0-9]+';
            $space = '[ \t]+';
            $ordinal = implode('|', array_keys(static::$ordinalSymbols));
            $unit = 'microseconds?';
            $regex = "
                /
                (?:^|{$space})
                ({$ordinal}|{$number})
                (?:{$space}{$unit})
                (?:$|{$space})
                /xi";
            static::$relativeMicrosecondsOperationsRegex = $regex;
        }
        
        return static::$relativeMicrosecondsOperationsRegex;
    }
    
    /**
     * Calculates the sum of all operations.
     * E.g.: array('-3001', 'previous', 'twelfth')
     *  will result in -3001 + -1 + 12 = -2990
     * @param List<string> $operations
     * @return int
     */
    protected static function sumRelativeOperations(array $operations = array())
    {
        $diff = 0;
        foreach ($operations as $operation) {
            if (is_numeric($operation)) {
                $diff += $operation;
            }
            elseif (isset(static::$ordinalSymbols[$operation])) {
                $diff += static::$ordinalSymbols[$operation];
            }
        }
        
        return $diff;
    }
    
    /**
     * @param int $microseconds
     */
    public function setTime($hours, $minutes, $seconds = 0, $microseconds = 0)
    {
        $this->microInSeconds = DateIntervalMS::microsecondsToSeconds($microseconds);
        return parent::setTime($hours, $minutes, $seconds);
    }
    
    /**
     * @param float $unixtimestamp Number of seconds since Unix epoch.
     *  Partial seconds count as microseconds.
     *  E.g.: setTimeStamp(microtime(true));
     */
    public function setTimestamp($unixtimestamp)
    {
        // Split into seconds and microseconds.
        $microseconds = fmod($unixtimestamp, 1.0);
        $seconds = intval($unixtimestamp);
        
        // Set microseconds and let parent do the rest.
        parent::setTimestamp($seconds);
        $this->microInSeconds = $microseconds;
        
        return $this;
    }
    
    /**
     * @param DateInterval $interval
     */
    public function sub($interval)
    {
        $invInterval = clone $interval;
        $invInterval->invert = !$interval->invert;
        return $this->add($invInterval);
    }
    
    public function __construct($time = 'now', DateTimeZone $timezone = null)
    {
        parent::__construct($time, $timezone);
        
        // Legacy DateTime does parse microseconds. Extract these.
        $microseconds = parent::format('u');
        
        // Inject microseconds into DateTimeMS.
        $this->setMicroseconds($microseconds);
    }
    
    public static function __set_state(array $state)
    {
        $dt = new static($state['date']);
        $tz = new \DateTimeZone($state['timezone']);
        $dt->setTimezone($tz);
        $dt->microInSeconds = $state['microInSeconds'];
        
        return $dt;
    }
    
    /**
     * Format the date and time as a string, per the default format.
     * @return string
     */
    public function __toString()
    {
        return $this->format(static::$defaultFormat);
    }
    
    /**
     * preg_replace_callback() with offset capturing.
     *
     * Usage:
     *   Works exactly like preg_replace_callback() but differs in passing the matches to the callback function.
     *   Matches are as if using preg_match() with the PREG_OFFSET_CAPTURE flag:
     *
     *     $match[$captureGroup] = array(0 => (string) $matchedString, 1 => (int) $subjectStringOffset)
     *
     * @author hakre <http://hakre.wordpress.com>
     * @copyright Copyright (c) 2013 by hakre
     * @revision 4
     *
     * @link http://php.net/preg_replace_callback
     * @link http://php.net/preg_match
     *
     * @param string $pattern
     * @param callable $callback
     * @param string|array $subject
     * @param int $limit (optional)
     * @param int $count (optional)
     *
     * @return array|bool|mixed|string
     */
    public static function preg_replace_callback_offset($pattern, $callback, $subject, $limit = -1, &$count = 0)
    {
        if (is_array($subject)) {
            foreach ($subject as &$subSubject) {
                $subSubject = static::preg_replace_callback_offset($pattern, $callback, $subSubject, $limit, $subCount);
                $count += $subCount;
            }

            return $subject;
        }

        if (is_array($pattern)) {
            foreach ($pattern as $subPattern) {
                $subject = static::preg_replace_callback_offset($subPattern, $callback, $subject, $limit, $subCount);
                $count += $subCount;
            }

            return $subject;
        }

        $limit = max(-1, (int) $limit);
        $count = 0;
        $offset = 0;
        $buffer = (string) $subject;

        while ($limit === -1 || $count < $limit) {
            $result = preg_match($pattern, $buffer, $matches, PREG_OFFSET_CAPTURE, $offset);
            if (FALSE === $result)
                return FALSE;
            if (!$result)
                break;

            $pos = $matches[0][1];
            $len = strlen($matches[0][0]);
            $replace = call_user_func($callback, $matches);

            $buffer = substr_replace($buffer, $replace, $pos, $len);

            $offset = $pos + strlen($replace);

            $count++;
        }

        return $buffer;
    }

}
