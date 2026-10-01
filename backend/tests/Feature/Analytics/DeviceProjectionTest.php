<?php

namespace Tests\Feature\Analytics;

use App\Events\UrlClicked;
use App\Messaging\ClickProjector;
use App\Models\ClickDeviceAggregate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DeviceProjectionTest extends TestCase
{
    use DatabaseTransactions;

    private const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    private function project(?string $userAgent): void
    {
        app(ClickProjector::class)->project(new UrlClicked(
            shortCode: 'abc1234',
            longUrl: 'https://example.com',
            occurredAt: now()->toIso8601String(),
            ip: '127.0.0.1',
            userAgent: $userAgent,
        ));
    }

    public function test_projection_writes_a_device_row_for_a_known_user_agent(): void
    {
        $this->project(self::CHROME_WINDOWS);
        $this->project(self::CHROME_WINDOWS);

        $this->assertDatabaseHas('click_device_aggregates', [
            'browser' => 'Chrome',
            'os' => 'Windows',
            'device_type' => 'desktop',
            'clicks' => 2,
        ]);

        // Same bucket is reused, not duplicated.
        $this->assertSame(1, ClickDeviceAggregate::query()->count());
    }

    public function test_distinct_user_agents_create_distinct_buckets(): void
    {
        $this->project(self::CHROME_WINDOWS);
        $this->project('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1');

        $this->assertDatabaseHas('click_device_aggregates', ['browser' => 'Chrome', 'os' => 'Windows', 'device_type' => 'desktop', 'clicks' => 1]);
        $this->assertDatabaseHas('click_device_aggregates', ['browser' => 'Safari', 'os' => 'iOS', 'device_type' => 'mobile', 'clicks' => 1]);
        $this->assertSame(2, ClickDeviceAggregate::query()->count());
    }

    public function test_unknown_user_agent_is_handled_gracefully(): void
    {
        $this->project(null);

        $this->assertDatabaseHas('click_device_aggregates', [
            'browser' => 'Unknown',
            'os' => 'Unknown',
            'device_type' => 'unknown',
            'clicks' => 1,
        ]);
    }
}
