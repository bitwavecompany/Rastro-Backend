<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Actions\Accounts\EnsureDefaultAccountAction;
use App\Constants\ApiMessage;
use App\DTOs\Auth\LoginData;
use App\Enums\UserPlan;
use App\Exceptions\SessionLimitReachedException;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginUserAction
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly EnsureDefaultAccountAction $ensureDefaultAccount = new EnsureDefaultAccountAction
    ) {}

    /**
     * @return array{user: User, tokenData: array{token: string, access_token: string, refresh_token: string, token_type: string, expires_in: int}}
     *
     * @throws ValidationException
     * @throws SessionLimitReachedException
     */
    public function handle(LoginData $data, ?string $ipAddress = null): array
    {
        /** @var User|null $user */
        $user = User::where('email', $data->email)->first();

        if (! $user || ! Hash::check($data->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [ApiMessage::INVALID_CREDENTIALS],
            ]);
        }

        // Asegurar cuenta por defecto (SSOT)
        $this->ensureDefaultAccount->handle($user);

        // Reglas 12 y 13: Control de concurrencia de sesiones en Plan Free
        if ($user->plan === UserPlan::Free) {
            $activeSessionsCount = $user->sessions()
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->count();

            if ($activeSessionsCount >= 1) {
                if (! $data->force) {
                    throw new SessionLimitReachedException;
                }

                // Usuario confirma inicio en segundo dispositivo: invalidar sesiones previas (Reglas 13 y 15)
                $this->tokenService->revokeOtherSessions($user);
            }
        }

        $tokenData = $this->tokenService->issueTokens(
            $user,
            $data->deviceName,
            $ipAddress
        );

        return [
            'user' => $user,
            'tokenData' => $tokenData,
        ];
    }
}
