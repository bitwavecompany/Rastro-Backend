<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Auth\TokenService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private TokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenService = new TokenService;
    }

    public function test_issues_tokens_and_creates_active_session(): void
    {
        $user = User::factory()->create();

        $tokens = $this->tokenService->issueTokens($user, 'Test Device', '127.0.0.1');

        $this->assertArrayHasKey('token', $tokens);
        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertEquals('Bearer', $tokens['token_type']);
        $this->assertEquals(3600, $tokens['expires_in']);

        $session = UserSession::where('user_id', $user->id)->first();
        $this->assertNotNull($session);
        $this->assertEquals('Test Device', $session->device_name);
        $this->assertEquals('127.0.0.1', $session->ip_address);
        $this->assertNull($session->revoked_at);
        $this->assertTrue($session->isActive());
    }

    public function test_rotates_refresh_token_on_refresh(): void
    {
        $user = User::factory()->create();
        $initialTokens = $this->tokenService->issueTokens($user, 'Pixel 8');

        $refreshedTokens = $this->tokenService->refreshToken($initialTokens['refresh_token']);

        $this->assertNotEquals($initialTokens['refresh_token'], $refreshedTokens['refresh_token']);
        $this->assertNotEquals($initialTokens['access_token'], $refreshedTokens['access_token']);
        $this->assertEquals($user->id, $refreshedTokens['user']->id);
    }

    public function test_revoke_active_sessions_revokes_all(): void
    {
        $user = User::factory()->create();
        $this->tokenService->issueTokens($user, 'Device 1');
        $this->tokenService->issueTokens($user, 'Device 2');

        $this->assertEquals(2, $user->sessions()->active()->count());

        $this->tokenService->revokeActiveSessions($user);

        $this->assertEquals(0, $user->sessions()->active()->count());
        $this->assertEquals(2, $user->sessions()->revoked()->count());
    }

    public function test_revoke_active_sessions_can_exclude_current_session(): void
    {
        $user = User::factory()->create();
        $this->tokenService->issueTokens($user, 'Device 1');
        $this->tokenService->issueTokens($user, 'Device 2');

        /** @var UserSession $keepSession */
        $keepSession = $user->sessions()->first();

        $this->tokenService->revokeActiveSessions($user, $keepSession->id);

        $this->assertTrue($keepSession->fresh()->isActive());
        $this->assertEquals(1, $user->sessions()->active()->count());
        $this->assertEquals(1, $user->sessions()->revoked()->count());
    }

    public function test_revoked_session_cannot_refresh(): void
    {
        $user = User::factory()->create();
        $tokens = $this->tokenService->issueTokens($user);

        $this->tokenService->revokeAllUserSessions($user);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(ApiMessage::SESSION_REVOKED_REMOTE);
        $this->expectExceptionCode(HttpCode::UNAUTHORIZED);

        $this->tokenService->refreshToken($tokens['refresh_token']);
    }
}
