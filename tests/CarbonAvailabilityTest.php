<?php

namespace Tests;

use Carbon\CarbonPeriod;
use Braid\CarbonAvailability;
use PHPUnit\Framework\TestCase;

class CarbonAvailabilityTest extends TestCase
{
    public function testBackToBackPeriodsMerge()
    {
        $periods = CarbonAvailability::mergePeriods([
            CarbonPeriod::create('2019-01-01 00:00:00', '2019-01-01 01:00:00'),
            CarbonPeriod::create('2019-01-01 01:00:00', '2019-01-01 02:00:00')
        ]);
        $this->assertCount(1, $periods);
        $this->assertEquals([
            $periods[0]->getStartDate()->toDateTimeString(),
            $periods[0]->getEndDate()->toDateTimeString()
        ], ['2019-01-01 00:00:00', '2019-01-01 02:00:00']);
    }
}
