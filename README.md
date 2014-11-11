DateTimeMS
==========
This small library enables you to make time calculations with microseconds
precisions. PHP's built-in DateTime and DateInterval classes will not do that.
Except that DateTime does keep microseconds, but will ignore those in its
calculations.

Usage
-----
Require this library via [composer](https://getcomposer.org).

Use in the same way as you would DateTime. The classes extend DateTime, so you
can substitute your legacy classes where needed.

Example:
``` php
$dtToday = new \DateTimeMS();
$dtTomorrow = clone $dtToday;
$dtTomorrow->modify("+1 day -1 microsecond");
$interval = $dtToday->diff($dtTomorrow);
print "In between {$dtToday->format('D, H:i:s.u')} and the same second tomorrow
    are $interval->format('%d days, %h hrs, %i mins, %secs and %u microsecs')."
```

Warnings
--------
*  The comparison operators (< > = etc) do not account for microseconds. (Which
   is impossible to achieve due to nonexistence of PHP operator overloading.)
*  DateTimeMS::modify cannot be used to set microseconds explicitly.
*  DateIntervalMS::modify is not implemented.

ToDo
----
*  Improve DateTimeMS::modify
*  Implement DateIntervalMS::modify
*  Expand the unit tests.
*  Test and think about performance.