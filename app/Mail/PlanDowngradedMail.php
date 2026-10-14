<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use App\Support\DateFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PlanDowngradedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notificación de cambio a plan Free - Rastro',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.plan_downgraded',
            with: [
                'user' => $this->user,
                'dateFormatted' => DateFormatter::formatDateTime(now()),
            ]
        );
    }
}
