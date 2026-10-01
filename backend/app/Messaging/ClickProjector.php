<?php

namespace App\Messaging;

use App\Events\UrlClicked;
use App\Metrics\Metrics;
use App\Repositories\Contracts\ClickAnalyticsRepositoryInterface;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use App\Support\UserAgentParser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The CQRS projection: turns a UrlClicked fact into read-model state.
 *
 *   1. append to the event store   (click_events)
 *   2. bump the daily aggregate    (click_daily_aggregates)
 *   3. bump the device breakdown    (click_device_aggregates)
 *   4. bump the per-URL counter     (short_urls.click_count)
 *
 * Both transports (SyncClickEventPublisher inline, Kafka consumer async)
 * funnel through here so projection logic lives in exactly one place.
 */
class ClickProjector
{
    public function __construct(
        private readonly ClickAnalyticsRepositoryInterface $analytics,
        private readonly ShortUrlRepositoryInterface $shortUrls,
        private readonly Metrics $metrics,
    ) {}

    public function project(UrlClicked $event): void
    {
        $date = CarbonImmutable::parse($event->occurredAt)->toDateString();
        $device = UserAgentParser::parse($event->userAgent);

        DB::transaction(function () use ($event, $date, $device) {
            $this->analytics->recordEvent($event);
            $this->analytics->incrementDaily($date);
            $this->analytics->incrementDevice($device['browser'], $device['os'], $device['device_type']);
            $this->shortUrls->incrementClickCountByCode($event->shortCode);
        });

        // Lag proxy: published (api) vs consumed (worker) under the Kafka driver.
        $this->metrics->incClickConsumed();
    }
}
