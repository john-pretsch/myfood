<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSsoCallback(string $ssoId, string $email, string $name, array $permissions = ['global' => null, 'apps' => []]): void
    {
        $ssoUser = (new SocialiteUser)->map(['id' => $ssoId, 'email' => $email, 'name' => $name]);
        $ssoUser->token = 'fake-token';

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($ssoUser);
        Socialite::shouldReceive('driver')->with('jepflow_sso')->andReturn($driver);

        Http::fake(['*/api/permissions' => Http::response($permissions)]);
    }

    public function test_sso_callback_creates_and_logs_in_a_new_user(): void
    {
        $this->fakeSsoCallback('sso-123', 'jane@example.com', 'Jane');

        $this->get('/auth/sso/callback')->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'sso_id' => 'sso-123']);
    }

    public function test_an_existing_local_user_is_linked_to_their_sso_id_on_first_login(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->fakeSsoCallback('sso-123', 'jane@example.com', 'Jane');

        $this->get('/auth/sso/callback')->assertRedirect('/');

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('sso-123', $user->fresh()->sso_id);
    }

    public function test_the_users_myfood_role_is_synced_from_sso_permissions_on_login(): void
    {
        $this->fakeSsoCallback('sso-123', 'jane@example.com', 'Jane', [
            'global' => 'edit',
            'apps' => ['myfood' => 'admin', 'fit' => 'read'],
        ]);

        $this->get('/auth/sso/callback');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'role' => 'admin']);
    }

    public function test_local_password_login_no_longer_exists(): void
    {
        $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'secret'])
            ->assertStatus(405);

        $this->assertGuest();
    }

    public function test_logout_ends_session_and_points_to_sso_logout(): void
    {
        config([
            'services.jepflow_sso.base_url' => 'https://sso.jepflow.io',
            'services.jepflow_sso.client_id' => 'myfood-client',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('redirect', 'https://sso.jepflow.io/logout/client?'.http_build_query([
                'client_id' => 'myfood-client',
                'redirect_uri' => url('/'),
            ]));

        $this->assertGuest();
    }

    public function test_guest_user_endpoint_returns_null_user(): void
    {
        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user', null);
    }

    public function test_guests_cannot_write_recipes(): void
    {
        $this->postJson('/api/recipes', ['title' => 'Salad', 'ingredients' => [], 'steps' => []])
            ->assertUnauthorized();
    }
}
