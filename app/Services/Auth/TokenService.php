<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Models\User;
use App\Models\UserSession;
use Exception;
use Illuminate\Support\Str;

class TokenService
{
    /**
     * @return array{token: string, access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function issueTokens(User $user, ?string $deviceName = null, ?string $ipAddress = null): array
    {
        // 1. Emitir Access Token con expiración de 60 minutos (3600 segundos) (Regla 14)
        $newAccessToken = $user->createToken(
            name: $deviceName ?? 'access_token',
            abilities: ['*'],
            expiresAt: now()->addMinutes(60)
        );

        $accessTokenString = $newAccessToken->plainTextToken;
        $accessTokenId = $newAccessToken->accessToken->id;

        // 2. Generar Refresh Token criptográfico con expiración de 30 días (Regla 14)
        $refreshToken = Str::random(64);
        $refreshTokenHash = hash('sha256', $refreshToken);

        // 3. Registrar sesión activa del dispositivo
        UserSession::create([
            'user_id' => $user->id,
            'device_name' => $deviceName,
            'refresh_token_hash' => $refreshTokenHash,
            'access_token_id' => $accessTokenId,
            'ip_address' => $ipAddress,
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ]);

        return [
            'token' => $accessTokenString,
            'access_token' => $accessTokenString,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ];
    }

    /**
     * @return array{token: string, access_token: string, refresh_token: string, token_type: string, expires_in: int, user: User}
     */
    public function refreshToken(string $refreshToken, ?string $ipAddress = null): array
    {
        $hash = hash('sha256', $refreshToken);

        /** @var UserSession|null $session */
        $session = UserSession::where('refresh_token_hash', $hash)->first();

        if (! $session) {
            throw new Exception(ApiMessage::INVALID_REFRESH_TOKEN, HttpCode::UNAUTHORIZED);
        }

        if ($session->revoked_at !== null) {
            throw new Exception(ApiMessage::SESSION_REVOKED_REMOTE, HttpCode::UNAUTHORIZED);
        }

        if ($session->expires_at->isPast()) {
            throw new Exception(ApiMessage::INVALID_REFRESH_TOKEN, HttpCode::UNAUTHORIZED);
        }

        $user = $session->user;

        // Revocar token de acceso previo si existe
        if ($session->access_token_id) {
            $user->tokens()->where('id', $session->access_token_id)->delete();
        }

        // Emitir nuevo Access Token
        $newAccessToken = $user->createToken(
            name: $session->device_name ?? 'access_token',
            abilities: ['*'],
            expiresAt: now()->addMinutes(60)
        );

        // Rotar Refresh Token
        $newRefreshToken = Str::random(64);
        $newHash = hash('sha256', $newRefreshToken);

        $session->update([
            'refresh_token_hash' => $newHash,
            'access_token_id' => $newAccessToken->accessToken->id,
            'expires_at' => now()->addDays(30),
            'ip_address' => $ipAddress ?? $session->ip_address,
        ]);

        return [
            'token' => $newAccessToken->plainTextToken,
            'access_token' => $newAccessToken->plainTextToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => $user,
        ];
    }

    /**
     * Invalida las sesiones activas de un usuario, opcionalmente excluyendo una sesión específica (Reglas 13 y 15)
     */
    public function revokeActiveSessions(User $user, ?string $exceptSessionId = null): void
    {
        $query = $user->sessions()->whereNull('revoked_at');

        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->update([
            'revoked_at' => now(),
        ]);
    }

    /**
     * Invalida todas las sesiones de un usuario (Regla 15)
     */
    public function revokeAllUserSessions(User $user): void
    {
        $this->revokeActiveSessions($user);
    }

    /**
     * Invalida las sesiones existentes cuando un usuario Free confirma sesión en 2do dispositivo (Reglas 13 y 15)
     */
    public function revokeOtherSessions(User $user, ?string $exceptSessionId = null): void
    {
        $this->revokeActiveSessions($user, $exceptSessionId);
    }
}
