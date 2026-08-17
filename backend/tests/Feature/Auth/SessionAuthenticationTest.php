<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function withCsrfToken(): self
    {
        return $this
            ->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->withHeader('Origin', 'http://localhost:5173');
    }

    public function test_csrf_cookie_endpoint_is_public(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173')
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent();
    }

    public function test_login_creates_authenticated_session(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario@example.com',
            'password' => Hash::make('senha-segura'),
        ]);

        $this->withCsrfToken()
            ->postJson('/api/login', [
                'email' => 'usuario@example.com',
                'password' => 'senha-segura',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'usuario@example.com');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_invalid_credentials_with_generic_message(): void
    {
        User::factory()->create([
            'email' => 'usuario@example.com',
            'password' => Hash::make('senha-segura'),
        ]);

        $this->withCsrfToken()
            ->postJson('/api/login', [
                'email' => 'usuario@example.com',
                'password' => 'senha-incorreta',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'As credenciais informadas sao invalidas.');
    }

    public function test_user_route_requires_authentication(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_horizon_api_requires_authentication(): void
    {
        $this->getJson('/horizon/api/stats')
            ->assertForbidden();
    }

    public function test_authenticated_user_can_access_horizon_api(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/horizon/api/stats')
            ->assertOk();
    }

    public function test_authenticated_user_can_read_session_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'usuario@example.com',
            'password' => Hash::make('senha-segura'),
        ]);

        $this->withCsrfToken()
            ->postJson('/api/login', [
                'email' => 'usuario@example.com',
                'password' => 'senha-segura',
            ])
            ->assertOk();

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);

        $this->withCsrfToken()
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sessao encerrada.');

        $this->getJson('/api/user')
            ->assertUnauthorized();
    }
}
