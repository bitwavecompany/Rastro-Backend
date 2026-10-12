<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PremiumRequestStatus;
use App\Enums\UserPlan;
use App\Enums\UserRole;
use App\Mail\PlanDowngradedMail;
use App\Models\PremiumRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_cliente_normal_no_puede_acceder_al_panel_admin(): void
    {
        /** @var User $client */
        $client = User::factory()->create(['role' => UserRole::Client]);

        $response = $this->actingAs($client)->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_un_admin_puede_ver_la_lista_de_clientes(): void
    {
        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        User::factory()->create(['role' => UserRole::Client, 'name' => 'Cliente Uno']);

        $response = $this->actingAs($admin)->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Cliente Uno');
    }

    public function test_un_admin_puede_cambiar_el_plan_a_premium_con_pago(): void
    {
        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $client */
        $client = User::factory()->create(['role' => UserRole::Client, 'plan' => UserPlan::Free]);

        $payload = [
            'plan' => 'premium',
            'payment_method' => 'transfer',
            'payment_bank' => 'Pichincha',
            'payment_receipt' => '123456789',
        ];

        $response = $this->actingAs($admin)->patchJson("/api/admin/users/{$client->id}/plan", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.plan', 'premium');

        $this->assertDatabaseHas('users', [
            'id' => $client->id,
            'plan' => 'premium',
            'payment_method' => 'transfer',
            'payment_bank' => 'Pichincha',
            'payment_receipt' => '123456789',
        ]);
    }

    public function test_un_admin_puede_aprobar_solicitud_premium(): void
    {
        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $client */
        $client = User::factory()->create(['role' => UserRole::Client, 'plan' => UserPlan::Free]);

        $premiumRequest = PremiumRequest::create([
            'user_id' => $client->id,
            'status' => PremiumRequestStatus::PendingVerification,
            'payment_method' => PaymentMethod::Transfer,
            'origin_bank' => 'Banco de Guayaquil',
            'transfer_receipt' => 'REC-999',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/admin/premium-requests/{$premiumRequest->id}/approve");

        $response->assertStatus(200);

        $this->assertDatabaseHas('premium_requests', [
            'id' => $premiumRequest->id,
            'status' => 'approved',
            'payment_verified_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $client->id,
            'plan' => 'premium',
        ]);
    }

    public function test_un_admin_puede_rechazar_solicitud_premium_con_motivo(): void
    {
        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $client */
        $client = User::factory()->create(['role' => UserRole::Client, 'plan' => UserPlan::Free]);

        $premiumRequest = PremiumRequest::create([
            'user_id' => $client->id,
            'status' => PremiumRequestStatus::PendingVerification,
            'payment_method' => PaymentMethod::Transfer,
            'origin_bank' => 'Banco Pichincha',
            'transfer_receipt' => 'REC-000',
        ]);

        $response = $this->actingAs($admin)->postJson("/api/admin/premium-requests/{$premiumRequest->id}/reject", [
            'rejection_reason' => 'Comprobante ilegible o no encontrado en cuenta.',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('premium_requests', [
            'id' => $premiumRequest->id,
            'status' => 'rejected',
            'rejection_reason' => 'Comprobante ilegible o no encontrado en cuenta.',
            'payment_verified_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $client->id,
            'plan' => 'free',
        ]);
    }

    public function test_un_admin_puede_registrar_pago_en_efectivo_presencial(): void
    {
        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $client */
        $client = User::factory()->create(['role' => UserRole::Client, 'plan' => UserPlan::Free]);

        $response = $this->actingAs($admin)->postJson("/api/admin/users/{$client->id}/manual-upgrade", [
            'payment_notes' => 'Pago recibido en efectivo en oficina central el 10/10/2026',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.plan', 'premium');

        $this->assertDatabaseHas('premium_requests', [
            'user_id' => $client->id,
            'status' => 'approved',
            'payment_method' => 'cash',
            'payment_notes' => 'Pago recibido en efectivo en oficina central el 10/10/2026',
            'payment_verified_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $client->id,
            'plan' => 'premium',
            'payment_method' => 'cash',
        ]);
    }

    public function test_un_admin_puede_degradar_plan_a_free_y_notifica_por_correo(): void
    {
        Mail::fake();

        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $client */
        $client = User::factory()->premium()->create();

        $payload = [
            'plan' => 'free',
        ];

        $response = $this->actingAs($admin)->patchJson("/api/admin/users/{$client->id}/plan", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.plan', 'free');

        $this->assertDatabaseHas('users', [
            'id' => $client->id,
            'plan' => 'free',
        ]);

        Mail::assertSent(PlanDowngradedMail::class, function ($mail) use ($client) {
            return $mail->hasTo($client->email);
        });
    }

    public function test_un_admin_puede_listar_solicitudes_premium_pendientes(): void
    {
        /** @var User $admin */
        $admin = User::factory()->admin()->create();

        /** @var User $client1 */
        $client1 = User::factory()->create(['name' => 'Cliente Pendiente 1', 'role' => UserRole::Client]);
        /** @var User $client2 */
        $client2 = User::factory()->create(['name' => 'Cliente Pendiente 2', 'role' => UserRole::Client]);
        /** @var User $client3 */
        $client3 = User::factory()->create(['name' => 'Cliente Aprobado', 'role' => UserRole::Client]);

        PremiumRequest::create([
            'user_id' => $client1->id,
            'status' => PremiumRequestStatus::PendingVerification,
            'payment_method' => PaymentMethod::Transfer,
            'origin_bank' => 'Banco Guayaquil',
            'transfer_receipt' => 'REC-111',
        ]);

        PremiumRequest::create([
            'user_id' => $client2->id,
            'status' => PremiumRequestStatus::PendingVerification,
            'payment_method' => PaymentMethod::Transfer,
            'origin_bank' => 'Banco Pichincha',
            'transfer_receipt' => 'REC-222',
        ]);

        // Solicitud ya aprobada no debe aparecer en la cola de pendientes
        PremiumRequest::create([
            'user_id' => $client3->id,
            'status' => PremiumRequestStatus::Approved,
            'payment_method' => PaymentMethod::Transfer,
            'origin_bank' => 'Banco Pacífico',
            'transfer_receipt' => 'REC-333',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/premium-requests');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'status',
                        'payment_method',
                        'origin_bank',
                        'transfer_receipt',
                        'user' => ['id', 'name', 'email'],
                    ],
                ],
                'meta' => ['page', 'per_page', 'total'],
            ]);

        // Cliente común recibe 403
        $this->actingAs($client1)->getJson('/api/admin/premium-requests')->assertStatus(403);
    }
}
