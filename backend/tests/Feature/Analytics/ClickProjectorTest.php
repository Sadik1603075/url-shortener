<?php

namespace Tests\Feature\Analytics;

use App\Events\UrlClicked;
use App\Messaging\ClickProjector;
use App\Models\ClickDailyAggregate;
use App\Models\ShortUrl;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClickProjectorTest extends TestCase
{
    use RefreshDatabase;

    private function projectClick(string $code): void
    {
        app(ClickProjector::class)->project(new UrlClicked(
            shortCode: $code,
            longUrl: 'https://example.com',
            occurredAt: now()->toIso8601String(),
            ip: '127.0.0.1',
            userAgent: 'phpunit',
        ));
    }

    public function test_it_projects_a_click_into_the_read_model(): void
    {
        $user = User::factory()->create();

        ShortUrl::create([
            'user_id' => $user->id,
            'short_code' => 'abc1234',
            'long_url' => 'https://example.com',
        ]);

        $this->projectClick('abc1234');
        $this->projectClick('abc1234');

        $this->assertDatabaseCount('click_events', 2);
        $this->assertSame(2, (int) ClickDailyAggregate::sum('clicks'));
        $this->assertSame(
            2,
            (int) ShortUrl::where('short_code', 'abc1234')->value('click_count'),
        );
    }

    public function test_overview_serves_clicks_from_the_read_model(): void
    {
        $user = User::factory()->create();

        ShortUrl::create([
            'user_id' => $user->id,
            'short_code' => 'xyz9876',
            'long_url' => 'https://example.com',
        ]);

        $this->projectClick('xyz9876');

        $overview = app(AnalyticsService::class)->overview(7);

        $this->assertSame(7, count($overview['clicks_series']));

        $total = array_sum(array_column($overview['clicks_series'], 'count'));
        $this->assertSame(1, $total);
    }
}
