<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserSessionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scopes_active_and_revoked(): void
    {
        $user = User::factory()->create();

        $activeSession = UserSession::create([
            'user_id' => $user->id,
            'device_name' => 'Active Device',
            'refresh_token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(10),
            'revoked_at' => null,
        ]);

        $revokedSession = UserSession::create([
            'user_id' => $user->id,
            'device_name' => 'Revoked Device',
            'refresh_token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(10),
            'revoked_at' => now()->subHour(),
        ]);

        $expiredSession = UserSession::create([
            'user_id' => $user->id,
            'device_name' => 'Expired Device',
            'refresh_token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->subDay(),
            'revoked_at' => null,
        ]);

        $activeIds = UserSession::active()->pluck('id')->all();
        $this->assertContains($activeSession->id, $activeIds);
        $this->assertNotContains($revokedSession->id, $activeIds);
        $this->assertNotContains($expiredSession->id, $activeIds);

        $revokedIds = UserSession::revoked()->pluck('id')->all();
        $this->assertContains($revokedSession->id, $revokedIds);
        $this->assertNotContains($activeSession->id, $revokedIds);
    }
}
