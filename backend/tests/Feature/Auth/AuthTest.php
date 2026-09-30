<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    private const LOGIN = '/api/v1/auth/login';

    private const LOGOUT = '/api/v1/auth/logout';

    // Any route behind the auth:sanctum + admin middleware stack.
    private const ADMIN_ROUTE = '/api/v1/admin/analytics/overview';

    private const ADMIN_EMAIL = 'admin@linkforge.test';

    private const USER_EMAIL = 'user@linkforge.test';

    private const PASSWORD = 'secret-password';

    public function test_admin_can_login_and_receives_token_and_user_resource(): void
    {
        $admin = $this->createUser('admin', self::ADMIN_EMAIL);

        $response = $this->login(self::ADMIN_EMAIL, self::PASSWORD);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'role', 'created_at'],
                ],
            ])
            ->assertJsonPath('data.user.id', $admin->id)
            ->assertJsonPath('data.user.email', self::ADMIN_EMAIL)
            ->assertJsonPath('data.user.role', 'admin');

        $this->assertNotEmpty($response->json('data.token'));

        // A Sanctum token was actually persisted for this user.
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $admin->id,
            'tokenable_type' => User::class,
            'name' => 'admin-web',
        ]);
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $this->createUser('admin', self::ADMIN_EMAIL);

        $this->login(self::ADMIN_EMAIL, 'wrong-password')
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_with_unknown_email_is_rejected(): void
    {
        $this->login('nobody@linkforge.test', self::PASSWORD)
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_validation_fails_when_fields_are_missing(): void
    {
        $this->postJson(self::LOGIN, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Login is admin-only by design: a non-admin with correct credentials is
     * rejected at the login step (AuthenticationService throws before minting a
     * token). This is stricter than "log in, then get blocked at /admin/*".
     */
    public function test_non_admin_cannot_login_even_with_correct_credentials(): void
    {
        $this->createUser('user', self::USER_EMAIL);

        $this->login(self::USER_EMAIL, self::PASSWORD)
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_routes_reject_unauthenticated_requests(): void
    {
        $this->getJson(self::ADMIN_ROUTE)->assertUnauthorized(); // 401
    }

    public function test_admin_middleware_blocks_authenticated_non_admin(): void
    {
        Sanctum::actingAs($this->createUser('user', self::USER_EMAIL));

        $this->getJson(self::ADMIN_ROUTE)->assertForbidden(); // 403
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $this->createUser('admin', self::ADMIN_EMAIL);

        $token = $this->login(self::ADMIN_EMAIL, self::PASSWORD)->json('data.token');
        $auth = ['Authorization' => 'Bearer '.$token];

        // First logout succeeds and deletes the token.
        $this->postJson(self::LOGOUT, [], $auth)->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // The single reused test app memoizes the resolved user on the sanctum
        // guard; forget it so the next request re-authenticates against the DB
        // (in production every request is a fresh instance and does this anyway).
        $this->app['auth']->forgetGuards();

        // The same token is now rejected.
        $this->postJson(self::LOGOUT, [], $auth)->assertUnauthorized(); // 401
    }

    /**
     * Seed a user with the given role. Password is the shared test password;
     * the model's `hashed` cast hashes it on assignment.
     */
    private function createUser(string $role, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => self::PASSWORD,
            'role' => $role,
        ]);
    }

    private function login(string $email, string $password): TestResponse
    {
        return $this->postJson(self::LOGIN, [
            'email' => $email,
            'password' => $password,
        ]);
    }
}
