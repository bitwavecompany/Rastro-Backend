<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\UpgradePremiumData;
use App\Enums\PremiumRequestStatus;
use App\Mail\PaymentProofSubmittedMail;
use App\Models\PremiumRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class RequestPremiumUpgradeAction
{
    public function handle(User $user, UpgradePremiumData $data): PremiumRequest
    {
        PremiumRequest::where('user_id', $user->id)
            ->where('status', PremiumRequestStatus::PendingVerification)
            ->update([
                'status' => PremiumRequestStatus::Rejected,
                'rejection_reason' => 'Reemplazada por nueva solicitud',
                'verified_at' => now(),
            ]);

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
            Log::info("Enviando correo de solicitud Premium a {$adminEmail} para el usuario {$user->id}...");
            Mail::to($adminEmail)->send(new PaymentProofSubmittedMail($user));
            Log::info("Correo de solicitud Premium enviado exitosamente a {$adminEmail} para el usuario {$user->id}");
        } catch (\Throwable $e) {
            Log::error("Fallo al enviar correo de solicitud Premium: {$e->getMessage()}", [
                'user_id' => $user->id,
                'admin_email' => config('mail.admin_address', 'bitwave.company@gmail.com'),
                'exception' => $e,
            ]);
            if (config('app.debug')) {
                report($e);
            }
        }

        return $premiumRequest;
    }
}
