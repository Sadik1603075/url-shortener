<?php

namespace App\Repositories\Eloquent;

use App\Events\UrlClicked;
use App\Models\ClickDailyAggregate;
use App\Models\ClickDeviceAggregate;
use App\Models\ClickEvent;
use App\Repositories\Contracts\ClickAnalyticsRepositoryInterface;

class EloquentClickAnalyticsRepository implements ClickAnalyticsRepositoryInterface
{
    public function recordEvent(UrlClicked $event): void
    {
        ClickEvent::create([
            'short_code' => $event->shortCode,
            'long_url' => $event->longUrl,
            'ip' => $event->ip,
            'user_agent' => $event->userAgent,
            'referer' => $event->referer,
            'occurred_at' => $event->occurredAt,
        ]);
    }

    public function incrementDaily(string $date, int $by = 1): void
    {
        $aggregate = ClickDailyAggregate::query()->firstOrCreate(
            ['date' => $date],
            ['clicks' => 0],
        );

        $aggregate->increment('clicks', $by);
    }

    public function incrementDevice(string $browser, string $os, string $deviceType, int $by = 1): void
    {
        $aggregate = ClickDeviceAggregate::query()->firstOrCreate(
            ['browser' => $browser, 'os' => $os, 'device_type' => $deviceType],
            ['clicks' => 0],
        );

        $aggregate->increment('clicks', $by);
    }

    public function clicksByDay(int $days): array
    {
        $since = now()->startOfDay()->subDays($days - 1)->toDateString();

        return ClickDailyAggregate::query()
            ->where('date', '>=', $since)
            ->get()
            ->mapWithKeys(fn (ClickDailyAggregate $a) => [
                $a->date->toDateString() => (int) $a->clicks,
            ])
            ->toArray();
    }

    public function deviceBreakdown(): array
    {
        return [
            'by_type' => $this->sumClicksGroupedBy('device_type'),
            'by_browser' => $this->sumClicksGroupedBy('browser'),
            'by_os' => $this->sumClicksGroupedBy('os'),
        ];
    }

    /**
     * @return array<string,int> clicks summed per distinct value of $column
     */
    private function sumClicksGroupedBy(string $column): array
    {
        // $column is passed as a quoted identifier (never interpolated into raw
        // SQL); only the SUM is raw. Callers pass literal column names.
        return ClickDeviceAggregate::query()
            ->groupBy($column)
            ->selectRaw('SUM(clicks) as clicks')
            ->addSelect($column)
            ->pluck('clicks', $column)
            ->map(fn ($clicks) => (int) $clicks)
            ->toArray();
    }
}
