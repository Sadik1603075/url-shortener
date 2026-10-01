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
     * Increment the device-breakdown read model for a (browser, os, device_type)
     * bucket. Part of the device-enrichment projection (D5-T3).
     */
    public function incrementDevice(string $browser, string $os, string $deviceType, int $by = 1): void;

    /**
     * Clicks per calendar day for the last $days days.
     *
     * @return array<string,int> keyed by Y-m-d
     */
    public function clicksByDay(int $days): array;

    /**
     * All-time device breakdown from the device read model, grouped three ways.
     *
     * @return array{by_type: array<string,int>, by_browser: array<string,int>, by_os: array<string,int>}
     */
    public function deviceBreakdown(): array;
}
