<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\DTOs\Admin\ManualCashUpgradeData;
use App\Enums\PaymentMethod;
use App\Enums\PremiumRequestStatus;
use App\Enums\UserPlan;
use App\Mail\PremiumActivatedMail;
use App\Models\PremiumRequest;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ManualCashUpgradeAction
{
    public function __construct(
        private readonly TokenService $tokenService
    ) {}

    public function handle(User $user, ManualCashUpgradeData $data, User $admin): User
    {
        DB::transaction(function () use ($user, $data, $admin): void {
            PremiumRequest::create([
                'user_id' => $user->id,
                'status' => PremiumRequestStatus::Approved,
                'payment_method' => PaymentMethod::Cash,
                'payment_notes' => $data->paymentNotes,
                'payment_verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            $user->update([
                'plan' => UserPlan::Premium,
                'payment_method' => PaymentMethod::Cash,
            ]);
        });

        $this->tokenService->revokeAllUserSessions($user);

        try {
            Mail::to($user->email)->send(new PremiumActivatedMail($user));
        } catch (Throwable) {
        }

        return $user->fresh();
    }
}
