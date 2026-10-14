<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\PremiumRequest;
use App\Models\User;
use App\Support\DateFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentProofSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?PremiumRequest $premiumRequest = null
    ) {}

    public function envelope(): Envelope
    {
        $userName = trim((string) ($this->user->name ?? ''));
        $userEmail = (string) ($this->user->email ?? 'usuario@rastro.app');
        $identifier = $userName !== '' ? "{$userName} ({$userEmail})" : $userEmail;

        $bank = (string) ($this->premiumRequest?->origin_bank ?? $this->user->payment_bank ?? 'Banco no especificado');
        $receipt = (string) ($this->premiumRequest?->transfer_receipt ?? $this->user->payment_receipt ?? 'S/N');

        return new Envelope(
            subject: "[Rastro Admin] Nueva Solicitud Premium: {$identifier} - {$bank} (Comp. #{$receipt})",
        );
    }

    public function content(): Content
    {
        $bank = (string) ($this->premiumRequest?->origin_bank ?? $this->user->payment_bank ?? 'No especificado');
        $receipt = (string) ($this->premiumRequest?->transfer_receipt ?? $this->user->payment_receipt ?? 'No especificado');
        $rawMethod = $this->premiumRequest?->payment_method ?? $this->user->payment_method;
        $methodValue = $rawMethod instanceof \BackedEnum ? $rawMethod->value : (string) $rawMethod;

        $dateFormatted = DateFormatter::format($this->premiumRequest?->created_at ?? $this->user->updated_at ?? now());
        $adminUrl = (string) config('mail.admin_dashboard_url', 'https://rastro-ec.vercel.app/admin/login');

        return new Content(
            view: 'emails.premium_request',
            with: [
                'user' => $this->user,
                'premiumRequest' => $this->premiumRequest,
                'bank' => $bank,
                'receipt' => $receipt,
                'methodValue' => $methodValue,
                'dateFormatted' => $dateFormatted,
                'adminUrl' => $adminUrl,
            ],
        );
    }
}
