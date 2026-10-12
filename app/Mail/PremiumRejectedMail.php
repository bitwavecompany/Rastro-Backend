<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Support\DateFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PremiumRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Actualización sobre tu solicitud Rastro Premium',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.premium_rejected',
            with: [
                'user' => $this->user,
                'reason' => $this->reason,
                'dateFormatted' => DateFormatter::format(now()),
            ],
        );
    }
}
