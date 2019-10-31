# CarbonAvailability

Given some available times and some unavailable times how do you determine what
time slots you can schedule an event of a certain duration (think something like
[calendly](https://calendly.com/))? Well – it's surprisingly hard – unless
you're using this.

```php

use Braid\CarbonAvailability;

$availability = [
    ['2019-01-01 09:00:00', '2019-01-01 10:00:00'],
    ['2019-01-01 10:15:00', '2019-01-01 11:00:00'],
    ['2019-01-01 11:00:00', '2019-01-01 12:00:00']
];

$booked = [
    ['2019-01-01 10:45:00', '2019-01-01 11:30:00'],
    ['2019-01-01 11:50:00', '2019-01-01 11:55:00']
];

$availability = new CarbonAvailability($availability, $booked);
$startTimes = $availability->session('15 minutes');
/* Returns the following Carbon\Carbon date times (2019-01-01):
9:00
9:15
9:45
10:15
10:30
11:30
*/
```
