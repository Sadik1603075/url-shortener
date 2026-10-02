<?php

namespace Tests\Feature\Integration;

use App\Models\AccessCode;
use App\Models\ClickDailyAggregate;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BindsInMemoryShortUrlCache;
use Tests\TestCase;

/**
 * End-to-end across the layers (sync click driver): create a short URL through the
 * API, redirect through it, and assert the click was projected into every read
 * model. Exercises controller → service → repository → ClickProjector.
 */
class CreateRedirectClickFlowTest extends TestCase
{
    use BindsInMemoryShortUrlCache;
    use DatabaseTransactions;

    private const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        $this->bindInMemoryShortUrlCache();
    }

    public function test_create_then_redirect_projects_a_click_across_all_read_models(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        AccessCode::factory()->for($owner)->create(['code' => 'FLOWCODE1']);

        // 1. Create a short URL via the public API (access-code gated).
        $create = $this->postJson('/api/v1/urls', [
            'access_code' => 'FLOWCODE1',
            'long_url' => 'https://example.com/flow',
        ])->assertCreated();

        $code = $create->json('data.short_code');
        $this->assertNotEmpty($code);
        $this->assertDatabaseHas('short_urls', [
            'short_code' => $code,
            'user_id' => $owner->id,
            'click_count' => 0,
        ]);

        // 2. Redirect through it (desktop Chrome UA for the device projection).
        $this->get('/'.$code, ['User-Agent' => self::CHROME_WINDOWS])
            ->assertRedirect('https://example.com/flow');

        // 3. The click was projected into every read model (sync driver).
        $this->assertDatabaseHas('click_events', ['short_code' => $code]);
        $this->assertSame(1, (int) ShortUrl::where('short_code', $code)->value('click_count'));
        $this->assertGreaterThanOrEqual(1, (int) ClickDailyAggregate::sum('clicks'));
        $this->assertDatabaseHas('click_device_aggregates', [
            'browser' => 'Chrome',
            'os' => 'Windows',
            'device_type' => 'desktop',
            'clicks' => 1,
        ]);

        // And the access code was consumed.
        $this->assertNotNull(AccessCode::where('code', 'FLOWCODE1')->value('last_used_at'));
    }

    public function test_second_redirect_increments_the_same_read_models(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        AccessCode::factory()->for($owner)->create(['code' => 'FLOWCODE2']);

        $code = $this->postJson('/api/v1/urls', [
            'access_code' => 'FLOWCODE2',
            'long_url' => 'https://example.com/again',
        ])->assertCreated()->json('data.short_code');

        $this->get('/'.$code, ['User-Agent' => self::CHROME_WINDOWS])->assertRedirect('https://example.com/again');
        $this->get('/'.$code, ['User-Agent' => self::CHROME_WINDOWS])->assertRedirect('https://example.com/again');

        $this->assertSame(2, (int) ShortUrl::where('short_code', $code)->value('click_count'));
        $this->assertDatabaseHas('click_device_aggregates', [
            'browser' => 'Chrome',
            'os' => 'Windows',
            'device_type' => 'desktop',
            'clicks' => 2,
        ]);
    }
}
