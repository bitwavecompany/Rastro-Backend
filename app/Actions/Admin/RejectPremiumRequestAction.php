<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\DTOs\Admin\RejectPremiumRequestData;
use App\Enums\PremiumRequestStatus;
use App\Mail\PremiumRejectedMail;
use App\Models\PremiumRequest;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class RejectPremiumRequestAction
{
    public function __construct(
        private readonly TokenService $tokenService
    ) {}

    public function handle(PremiumRequest $premiumRequest, RejectPremiumRequestData $data, User $admin): PremiumRequest
    {
        $user = $premiumRequest->user;

        $premiumRequest->update([
            'status' => PremiumRequestStatus::Rejected,
            'rejection_reason' => $data->rejectionReason,
            'payment_verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        $this->tokenService->revokeAllUserSessions($user);

        try {
            Mail::to($user->email)->send(new PremiumRejectedMail($user, $data->rejectionReason));
        } catch (Throwable) {
        }

        return $premiumRequest->fresh();
    }
}
