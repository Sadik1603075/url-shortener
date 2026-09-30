<?php

namespace Tests\Feature\ShortUrl;

use App\Cache\Contracts\ShortUrlCacheInterface;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminShortUrlTest extends TestCase
{
    use DatabaseTransactions;

    private const BASE = '/api/v1/admin/urls';

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    /** Replace the Redis-backed cache with a spy so we can assert cache-busting. */
    private function spyCache(): MockInterface
    {
        $cache = Mockery::spy(ShortUrlCacheInterface::class);
        $this->app->instance(ShortUrlCacheInterface::class, $cache);

        return $cache;
    }

    // ---- Authorization gate -------------------------------------------------

    public static function adminRoutes(): array
    {
        return [
            'index' => ['get', self::BASE],
            'show' => ['get', self::BASE.'/1'],
            'update' => ['patch', self::BASE.'/1'],
            'destroy' => ['delete', self::BASE.'/1'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_admin_routes_require_authentication(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized(); // 401
    }

    #[DataProvider('adminRoutes')]
    public function test_admin_routes_forbidden_for_non_admin(string $method, string $uri): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->json($method, $uri)->assertForbidden(); // 403
    }

    // ---- index / show -------------------------------------------------------

    public function test_admin_can_list_short_urls_paginated(): void
    {
        $this->actingAsAdmin();
        ShortUrl::factory()->count(3)->create();

        $this->getJson(self::BASE)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'short_code', 'short_url', 'long_url', 'is_active']],
                'links',
                'meta',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_show_a_short_url(): void
    {
        $this->actingAsAdmin();
        $shortUrl = ShortUrl::factory()->create();

        $this->getJson(self::BASE.'/'.$shortUrl->id)
            ->assertOk()
            ->assertJsonPath('data.id', $shortUrl->id)
            ->assertJsonPath('data.short_code', $shortUrl->short_code);
    }

    public function test_show_returns_404_for_missing_short_url(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::BASE.'/999999')->assertNotFound();
    }

    // ---- update -------------------------------------------------------------

    public function test_admin_can_update_a_short_url_and_cache_is_busted(): void
    {
        $this->actingAsAdmin();
        $cache = $this->spyCache();
        $shortUrl = ShortUrl::factory()->create(['is_active' => true]);

        $this->patchJson(self::BASE.'/'.$shortUrl->id, [
            'long_url' => 'https://updated.example.com',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.long_url', 'https://updated.example.com')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('short_urls', [
            'id' => $shortUrl->id,
            'long_url' => 'https://updated.example.com',
            'is_active' => false,
        ]);

        $cache->shouldHaveReceived('forget')->with($shortUrl->short_code)->once();
    }

    public function test_admin_can_clear_expiry_via_patch(): void
    {
        // Regression: sending expires_at=null must un-expire the URL.
        $this->actingAsAdmin();
        $this->spyCache();
        $shortUrl = ShortUrl::factory()->create(['expires_at' => now()->addWeek()]);

        $this->patchJson(self::BASE.'/'.$shortUrl->id, ['expires_at' => null])
            ->assertOk()
            ->assertJsonPath('data.expires_at', null);

        $this->assertNull($shortUrl->fresh()->expires_at);
    }

    public function test_update_rejects_invalid_url(): void
    {
        $this->actingAsAdmin();
        $shortUrl = ShortUrl::factory()->create();

        $this->patchJson(self::BASE.'/'.$shortUrl->id, [
            'long_url' => 'not-a-url',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('long_url');
    }

    // ---- destroy ------------------------------------------------------------

    public function test_admin_can_delete_a_short_url_and_cache_is_busted(): void
    {
        $this->actingAsAdmin();
        $cache = $this->spyCache();
        $shortUrl = ShortUrl::factory()->create();

        $this->deleteJson(self::BASE.'/'.$shortUrl->id)->assertNoContent(); // 204

        $this->assertDatabaseMissing('short_urls', ['id' => $shortUrl->id]);
        $cache->shouldHaveReceived('forget')->with($shortUrl->short_code)->once();
    }

    public function test_destroy_returns_404_for_missing_short_url(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson(self::BASE.'/999999')->assertNotFound();
    }
}
