<?php

namespace Tests\Unit\Analytics;

use App\Models\ShortUrl;
use App\Repositories\Contracts\AccessCodeRepositoryInterface;
use App\Repositories\Contracts\ClickAnalyticsRepositoryInterface;
use App\Repositories\Contracts\ShortUrlRepositoryInterface;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    private ShortUrlRepositoryInterface $shortUrls;

    private AccessCodeRepositoryInterface $accessCodes;

    private ClickAnalyticsRepositoryInterface $clicks;

    private AnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shortUrls = Mockery::mock(ShortUrlRepositoryInterface::class);
        $this->accessCodes = Mockery::mock(AccessCodeRepositoryInterface::class);
        $this->clicks = Mockery::mock(ClickAnalyticsRepositoryInterface::class);

        $this->service = new AnalyticsService($this->shortUrls, $this->accessCodes, $this->clicks);
    }

    /** series row list → [date => count] for easy assertions. */
    private function byDate(array $series): array
    {
        return collect($series)->mapWithKeys(fn ($r) => [$r['date'] => $r['count']])->all();
    }

    public function test_overview_assembles_totals_zero_filled_series_and_top_urls(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00'));

        $this->shortUrls->shouldReceive('totalCount')->once()->andReturn(10);
        $this->shortUrls->shouldReceive('totalClicks')->once()->andReturn(42);
        $this->accessCodes->shouldReceive('activeCount')->once()->andReturn(4);
        $this->accessCodes->shouldReceive('totalCount')->once()->andReturn(6);

        $this->shortUrls->shouldReceive('createdCountByDay')->once()->with(7)->andReturn([
            '2026-10-01' => 3,
            '2026-09-29' => 2,
        ]);
        $this->clicks->shouldReceive('clicksByDay')->once()->with(7)->andReturn([
            '2026-09-30' => 5,
        ]);
        $this->shortUrls->shouldReceive('topByClicks')->once()->with(5)->andReturn(new Collection([
            new ShortUrl(['short_code' => 'aaa', 'long_url' => 'https://a.example.com', 'click_count' => 9]),
            new ShortUrl(['short_code' => 'bbb', 'long_url' => 'https://b.example.com', 'click_count' => 4]),
        ]));

        $overview = $this->service->overview(7);

        // Totals
        $this->assertSame([
            'total_urls' => 10,
            'total_clicks' => 42,
            'active_codes' => 4,
            'total_codes' => 6,
        ], $overview['totals']);

        // Zero-filled continuous 7-day series, newest last.
        $this->assertCount(7, $overview['urls_created']);
        $this->assertSame('2026-09-25', $overview['urls_created'][0]['date']);
        $this->assertSame('2026-10-01', $overview['urls_created'][6]['date']);

        $urls = $this->byDate($overview['urls_created']);
        $this->assertSame(3, $urls['2026-10-01']);
        $this->assertSame(2, $urls['2026-09-29']);
        $this->assertSame(0, $urls['2026-09-27']); // untouched day → zero-filled

        $clicks = $this->byDate($overview['clicks_series']);
        $this->assertCount(7, $overview['clicks_series']);
        $this->assertSame(5, $clicks['2026-09-30']);
        $this->assertSame(0, $clicks['2026-10-01']);

        // top_urls mapped to plain rows with int click_count.
        $this->assertSame([
            ['short_code' => 'aaa', 'long_url' => 'https://a.example.com', 'click_count' => 9],
            ['short_code' => 'bbb', 'long_url' => 'https://b.example.com', 'click_count' => 4],
        ], $overview['top_urls']);
    }
}
