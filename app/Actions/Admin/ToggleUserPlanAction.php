<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\DTOs\Admin\TogglePlanData;
use App\Enums\PremiumRequestStatus;
use App\Enums\UserPlan;
use App\Mail\PlanDowngradedMail;
use App\Mail\PremiumActivatedMail;
use App\Models\PremiumRequest;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ToggleUserPlanAction
{
    public function __construct(
        private readonly TokenService $tokenService
    ) {}

    public function handle(User $user, TogglePlanData $data): User
    {
        $previousPlan = $user->plan;

        $updateData = [
            'plan' => $data->plan,
            'payment_method' => $data->plan === UserPlan::Free ? null : $data->paymentMethod,
            'payment_bank' => $data->plan === UserPlan::Free ? null : $data->paymentBank,
            'payment_receipt' => $data->plan === UserPlan::Free ? null : $data->paymentReceipt,
        ];

        $user->update($updateData);

        if ($data->plan === UserPlan::Premium) {
            PremiumRequest::where('user_id', $user->id)
                ->where('status', PremiumRequestStatus::PendingVerification)
                ->update([
                    'status' => PremiumRequestStatus::Approved,
                    'verified_at' => now(),
                ]);
        } elseif ($data->plan === UserPlan::Free) {
            PremiumRequest::where('user_id', $user->id)
                ->where('status', PremiumRequestStatus::PendingVerification)
                ->update([
                    'status' => PremiumRequestStatus::Rejected,
                    'rejection_reason' => 'Plan cambiado a Free por el administrador',
                    'verified_at' => now(),
                ]);
        }

        if ($user->wasChanged('plan')) {
            $this->tokenService->revokeAllUserSessions($user);
        }

        try {
            if ($data->plan === UserPlan::Free && $previousPlan === UserPlan::Premium) {
                Mail::to($user->email)->send(new PlanDowngradedMail($user));
            } elseif ($data->plan === UserPlan::Premium && $previousPlan === UserPlan::Free) {
                Mail::to($user->email)->send(new PremiumActivatedMail($user));
            }
        } catch (Throwable) {
        }

        return $user->fresh();
    }
}
