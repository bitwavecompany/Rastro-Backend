<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\PremiumRequestStatus;
use App\Enums\UserPlan;
use App\Mail\PremiumActivatedMail;
use App\Models\PremiumRequest;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ApprovePremiumRequestAction
{
    public function __construct(
        private readonly TokenService $tokenService
    ) {}

    public function handle(PremiumRequest $premiumRequest, User $admin): PremiumRequest
    {
        $user = $premiumRequest->user;

        $premiumRequest->update([
            'status' => PremiumRequestStatus::Approved,
            'payment_verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        $user->update([
            'plan' => UserPlan::Premium,
            'payment_method' => $premiumRequest->payment_method,
            'payment_bank' => $premiumRequest->origin_bank,
            'payment_receipt' => $premiumRequest->transfer_receipt,
        ]);

        $this->tokenService->revokeAllUserSessions($user);

        try {
            Mail::to($user->email)->send(new PremiumActivatedMail($user));
        } catch (Throwable) {
        }

        return $premiumRequest->fresh();
    }
}
