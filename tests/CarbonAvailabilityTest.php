<?php

namespace Tests;

use Carbon\CarbonPeriod;
use Braid\CarbonAvailability;
use PHPUnit\Framework\TestCase;

class CarbonAvailabilityTest extends TestCase
{
    /**
     * Test that sequential dates merge together.
     * [=========]
     *            [=========]
     * [====================] // desired result
     */
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

    /**
     * Test that slightly offset dates don't merge together.
     * [=========]
     *             [==========]
     * [=========] [==========] // desired result
     */
    public function testDisconnectedPeriodsDontMerge()
    {
        $periods = CarbonAvailability::mergePeriods([
            CarbonPeriod::create('2019-01-01 00:00:00', '2019-01-01 00:59:00'),
            CarbonPeriod::create('2019-01-01 01:00:00', '2019-01-01 02:00:00')
        ]);
        $this->assertCount(2, $periods);
        $this->assertEquals([
            $periods[0]->getStartDate()->toDateTimeString(),
            $periods[0]->getEndDate()->toDateTimeString(),
            $periods[1]->getStartDate()->toDateTimeString(),
            $periods[1]->getEndDate()->toDateTimeString()
        ], ['2019-01-01 00:00:00', '2019-01-01 00:59:00', '2019-01-01 01:00:00', '2019-01-01 02:00:00']);
    }

    /**
     * Test that disconnected times get merged by a third time that spans both.
     * [=========]
     *             [=========]
     *         [======]
     * [=====================] // desired result
     */
    public function testDisconnectedTimesAreMerged()
    {
        $periods = CarbonAvailability::mergePeriods([
            CarbonPeriod::create('2019-01-01 00:00:00', '2019-01-01 00:59:00'),
            CarbonPeriod::create('2019-01-01 01:00:00', '2019-01-01 02:00:00'),
            CarbonPeriod::create('2019-01-01 00:50:00', '2019-01-01 01:20:00')
        ]);
        $this->assertCount(1, $periods);
        $this->assertEquals([
            $periods[0]->getStartDate()->toDateTimeString(),
            $periods[0]->getEndDate()->toDateTimeString(),
        ], ['2019-01-01 00:00:00', '2019-01-01 02:00:00']);
    }

    /**
     * Test that disconnected times get merged by a third time that spans both
     * while an outlier will stay preserved.
     * [=========]
     *             [=========]
     *         [======]
     *                             [===]
     * [=====================]     [===] // desired result
     */
    public function testDisconnectedTimesAreMergedWithOutliers()
    {
        $periods = CarbonAvailability::mergePeriods([
            CarbonPeriod::create('2019-01-01 00:00:00', '2019-01-01 00:59:00'),
            CarbonPeriod::create('2019-01-01 01:00:00', '2019-01-01 02:00:00'),
            CarbonPeriod::create('2019-01-01 00:50:00', '2019-01-01 01:20:00'),
            CarbonPeriod::create('2019-01-01 03:20:00', '2019-01-01 03:45:00')
        ]);
        $this->assertCount(2, $periods);
        $this->assertEquals([
            $periods[0]->getStartDate()->toDateTimeString(),
            $periods[0]->getEndDate()->toDateTimeString(),
            $periods[1]->getStartDate()->toDateTimeString(),
            $periods[1]->getEndDate()->toDateTimeString(),
        ], ['2019-01-01 00:00:00', '2019-01-01 02:00:00', '2019-01-01 03:20:00', '2019-01-01 03:45:00']);
    }

    /**
     * Test an availability totally encompassed by a larger period is removed.
     * [==================]
     *       [======]
     * [==================] // desired result
     */
    public function testInclusiveAvailabilitiesAreRemoved()
    {
        $periods = CarbonAvailability::mergePeriods([
            CarbonPeriod::create('2019-01-01 00:00:00', '2019-01-01 02:00:00'),
            CarbonPeriod::create('2019-01-01 00:45:00', '2019-01-01 01:15:00')
        ]);
        $this->assertCount(1, $periods);
        $this->assertEquals([
            $periods[0]->getStartDate()->toDateTimeString(),
            $periods[0]->getEndDate()->toDateTimeString(),
        ], ['2019-01-01 00:00:00', '2019-01-01 02:00:00']);
    }

    /**
     * Inverse of previous test.
     *       [======]
     * [==================]
     * [==================] // desired result
     */
    public function testInverseInclusiveAvailabilitiesAreRemoved()
    {
        $periods = CarbonAvailability::mergePeriods([
            CarbonPeriod::create('2019-01-01 00:45:00', '2019-01-01 01:15:00'),
            CarbonPeriod::create('2019-01-01 00:00:00', '2019-01-01 02:00:00')
        ]);
        $this->assertCount(1, $periods);
        $this->assertEquals([
            $periods[0]->getStartDate()->toDateTimeString(),
            $periods[0]->getEndDate()->toDateTimeString(),
        ], ['2019-01-01 00:00:00', '2019-01-01 02:00:00']);
    }

