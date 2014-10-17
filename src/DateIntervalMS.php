<?php

/**
 * DateInterval with (partial) support for microseconds
 *
 * @author Roel Arents <r.arents@bva-auctions.com>
 */
class DateIntervalMS extends DateInterval
{
    /**
     * There are this many microseconds in one second.
     * @var int
     */
    const MS_IN_S = 1000000;
    
    /**
     * Number of microseconds
     * @var int 
     */
    public $u = 0;
    
    /**
     * A regular expression for parsing an interval specification.
     * @var string
     */
    protected static $intervalSpecRegex = "/
        ^ ## start of the string
        P ## first character must be a P
        (?:(?P<y>\d+)Y)? ## year
        (?:(?P<m>\d+)M)? ## month
        (?:(?P<d>\d+)D)? ## day
        (?:T ## T delineates between day and time information
            (?:(?P<h>\d+)H)? ## hour
            (?:(?P<i>\d+)M)? ## minute
            (?:(?P<s>\d+(?:\.\d+)?)S)? ## seconds as float.
            )? ## closes 'T' subexpression
        $ ## end of the string
        /x";
    
    /**
     * @todo Implement support for microseconds.
     */
    public static function createFromDateString($input)
    {
        return parent::createFromDateString($input);
    }
    
    /**
     * @param string $format Like {@see format} but 'u' and 'U' are also supported.
     *  U   Microseconds, zero-padded until six digits.
     *  u   Microseconds, numeric
     *  E.g.: '%s.%U' prints '8.01234'
     *        '%u μs' prints '12340 μs'
     */
    public function format($format)
    {
        // Substitute microseconds.
        $format = str_replace('%U', sprintf("%06d", $this->u), $format);
        $format = str_replace('%u', sprintf("%d", intval($this->u)), $format);
        
        // Let parent do the rest.
        return parent::format($format);
    }
    
    /**
     * Converts cq casts a DateInterval to self
     * @param DateInterval $interval
     * @return DateIntervalMS
     */
    public static function castIntervalToMS(DateInterval $interval)
    {
        $intervalMs = new DateIntervalMS("PT0S");
        
        // Copy all properties.
        foreach ($foo = get_object_vars($interval) as $prop => $val) {
            $intervalMs->$prop = $val;
        }
        
        return $intervalMs;
    }
    
    /**
     * Converts microseconds to seconds.
     * @param int $microseconds
     * @return float Seconds (precise to 6 decimals)
     */
    public static function microsecondsToSeconds($microseconds)
    {
        $microseconds = intval($microseconds);
        $seconds = round($microseconds / static::MS_IN_S, 6);
        return $seconds;
    }
    
    /**
     * Converts seconds to microseconds.
     * @param float $seconds
     * @return int Microseconds
     */
    public static function secondsToMicroseconds($seconds)
    {
        $seconds = round($seconds, 6);
        $microseconds = intval($seconds * static::MS_IN_S);
        return $microseconds;
    }
    
    /**
     * @param string $intervalSpec Like {@see __construct) but seconds can have
     *  a decimal separator to indicate microseconds. E.g.: PT8.01234S
     */
    public function __construct($intervalSpec)
    {
        // Check input for validity and extract the date/time parts.
        if (! \preg_match(static::$intervalSpecRegex, $intervalSpec, $parts)) {
            throw new UnexpectedValueException(sprintf("%s::%s: Unknown or bad format (%s)", get_called_class(), '__construct', $intervalSpec));
        }
        
        // Get microseconds from spec.
        if (isset($parts['s'])) {
            $preciseSeconds = floatval($parts['s']);
            $microseconds = static::secondsToMicroseconds(fmod($preciseSeconds, 1.0));
            $seconds = floor($preciseSeconds);
            $this->u = $microseconds;
            $parts['s'] = $seconds;
        }
        
        // Rebuild the interval spec without microseconds.
        $legacySpec = static::getLegacySpec($parts);
        
        // Let parent do the rest of the parsing.
        parent::__construct($legacySpec);
    }
    
    /**
     * Creates an interval specification in legacy format (without microseconds)
     *  from a parsed interval spec.
     * @param Map<string, string> $parts A parsed interval spec.
     */
    private static function getLegacySpec(array $parts)
    {
        $spec = "P";
        $spec .= $parts['y'] !== "" ? "{$parts['y']}Y" : "";
        $spec .= $parts['m'] !== "" ? "{$parts['m']}M" : "";
        $spec .= $parts['d'] !== "" ? "{$parts['d']}D" : "";
        if ($parts['h']. $parts['i'] . $parts['s'] !== "") {
            $spec .= "T";
            $spec .= $parts['h'] !== "" ? "{$parts['h']}H" : "";
            $spec .= $parts['i'] !== "" ? "{$parts['i']}M" : "";
            $spec .= $parts['s'] !== "" ? "{$parts['s']}S" : "";
        }
        if ($spec === "P") {
            $spec = "";
        }
        
        return $spec;
    }
    
    public static function __set_state(array $state)
    {
        $intv = new static();
        
        foreach ($state as $key => $val)
        {
            if (property_exists($intv, $key)) {
                $intv->$key = $val;
            }
        }
    }
}
