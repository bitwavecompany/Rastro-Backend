<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Enums\UserPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_dual_tokens_and_registers_session(): void
    {
        $user = User::factory()->create([
            'email' => 'free@rastro.app',
            'password' => bcrypt('password123'),
            'plan' => UserPlan::Free,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'free@rastro.app',
            'password' => 'password123',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertStatus(HttpCode::OK)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_in',
                    'user',
                ],
            ]);

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $user->id,
            'device_name' => 'Pixel 8',
            'revoked_at' => null,
        ]);
    }

    public function test_refresh_token_rotates_and_returns_new_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'free@rastro.app',
            'password' => bcrypt('password123'),
            'plan' => UserPlan::Free,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'free@rastro.app',
            'password' => 'password123',
            'device_name' => 'Pixel 8',
        ]);

        $refreshToken = $loginResponse->json('data.refresh_token');

        $refreshResponse = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $refreshResponse->assertStatus(HttpCode::OK)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'access_token',
                    'refresh_token',
                    'user',
                ],
            ]);

        $this->assertNotEquals($refreshToken, $refreshResponse->json('data.refresh_token'));
    }

    public function test_free_plan_blocks_second_concurrent_device_without_force(): void
    {
        $user = User::factory()->create([
            'email' => 'free@rastro.app',
            'password' => bcrypt('password123'),
            'plan' => UserPlan::Free,
        ]);

        // Primera sesión en Dispositivo A
        $this->postJson('/api/auth/login', [
            'email' => 'free@rastro.app',
            'password' => 'password123',
            'device_name' => 'Device A',
        ])->assertStatus(HttpCode::OK);

        // Intento en Dispositivo B sin force
        $response = $this->postJson('/api/auth/login', [
            'email' => 'free@rastro.app',
            'password' => 'password123',
            'device_name' => 'Device B',
        ]);

        $response->assertStatus(HttpCode::CONFLICT)
            ->assertJsonPath('code', 'SESSION_LIMIT_REACHED')
            ->assertJsonPath('message', ApiMessage::SESSION_LIMIT_REACHED);
    }

    public function test_free_plan_login_with_force_revokes_previous_session(): void
    {
        $user = User::factory()->create([
            'email' => 'free@rastro.app',
            'password' => bcrypt('password123'),
            'plan' => UserPlan::Free,
        ]);

        // Dispositivo A inicia sesión
        $respA = $this->postJson('/api/auth/login', [
            'email' => 'free@rastro.app',
            'password' => 'password123',
            'device_name' => 'Device A',
        ]);
        $tokenA = $respA->json('data.access_token');
        $refreshTokenA = $respA->json('data.refresh_token');

        // Dispositivo B inicia sesión con force: true
        $respB = $this->postJson('/api/auth/login', [
            'email' => 'free@rastro.app',
            'password' => 'password123',
            'device_name' => 'Device B',
            'force' => true,
        ]);
        $respB->assertStatus(HttpCode::OK);

        // Dispositivo A realiza una petición con su access token -> 401 SESSION_REVOKED_REMOTE
        $responseAAfter = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/auth/me');

        $responseAAfter->assertStatus(HttpCode::UNAUTHORIZED)
            ->assertJsonPath('code', 'SESSION_REVOKED_REMOTE')
            ->assertJsonPath('message', ApiMessage::SESSION_REVOKED_REMOTE);

        // Dispositivo A intenta refrescar token -> 401 SESSION_REVOKED_REMOTE
        $refreshRespA = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $refreshTokenA,
        ]);

        $refreshRespA->assertStatus(HttpCode::UNAUTHORIZED)
            ->assertJsonPath('code', 'SESSION_REVOKED_REMOTE');
    }

    public function test_premium_plan_allows_multiple_concurrent_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'premium@rastro.app',
            'password' => bcrypt('password123'),
            'plan' => UserPlan::Premium,
        ]);

        $respA = $this->postJson('/api/auth/login', [
            'email' => 'premium@rastro.app',
            'password' => 'password123',
            'device_name' => 'Device A',
        ]);
        $respA->assertStatus(HttpCode::OK);

        $respB = $this->postJson('/api/auth/login', [
            'email' => 'premium@rastro.app',
            'password' => 'password123',
            'device_name' => 'Device B',
        ]);
        $respB->assertStatus(HttpCode::OK);
    }
}
