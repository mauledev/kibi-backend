<?php

namespace App\Modules\Staff\Domain\Entities;

/**
 * Working schedule of a Backoffice staff member.
 *
 * `startTime` / `endTime` use 24h `HH:mm` notation. `timezone` is an IANA name
 * (e.g. `America/Mexico_City`). `days` holds weekday codes (`mon`..`sun`).
 */
class WorkSchedule
{
    /**
     * @param  array<string>  $days
     */
    public function __construct(
        private readonly string $timezone,
        private readonly array $days,
        private readonly string $startTime,
        private readonly string $endTime,
    ) {}

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /** @return array<string> */
    public function getDays(): array
    {
        return $this->days;
    }

    public function getStartTime(): string
    {
        return $this->startTime;
    }

    public function getEndTime(): string
    {
        return $this->endTime;
    }
}
