<?php

namespace Braid;

use Carbon\CarbonPeriod;

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
    public function __construct(array $availableTimes = [], array $unavailableTimes = [])
    {
        $this->availability = static::mergePeriods(static::createPeriods($availableTimes));
        $this->unavailability = static::mergePeriods(static::createPeriods($unavailableTimes));
    }

    /**
     * Return all the available time periods with unavailable times removed.
     *
     * @return array
     */
    public function periods()
    {
        return static::removePeriods($this->availability, $this->unavailability);
    }

    /**
     * Break up the current availability into "sessions" that represent bookable
     * times.
     *
     * @param mixed $interval
     * @return [Carbon]
     */
    public function sessions($interval)
    {
        return array_reduce($this->periods(), function ($times, $available) use ($interval) {
            foreach ($available->setDateInterval($interval)->excludeEndDate() as $time) {
                if ($available->startsBeforeOrAt($time) && $available->endsAfterOrAt($time->copy()->add($interval))) {
                    $times[] = $time;
                }
            }
            return $times;
        }, []);
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
     * Given an array of periods, merge them together, this will recurse until
     * it has found no overlapping periods within the array.
     *
     * @param array $periods
     * @return [CarbonPeriod]
     */
    public static function mergePeriods(array $periods)
    {
        return array_reduce($periods, function ($merged, $period) {
            foreach ($merged as $mergedPeriod) {
                if ($didOverlap = static::expandPeriod($mergedPeriod, $period)) {
                    $merged = static::mergePeriods($merged);
                    break;
                }
            }
            return empty($didOverlap) ? array_merge($merged, [$period]) : $merged;
        }, []);
    }

    /**
     * Given 2 periods, expand the first (by reference) to encompass both, but
     * only if the 2 periods have some kind of overlap.
     *
     * @param CarbonPeriod $expand
     * @param CarbonPeriod $expandTo
     * @return bool
     */
    public static function expandPeriod($expand, $expandTo)
    {
        if (static::inclusiveOverlap($expand, $expandTo)) {
            $expand->setStartDate($expand->getStartDate()->min($expandTo->getStartDate()));
            $expand->setEndDate($expand->getEndDate()->max($expandTo->getEndDate()));
            return true;
        }
        return false;
    }

    /**
     * Given a group of available periods, remove from them the unavailable
     * periods and return a new array.
     *
     * @param [CarbonPeriod] $availability
     * @param [CarbonPeriod] $unavailability
     * @return [CarbonPeriod]
     */
    public static function removePeriods($availability, $unavailability)
    {
        return array_reduce($availability, function ($merged, $available) use ($unavailability) {
            foreach ($unavailability as $unavailable) {
                $available = static::subtractPeriod($available, $unavailable);
                if (is_array($available)) {
                    // Availability was split, recursively apply unavailability
                    return array_merge($merged, static::removePeriods($available, $unavailability));
                }
                if (!$available) {
                    // The availability was completely removed
                    return $merged;
                }
            }
            return array_merge($merged, [$available]);
        }, []);
    }

    /**
     * Given a carbon period, subtract another carbon period from it.
     *
     * @param CarbonPeriod $avail
     * @param CarbonPeriod $unavail
     * @return CarbonPeriod|[CarbonPeriod]|false always returns an array of carbon periods.
     */
    public static function subtractPeriod($avail, $unavail)
    {
        if ($avail->overlaps($unavail)) {
            if ($avail->startsBefore($unavail->getStartDate()) && $avail->endsAfter($unavail->getEndDate())) {
                // If the unavailability splits the available time, split it.
                return [
                    CarbonPeriod::create($avail->getStartDate(), $unavail->getStartDate()),
                    CarbonPeriod::create($unavail->getEndDate(), $avail->getEndDate())
                ];
            }
            if ($avail->startsAfterOrAt($unavail->getStartDate()) && $avail->endsAfter($unavail->getEndDate())) {
                // Trim the front end of the availability
                $avail->setStartDate($unavail->getEndDate());
            }
            if ($avail->endsAfter($unavail->getStartDate()) && $avail->endsBeforeOrAt($unavail->getEndDate())) {
                // Trim the back end of the availability
                $avail->setEndDate($unavail->getStartDate());
            }
            if ($avail->startsAfterOrAt($unavail->getStartDate()) && $avail->endsBeforeOrAt($unavail->getEndDate())) {
                // Total Eclipse, remove the entire period
                return false;
            }
        }
        return $avail;
    }

    /**
     * Checks if the existing periods have any overlap including start/ends
     * being exactly the same time (which doesnt trigger Carbon::overlaps()).
     *
     * @param CarbonPeriod $periodA
     * @param CarbonPeriod $periodB
     * @return void
     */
    public static function inclusiveOverlap($periodA, $periodB)
    {
        return $periodA->overlaps($periodB) ||
            $periodA->getStartDate()->eq($periodB->getStartDate()) ||
            $periodA->getStartDate()->eq($periodB->getEndDate()) ||
            $periodB->getStartDate()->eq($periodA->getEndDate()) ||
            $periodB->getEndDate()->eq($periodA->getEndDate());
    }

    /**
     * Short hand for functional instance creation.
     *
     * @return Braid\CarbonAvailability
     */
    public static function create(...$args)
    {
        return new static(...$args);
    }
}
