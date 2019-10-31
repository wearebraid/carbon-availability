<?php

namespace Braid;

class CarbonAvailability
{
    /**
     * List of available carbon periods.
     *
     * @var [Carbon\CarbonPeriod]
     */
    protected $availability;

    /**
     * List of unavailable carbon periods.
     *
     * @var [Carbon\CarbonPeriod]
     */
    protected $unavailability;

    /**
     * Accepts an array of available times, and an array of unavailable times.
     *
     * @param array $availablePeriods [[string $start_at, string $end_at], ...]
     * @param array $unavailablePeriods [[string $start_at, string $end_at], ...]
     * @return void
     */
    public function __constructor(array $availableTimes = [], array $unavailableTimes = [])
    {
        $this->availability = static::mergePeriods(static::createPeriods($availableTimes));
        $this->unavailability = static::mergePeriods(static::createPeriods($unavailableTimes));
    }

    /**
     * Create some time periods from an array of arrays of start dates and
     * end dates.
     *
     * @return [CarbonPeriod]
     */
    public static function createPeriods(array $times = [])
    {
        return array_map(function ($period) {
            return CarbonPeriod::create($period[0], $period[1]);
        }, $times);
    }

    /**
     * Given an array of periods, merge them together.
     *
     * @param array $periods
     * @return [CarbonPeriod]
     */
    public static function mergePeriods(array $periods)
    {
        $merged = [];
        foreach ($periods as $period) {
            $didOverlap = false;
            foreach ($merged as &$mergedPeriod) {
                if (static::inclusiveOverlap($mergedPeriod, $period)) {
                    $didOverlap = true;
                    if ($period->getStartDate()->lt($mergedPeriod->getStartDate())) {
                        $mergedPeriod->setStartDate($period->getStartDate());
                    }
                    if ($period->getEndDate()->gt($mergedPeriod->getEndDate())) {
                        $mergedPeriod->setEndDate($period->getEndDate());
                    }
                }
            }
            if (!$didOverlap) {
                $merged[] = $period;
            }
        }
        return $merged;
    }

    /**
     * Checks if the existing periods have any overlap including start/ends
     * being exactly the same time (which doesnt trigger Carbon::overlaps()).
     *
     * @param CarbonPeriod $periodA
     * @param CarbonPeriod $periodB
     * @return void
     */
    public static function inclusiveOverlap($periodA, $periodB) {
        return $periodA->overlaps($periodB) ||
            $periodA->getStartDate()->eq($periodB->getStartDate()) ||
            $periodA->getStartDate()->eq($periodB->getEndDate()) ||
            $periodB->getStartDate()->eq($periodA->getEndDate()) ||
            $periodB->getEndDate()->eq($periodA->getEndDate());
    }
}
