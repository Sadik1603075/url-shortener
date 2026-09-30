<?php

namespace Tests\Feature\ShortUrl;

use App\Models\AccessCode;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ShortUrlCreateTest extends TestCase
{
    use DatabaseTransactions;

    private const CREATE = '/api/v1/urls';

    public function test_valid_access_code_creates_a_short_url(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $code = AccessCode::factory()->for($owner)->create(['code' => 'VALIDCODE1']);

        $response = $this->postJson(self::CREATE, [
            'access_code' => 'VALIDCODE1',
            'long_url' => 'https://example.com/a/very/long/path',
        ]);

        $response->assertCreated() // 201
            ->assertJsonStructure([
                'data' => ['id', 'short_code', 'short_url', 'long_url', 'is_active', 'click_count', 'created_at'],
            ])
            ->assertJsonPath('data.long_url', 'https://example.com/a/very/long/path');

        $shortCode = $response->json('data.short_code');
        $this->assertNotEmpty($shortCode);

        // Persisted, owned by the access code's user.
        $this->assertDatabaseHas('short_urls', [
            'short_code' => $shortCode,
            'long_url' => 'https://example.com/a/very/long/path',
            'user_id' => $owner->id,
        ]);

        // The code was consumed (last_used_at stamped).
        $this->assertNotNull($code->fresh()->last_used_at);
    }

    public function test_missing_access_code_is_rejected(): void
    {
        $this->postJson(self::CREATE, [
            'long_url' => 'https://example.com',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('access_code');

        $this->assertDatabaseCount('short_urls', 0);
    }

    public function test_unknown_access_code_is_rejected_fail_closed(): void
    {
        $this->postJson(self::CREATE, [
            'access_code' => 'DOES-NOT-EXIST',
            'long_url' => 'https://example.com',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('access_code');

        $this->assertDatabaseCount('short_urls', 0);
    }

    public function test_expired_access_code_is_rejected_fail_closed(): void
    {
        AccessCode::factory()->expired()->create(['code' => 'EXPIREDCODE']);

        $this->postJson(self::CREATE, [
            'access_code' => 'EXPIREDCODE',
            'long_url' => 'https://example.com',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('access_code');

        $this->assertDatabaseCount('short_urls', 0);
    }

    public function test_inactive_access_code_is_rejected_fail_closed(): void
    {
        AccessCode::factory()->inactive()->create(['code' => 'INACTIVECODE']);

        $this->postJson(self::CREATE, [
            'access_code' => 'INACTIVECODE',
            'long_url' => 'https://example.com',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('access_code');

        $this->assertDatabaseCount('short_urls', 0);
    }

    public function test_invalid_url_is_rejected(): void
    {
        $code = AccessCode::factory()->create(['code' => 'VALIDCODE2']);

        $this->postJson(self::CREATE, [
            'access_code' => 'VALIDCODE2',
            'long_url' => 'not-a-url',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('long_url');

        $this->assertDatabaseCount('short_urls', 0);
        // Validation fails before the controller, so a valid code stays unconsumed.
        $this->assertNull($code->fresh()->last_used_at);
    }

    public function test_missing_long_url_is_rejected(): void
    {
        $code = AccessCode::factory()->create(['code' => 'VALIDCODE3']);

        $this->postJson(self::CREATE, [
            'access_code' => 'VALIDCODE3',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('long_url');

        $this->assertDatabaseCount('short_urls', 0);
        $this->assertNull($code->fresh()->last_used_at);
    }
}
