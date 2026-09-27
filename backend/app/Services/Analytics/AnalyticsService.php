<?php

namespace App\Services\Analytics;

use App\Repositories\Contracts\AccessCodeRepositoryInterface;
use App\Repositories\Contracts\ClickAnalyticsRepositoryInterface;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use Carbon\CarbonImmutable;

class AnalyticsService
{
    public function __construct(
        private readonly ShortUrlRepositoryInterface $shortUrls,
        private readonly AccessCodeRepositoryInterface $accessCodes,
        private readonly ClickAnalyticsRepositoryInterface $clicks,
    ) {}

    /**
     * Assemble the admin dashboard overview.
     *
     * `clicks_series` is served from the CQRS read model (click_daily_aggregates),
     * projected from the UrlClicked event store (see docs/adr/0002).
     *
     * @return array<string,mixed>
     */
    public function overview(int $days = 30): array
    {
        return [
            'totals' => [
                'total_urls' => $this->shortUrls->totalCount(),
                'total_clicks' => $this->shortUrls->totalClicks(),
                'active_codes' => $this->accessCodes->activeCount(),
                'total_codes' => $this->accessCodes->totalCount(),
            ],
            'urls_created' => $this->dailySeries(
                $this->shortUrls->createdCountByDay($days),
                $days,
            ),
            'clicks_series' => $this->dailySeries(
                $this->clicks->clicksByDay($days),
                $days,
            ),
            'top_urls' => $this->topUrls(5),
        ];
    }

    /**
     * Continuous per-day series (zero-filled) for the last $days days.
     *
     * @param  array<string,int>  $counts  keyed by Y-m-d
     * @return array<int,array{date:string,count:int}>
     */
    private function dailySeries(array $counts, int $days): array
    {
        $today = CarbonImmutable::now()->startOfDay();

        $series = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $today->subDays($i)->toDateString();

            $series[] = [
                'date' => $date,
                'count' => $counts[$date] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * @return array<int,array{short_code:string,long_url:string,click_count:int}>
     */
    private function topUrls(int $limit): array
    {
        return $this->shortUrls->topByClicks($limit)
            ->map(fn ($url) => [
                'short_code' => $url->short_code,
                'long_url' => $url->long_url,
                'click_count' => (int) $url->click_count,
            ])
            ->all();
    }
}
