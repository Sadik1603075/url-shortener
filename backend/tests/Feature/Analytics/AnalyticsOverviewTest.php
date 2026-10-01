<?php

namespace Tests\Feature\Analytics;

use App\Models\AccessCode;
use App\Models\ClickDailyAggregate;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnalyticsOverviewTest extends TestCase
{
    use DatabaseTransactions;

    private const URI = '/api/v1/admin/analytics/overview';

    public function test_requires_authentication(): void
    {
        $this->getJson(self::URI)->assertUnauthorized(); // 401
    }

    public function test_forbidden_for_non_admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson(self::URI)->assertForbidden(); // 403
    }

    public function test_overview_returns_the_expected_aggregate_shape_and_values(): void
    {
        // Freeze time mid-day so seeding and assertion share one date key (no
        // midnight-boundary flake).
        $this->travelTo(now()->startOfDay()->addHours(12));

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        ShortUrl::factory()->create(['click_count' => 10]);
        ShortUrl::factory()->create(['click_count' => 5]);
        ShortUrl::factory()->create(['click_count' => 1]);

        AccessCode::factory()->count(2)->create();           // active
        AccessCode::factory()->inactive()->create();          // inactive

        ClickDailyAggregate::create(['date' => now()->toDateString(), 'clicks' => 7]);

        $response = $this->getJson(self::URI);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'totals' => ['total_urls', 'total_clicks', 'active_codes', 'total_codes'],
                    'urls_created' => [['date', 'count']],
                    'clicks_series' => [['date', 'count']],
                    'top_urls' => [['short_code', 'long_url', 'click_count']],
                ],
            ])
            ->assertJsonPath('data.totals.total_urls', 3)
            ->assertJsonPath('data.totals.total_clicks', 16)
            ->assertJsonPath('data.totals.active_codes', 2)
            ->assertJsonPath('data.totals.total_codes', 3)
            ->assertJsonPath('data.top_urls.0.click_count', 10); // ordered desc

        // Default window is 30 days; today's seeded aggregate shows up.
        $clicksByDate = collect($response->json('data.clicks_series'))
            ->mapWithKeys(fn ($r) => [$r['date'] => $r['count']]);
        $this->assertSame(7, $clicksByDate[now()->toDateString()]);
    }

    public function test_days_parameter_is_clamped_to_a_minimum_of_seven(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->getJson(self::URI.'?days=3')->assertOk();

        $this->assertCount(7, $response->json('data.clicks_series'));
        $this->assertCount(7, $response->json('data.urls_created'));
    }

    public function test_days_parameter_is_clamped_to_a_maximum_of_ninety(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->getJson(self::URI.'?days=500')->assertOk();

        $this->assertCount(90, $response->json('data.clicks_series'));
        $this->assertCount(90, $response->json('data.urls_created'));
    }
}