    /**
     * Given an unavailable time in the middle of an available block, ensure it
     * splits the block into two periods.
     * [=====================]
     *        [xxxxxx]
     * [=====]        [======] // desired result
     */
    public function testTimesGetSplitByUnavailability()
    {
        $availability = CarbonAvailability::create(
            [['2019-01-01 00:00:00', '2019-01-01 03:00:00']],
            [['2019-01-01 01:00:00', '2019-01-01 02:00:00']]
        );
        $periods = $availability->periods();
        $this->assertCount(2, $periods);
        $this->assertEquals([
            $periods[0]->getStartDate()->toDateTimeString(),
            $periods[0]->getEndDate()->toDateTimeString(),
            $periods[1]->getStartDate()->toDateTimeString(),
            $periods[1]->getEndDate()->toDateTimeString(),
        ], ['2019-01-01 00:00:00', '2019-01-01 01:00:00', '2019-01-01 02:00:00', '2019-01-01 03:00:00']);
    }

    /**
     * Given an unavailable time at the beginning of a block it should shorten
     * the total available time.
     *    [=====================]
     * [xxxxxx]
     *         [================] // desired result
     */
    public function testAvailabilityStartsLater()
    {
        $availability = CarbonAvailability::create(
            [['2019-01-01 10:00:00', '2019-01-01 12:00:00']],
            [['2019-01-01 09:45:00', '2019-01-01 10:30:00']]
        );
        $periods = $availability->periods();
        $this->assertCount(1, $periods);
        $this->assertEquals([
            $periods[0]->getStartDate()->toDateTimeString(),
            $periods[0]->getEndDate()->toDateTimeString(),
        ], ['2019-01-01 10:30:00', '2019-01-01 12:00:00']);
    }

    /**
     * Given an unavailable time at the end of a block it should shorten
     * the total available time.
     * [=====================]
     *                   [xxxxxxx]
     * [================] // desired result
     */
    public function testAvailabilityEndsEarlier()
    {
        $availability = CarbonAvailability::create(
            [['2019-01-01 10:00:00', '2019-01-01 12:00:00']],
            [['2019-01-01 11:45:00', '2019-01-01 12:30:00']]
        );
        $periods = $availability->periods();
        $this->assertCount(1, $periods);
        $this->assertEquals([
            $periods[0]->getStartDate()->toDateTimeString(),
            $periods[0]->getEndDate()->toDateTimeString(),
        ], ['2019-01-01 10:00:00', '2019-01-01 11:45:00']);
    }

    /**
     * Availability if eclipsed fully by unavailability, is completely removed.
     *   [===============]
     * [xxxxxxxxxxxxxxxxxxx]
     * [empty array] // desired result
     */
    public function testAvailabilityIsRemovedIfEclipsed()
    {
        $availability = CarbonAvailability::create(
            [['2019-01-01 10:00:00', '2019-01-01 12:00:00']],
            [['2019-01-01 09:45:00', '2019-01-01 12:15:00']]
        );
        $periods = $availability->periods();
        $this->assertCount(0, $periods);
    }

    /**
     * Availability if perfectly eclipsed by unavailability, is removed.
     * [===============]
     * [xxxxxxxxxxxxxxx]
     * [empty array] // desired result
     */
    public function testAvailabilityIsRemovedIfEclipsedPerfectly()
    {
        $availability = CarbonAvailability::create(
            [['2019-01-01 10:00:00', '2019-01-01 12:00:00']],
            [['2019-01-01 10:00:00', '2019-01-01 12:00:00']]
        );
        $periods = $availability->periods();
        $this->assertCount(0, $periods);
    }

    /**
     * Non overlapping unavailability has no effect.
     * [===============]
     *                      [xxxxx]
     * [===============] // desired result
     */
    public function testAvailabilityStaysUntouched()
    {
        $availability = CarbonAvailability::create(
            [['2019-01-01 10:00:00', '2019-01-01 12:00:00']],
            [['2019-01-01 12:45:00', '2019-01-01 13:15:00']]
        );
        $periods = $availability->periods();
        $this->assertCount(1, $periods);
        $this->assertEquals([
            $periods[0]->getStartDate()->toDateTimeString(),
            $periods[0]->getEndDate()->toDateTimeString(),
        ], ['2019-01-01 10:00:00', '2019-01-01 12:00:00']);
    }

