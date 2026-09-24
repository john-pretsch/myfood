<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSsoSuccess(string $ssoId, string $email, string $name, array $permissions = ['global' => null, 'apps' => []]): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'fake-token']),
            '*/api/user' => Http::response(['id' => $ssoId, 'email' => $email, 'name' => $name]),
            '*/api/permissions' => Http::response($permissions),
        ]);
    }

    public function test_a_user_can_log_in_via_sso_with_valid_credentials(): void
    {
        $this->fakeSsoSuccess('sso-123', 'jane@example.com', 'Jane');

        $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'whatever-the-sso-app-accepts',
        ])->assertOk()->assertJsonPath('user.email', 'jane@example.com');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'sso_id' => 'sso-123']);
    }

    public function test_an_existing_local_user_is_linked_to_their_sso_id_on_first_login(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->fakeSsoSuccess('sso-123', 'jane@example.com', 'Jane');

        $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'whatever-the-sso-app-accepts',
        ])->assertOk();

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('sso-123', $user->fresh()->sso_id);
    }

    public function test_the_users_myfood_role_is_synced_from_sso_permissions_on_login(): void
    {
        $this->fakeSsoSuccess('sso-123', 'jane@example.com', 'Jane', [
            'global' => 'edit',
            'apps' => ['myfood' => 'admin', 'fit' => 'read'],
        ]);

        $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'whatever-the-sso-app-accepts',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'role' => 'admin']);
    }

    public function test_login_still_succeeds_when_the_permissions_fetch_fails(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'fake-token']),
            '*/api/user' => Http::response(['id' => 'sso-123', 'email' => 'jane@example.com', 'name' => 'Jane']),
            '*/api/permissions' => Http::response(['message' => 'Server Error'], 500),
        ]);

        $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'whatever-the-sso-app-accepts',
        ])->assertOk();

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'role' => null]);
    }

    public function test_login_fails_when_sso_rejects_the_credentials(): void
    {
        Http::fake(['*/oauth/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_a_logged_in_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertGuest();
    }

    public function test_guest_user_endpoint_returns_null_user(): void
    {
        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user', null);
    }
}
