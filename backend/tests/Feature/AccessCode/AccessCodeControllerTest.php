<?php

namespace Tests\Feature\AccessCode;

use App\Mail\AccessCodeMail;
use App\Models\AccessCode;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessCodeControllerTest extends TestCase
{
    use DatabaseTransactions;

    private const BASE = '/api/v1/admin/access-codes';

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    // ---- Authorization gate -------------------------------------------------

    public static function endpoints(): array
    {
        return [
            'index' => ['get', self::BASE],
            'store' => ['post', self::BASE],
            'show' => ['get', self::BASE.'/1'],
            'update' => ['patch', self::BASE.'/1'],
            'destroy' => ['delete', self::BASE.'/1'],
            'send' => ['post', self::BASE.'/1/send'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_endpoints_require_authentication(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized(); // 401
    }

    #[DataProvider('endpoints')]
    public function test_endpoints_forbidden_for_non_admin(string $method, string $uri): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->json($method, $uri)->assertForbidden(); // 403
    }

    // ---- store --------------------------------------------------------------

    public function test_admin_can_create_a_unique_access_code(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->postJson(self::BASE, [
            'email' => 'grantee@example.com',
            'description' => 'For the marketing team',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'code', 'email', 'description', 'is_active', 'created_at'],
            ])
            ->assertJsonPath('data.email', 'grantee@example.com')
            ->assertJsonPath('data.is_active', true);

        $code = $response->json('data.code');
        $this->assertMatchesRegularExpression('/^USR-[0-9A-Z]{4}-[0-9A-Z]{4}$/', $code);

        $this->assertDatabaseHas('access_codes', [
            'code' => $code,
            'email' => 'grantee@example.com',
            'user_id' => $admin->id,
            'is_active' => true,
        ]);
    }

    public function test_create_rejects_duplicate_email(): void
    {
        $this->actingAsAdmin();
        AccessCode::factory()->create(['email' => 'taken@example.com']);

        $this->postJson(self::BASE, ['email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_create_requires_an_email(): void
    {
        $this->actingAsAdmin();

        $this->postJson(self::BASE, ['description' => 'no email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    // ---- index / show -------------------------------------------------------

    public function test_admin_can_list_access_codes_paginated(): void
    {
        $this->actingAsAdmin();
        AccessCode::factory()->count(3)->create();

        $this->getJson(self::BASE)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'code', 'email', 'is_active']],
                'links',
                'meta',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_show_an_access_code(): void
    {
        $this->actingAsAdmin();
        $accessCode = AccessCode::factory()->create();

        $this->getJson(self::BASE.'/'.$accessCode->id)
            ->assertOk()
            ->assertJsonPath('data.id', $accessCode->id)
            ->assertJsonPath('data.code', $accessCode->code);
    }

    public function test_show_returns_404_for_missing_code(): void
    {
        $this->actingAsAdmin();

        $this->getJson(self::BASE.'/999999')->assertNotFound();
    }

    // ---- update -------------------------------------------------------------

    public function test_admin_can_deactivate_and_set_expiry(): void
    {
        $this->actingAsAdmin();
        $accessCode = AccessCode::factory()->create(['is_active' => true, 'expires_at' => null]);
        $expiry = now()->addWeek();

        $this->patchJson(self::BASE.'/'.$accessCode->id, [
            'is_active' => false,
            'expires_at' => $expiry->toIso8601String(),
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $fresh = $accessCode->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertNotNull($fresh->expires_at);
    }

    public function test_admin_can_clear_expiry_via_patch(): void
    {
        // Regression: sending expires_at=null must clear the expiry.
        $this->actingAsAdmin();
        $accessCode = AccessCode::factory()->create(['expires_at' => now()->addWeek()]);

        $this->patchJson(self::BASE.'/'.$accessCode->id, ['expires_at' => null])
            ->assertOk()
            ->assertJsonPath('data.expires_at', null);

        $this->assertNull($accessCode->fresh()->expires_at);
    }

    public function test_update_returns_404_for_missing_code(): void
    {
        $this->actingAsAdmin();

        $this->patchJson(self::BASE.'/999999', ['is_active' => false])->assertNotFound();
    }

    // ---- destroy ------------------------------------------------------------

    public function test_admin_can_delete_an_access_code(): void
    {
        $this->actingAsAdmin();
        $accessCode = AccessCode::factory()->create();

        $this->deleteJson(self::BASE.'/'.$accessCode->id)->assertNoContent(); // 204

        $this->assertDatabaseMissing('access_codes', ['id' => $accessCode->id]);
    }

    public function test_destroy_returns_404_for_missing_code(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson(self::BASE.'/999999')->assertNotFound();
    }

    // ---- send ---------------------------------------------------------------

    public function test_send_dispatches_the_access_code_mail(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $accessCode = AccessCode::factory()->create(['email' => 'grantee@example.com']);

        $this->postJson(self::BASE.'/'.$accessCode->id.'/send')
            ->assertOk()
            ->assertJsonPath('message', 'Access code sent to grantee@example.com.');

        Mail::assertSent(
            AccessCodeMail::class,
            fn (AccessCodeMail $mail) => $mail->accessCode->is($accessCode)
                && $mail->hasTo('grantee@example.com'),
        );
    }

    public function test_send_returns_404_for_missing_code(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $this->postJson(self::BASE.'/999999/send')->assertNotFound();

        Mail::assertNothingSent();
    }
}
