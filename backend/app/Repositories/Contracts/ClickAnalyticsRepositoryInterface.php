<?php

namespace App\Repositories\Contracts;

use App\Events\UrlClicked;

interface ClickAnalyticsRepositoryInterface
{
    /**
     * Append a click to the immutable event store.
     */
    public function recordEvent(UrlClicked $event): void;

    /**
     * Increment the daily read-model aggregate for the given Y-m-d date.
     */
    public function incrementDaily(string $date, int $by = 1): void;

    /**
     * Clicks per calendar day for the last $days days.
     *
     * @return array<string,int> keyed by Y-m-d
     */
    public function clicksByDay(int $days): array;
}
