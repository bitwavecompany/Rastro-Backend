<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Mail\PaymentProofSubmittedMail;
use App\Mail\PlanDowngradedMail;
use App\Mail\PremiumActivatedMail;
use App\Mail\PremiumRejectedMail;
use App\Models\User;
use App\Support\DateFormatter;
use Tests\TestCase;

class MailDateFormatTest extends TestCase
{
    public function test_mailables_render_dates_according_to_prd_rule_11(): void
    {
        $user = new User([
            'name' => 'Ana Gomez',
            'email' => 'ana@example.com',
            'payment_method' => 'transfer',
            'payment_bank' => 'Banco Guayaquil',
            'payment_receipt' => 'REC-12345',
        ]);
        $expectedDate = DateFormatter::format(now());

        // 1. PaymentProofSubmittedMail
        $mail1 = new PaymentProofSubmittedMail($user);
        $html1 = $mail1->render();
        $this->assertStringContainsString('Fecha de Solicitud:', $html1);
        $this->assertStringContainsString($expectedDate, $html1);

        // 2. PremiumActivatedMail
        $mail2 = new PremiumActivatedMail($user);
        $html2 = $mail2->render();
        $this->assertStringContainsString('Fecha de activación:', $html2);
        $this->assertStringContainsString($expectedDate, $html2);

        // 3. PremiumRejectedMail
        $mail3 = new PremiumRejectedMail($user, 'Comprobante borroso');
        $html3 = $mail3->render();
        $this->assertStringContainsString('Fecha de revisión:', $html3);
        $this->assertStringContainsString($expectedDate, $html3);

        // 4. PlanDowngradedMail
        $mail4 = new PlanDowngradedMail($user);
        $html4 = $mail4->render();
        $this->assertStringContainsString('Fecha efectiva del cambio:', $html4);
        $this->assertStringContainsString($expectedDate, $html4);
    }
}
