<?php

namespace App\Messaging;

use App\Events\UrlClicked;
use App\Repositories\Contracts\ClickAnalyticsRepositoryInterface;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The CQRS projection: turns a UrlClicked fact into read-model state.
 *
 *   1. append to the event store   (click_events)
 *   2. bump the daily aggregate    (click_daily_aggregates)
 *   3. bump the per-URL counter     (short_urls.click_count)
 *
 * Both transports (SyncClickEventPublisher inline, Kafka consumer async)
 * funnel through here so projection logic lives in exactly one place.
 */
class ClickProjector
{
    public function __construct(
        private readonly ClickAnalyticsRepositoryInterface $analytics,
        private readonly ShortUrlRepositoryInterface $shortUrls,
    ) {}

    public function project(UrlClicked $event): void
    {
        $date = CarbonImmutable::parse($event->occurredAt)->toDateString();

        DB::transaction(function () use ($event, $date) {
            $this->analytics->recordEvent($event);
            $this->analytics->incrementDaily($date);
            $this->shortUrls->incrementClickCountByCode($event->shortCode);
        });
    }
}
