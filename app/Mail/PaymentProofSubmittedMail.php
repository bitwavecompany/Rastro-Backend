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

class PaymentProofSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva solicitud Premium: '.$this->user->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.premium_request',
            with: [
                'user' => $this->user,
                'dateFormatted' => DateFormatter::format($this->user->updated_at ?? now()),
            ],
        );
    }
}