    /**
     * Availability double splits.
     * [=====================]
     *    [xxx]     [xxx]
     * [=]     [===]     [===] // desired result
     */
    public function testTimesGetDoubleSplitByUnavailability()
    {
        $availability = CarbonAvailability::create(
            [['2019-01-01 08:00:00', '2019-01-01 12:00:00']],
            [
                ['2019-01-01 08:15:00', '2019-01-01 08:45:00'],
                ['2019-01-01 10:45:00', '2019-01-01 11:30:00'],
            ]
        );
        $periods = $availability->periods();
        $this->assertCount(3, $periods);
        $this->assertEquals([
            $periods[0]->getStartDate()->toDateTimeString(),
            $periods[0]->getEndDate()->toDateTimeString(),
            $periods[1]->getStartDate()->toDateTimeString(),
            $periods[1]->getEndDate()->toDateTimeString(),
            $periods[2]->getStartDate()->toDateTimeString(),
            $periods[2]->getEndDate()->toDateTimeString(),
        ], [
            '2019-01-01 08:00:00', '2019-01-01 08:15:00',
            '2019-01-01 08:45:00', '2019-01-01 10:45:00',
            '2019-01-01 11:30:00', '2019-01-01 12:00:00',
        ]);
    }

    /**
     * Unavailability removes time from multiple availabilities.
     * [=======]
     *              [=========]
     *       [xxxxxxxxx]
     * [====]           [=====]  // desired result
     */
    public function testMultipleTimesGetTrimmed()
    {
        $availability = CarbonAvailability::create(
            [
                ['2019-01-01 10:00:00', '2019-01-01 10:35:00'],
                ['2019-01-01 11:00:00', '2019-01-01 11:37:00']
            ],
            [['2019-01-01 10:20:00', '2019-01-01 11:10:00']]
        );
        $periods = $availability->periods();
        $this->assertCount(2, $periods);
        $this->assertEquals([
            $periods[0]->getStartDate()->toDateTimeString(),
            $periods[0]->getEndDate()->toDateTimeString(),
            $periods[1]->getStartDate()->toDateTimeString(),
            $periods[1]->getEndDate()->toDateTimeString(),
        ], ['2019-01-01 10:00:00', '2019-01-01 10:20:00', '2019-01-01 11:10:00', '2019-01-01 11:37:00']);
    }

    /**
     * Test that basic session times work.
     * [=============]
     * |  |  |  |  |   // desired result
     */
    public function testBasicSessionTimes()
    {
        $availability = CarbonAvailability::create(
            [['2019-01-01 11:00:00', '2019-01-01 12:00:00']]
        );
        $sessions = $availability->sessions('15 minutes');
        $this->assertCount(4, $sessions);
        $this->assertEquals([
            $sessions[0]->toDateTimeString(),
            $sessions[1]->toDateTimeString(),
            $sessions[2]->toDateTimeString(),
            $sessions[3]->toDateTimeString(),
        ], [
            '2019-01-01 11:00:00',
            '2019-01-01 11:15:00',
            '2019-01-01 11:30:00',
            '2019-01-01 11:45:00'
        ]);
    }

    /**
     * Test that multiple session times work.
     * [=============]
     *       [xxx]
     * |  |      |     // desired result
     */
    public function testBasicSessionTimesSplit()
    {
        $availability = CarbonAvailability::create(
            [['2019-01-01 11:00:00', '2019-01-01 12:00:00']],
            [['2019-01-01 11:35:00', '2019-01-01 11:40:00']]
        );
        $sessions = $availability->sessions('15 minutes');
        $this->assertCount(3, $sessions);
        $this->assertEquals([
            $sessions[0]->toDateTimeString(),
            $sessions[1]->toDateTimeString(),
            $sessions[2]->toDateTimeString(),
        ], [
            '2019-01-01 11:00:00',
            '2019-01-01 11:15:00',
            '2019-01-01 11:40:00'
        ]);
    }

    /**
     * [==========]
     *               [========]
     *                        [==========]
     *                    [xxxxxxx]
     *                                [x]
     * [=================]         [=]
     * |  |  |  |   |  |           |
     */
    public function testReadMeExample()
    {
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
        $sessions = $availability->sessions('15 minutes');
        $this->assertCount(7, $sessions);
        $this->assertEquals([
            $sessions[0]->toDateTimeString(),
            $sessions[1]->toDateTimeString(),
            $sessions[2]->toDateTimeString(),
            $sessions[3]->toDateTimeString(),
            $sessions[4]->toDateTimeString(),
            $sessions[5]->toDateTimeString(),
            $sessions[6]->toDateTimeString(),
        ], [
            '2019-01-01 09:00:00',
            '2019-01-01 09:15:00',
            '2019-01-01 09:30:00',
            '2019-01-01 09:45:00',
            '2019-01-01 10:15:00',
            '2019-01-01 10:30:00',
            '2019-01-01 11:30:00',
        ]);
    }
}
