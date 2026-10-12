<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\UpgradePremiumData;
use App\Enums\PremiumRequestStatus;
use App\Mail\PaymentProofSubmittedMail;
use App\Models\PremiumRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

final class RequestPremiumUpgradeAction
{
    public function handle(User $user, UpgradePremiumData $data): PremiumRequest
    {
        $premiumRequest = PremiumRequest::create([
            'user_id' => $user->id,
            'status' => PremiumRequestStatus::PendingVerification,
            'payment_method' => $data->paymentMethod,
            'origin_bank' => $data->originBank,
            'transfer_receipt' => $data->transferReceipt,
        ]);

        $user->update([
            'payment_method' => $data->paymentMethod,
            'payment_bank' => $data->originBank,
            'payment_receipt' => $data->transferReceipt,
        ]);

        try {
            $adminEmail = (string) config('mail.admin_address', 'bitwave.company@gmail.com');
            Mail::to($adminEmail)->send(new PaymentProofSubmittedMail($user));
        } catch (\Throwable) {
            // Ignorar fallas de envío de email en entorno de prueba / local
        }

        return $premiumRequest;
    }
}
