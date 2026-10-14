<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\PremiumRequestStatus;
use App\Enums\TransactionKind;
use App\Enums\UserPlan;
use App\Enums\UserRole;
use App\Mail\PaymentProofSubmittedMail;
use App\Models\Account;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserProfileLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_endpoint_returns_operational_status(): void
    {
        $response = $this->getJson('/api/ping');

        $response->assertStatus(HttpCode::OK)
            ->assertJson([
                'status' => 'Rastro API V1 Operational',
            ]);
    }

    public function test_me_endpoint_returns_authenticated_user_profile(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Carlos Andrade',
            'email' => 'carlos@ejemplo.com',
            'plan' => UserPlan::Free,
            'role' => UserRole::Client,
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me');

        $response->assertStatus(HttpCode::OK)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Carlos Andrade')
            ->assertJsonPath('data.email', 'carlos@ejemplo.com')
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.role', 'client')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'plan',
                    'role',
                    'payment_method',
                    'payment_bank',
                    'payment_receipt',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_update_me_updates_user_name_and_returns_updated_resource(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Nombre Anterior',
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/auth/me', [
                'name' => 'Juan Carlos Pérez',
            ]);

        $response->assertStatus(HttpCode::OK)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Juan Carlos Pérez');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Juan Carlos Pérez',
        ]);
    }

    public function test_update_me_validates_required_name(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/auth/me', [
                'name' => '',
            ]);

        $response->assertStatus(HttpCode::UNPROCESSABLE_ENTITY)
            ->assertJsonPath('success', false);
    }

    public function test_logout_invalidates_current_token_and_marks_session_as_revoked(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'logout@rastro.app',
            'password' => bcrypt('password123'),
        ]);

        // Login normal crea tokens y user_session vinculada
        $loginResp = $this->postJson('/api/auth/login', [
            'email' => 'logout@rastro.app',
            'password' => 'password123',
            'device_name' => 'Device To Logout',
        ]);

        $accessToken = $loginResp->json('data.access_token');

        $logoutResp = $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->postJson('/api/auth/logout');

        $logoutResp->assertStatus(HttpCode::OK)
            ->assertJsonPath('success', true);

        // La sesión en base de datos debe marcarse con revoked_at
        $session = UserSession::where('user_id', $user->id)
            ->where('device_name', 'Device To Logout')
            ->first();

        $this->assertNotNull($session);
        $this->assertNotNull($session->revoked_at);

        // Peticiones subsiguientes con ese token deben fallar con 401
        $subsequentResp = $this->withHeader('Authorization', 'Bearer '.$accessToken)
            ->getJson('/api/auth/me');

        $subsequentResp->assertStatus(HttpCode::UNAUTHORIZED);
    }

    public function test_delete_me_purges_user_and_all_associated_data_permanently(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = $user->createToken('device')->plainTextToken;

        /** @var Account $account */
        $account = $user->accounts()->create([
            'name' => 'Cuenta a Purgar',
            'type' => AccountType::Cash,
            'amount' => 100.0,
            'is_default' => true,
        ]);

        $tx = $user->transactions()->create([
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'amount' => 20.0,
            'reason' => 'Movimiento a purgar',
            'paymentType' => 'cash',
        ]);

        $session = UserSession::create([
            'user_id' => $user->id,
            'device_name' => 'Session to purge',
            'refresh_token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/auth/me');

        $response->assertStatus(HttpCode::OK)
            ->assertJsonPath('success', true);

        // Verificación de purga física permanente (forceDelete)
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
        $this->assertDatabaseMissing('user_sessions', ['id' => $session->id]);
    }

    public function test_delete_me_rejects_admin_user_with_forbidden(): void
    {
        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('admin-device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/auth/me');

        $response->assertStatus(HttpCode::FORBIDDEN)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_upgrade_request_creates_pending_request_and_queues_email(): void
    {
        Mail::fake();

        /** @var User $user */
        $user = User::factory()->create([
            'plan' => UserPlan::Free,
        ]);

        $token = $user->createToken('device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/upgrade-request', [
                'origin_bank' => 'Banco Pichincha',
                'transfer_receipt' => 'REC-987654321',
            ]);

        $response->assertStatus(HttpCode::OK)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessage::PREMIUM_REQUEST_SUBMITTED)
            ->assertJsonPath('data.plan', 'free'); // El plan permanece en free hasta que el admin apruebe

        $this->assertDatabaseHas('premium_requests', [
            'user_id' => $user->id,
            'origin_bank' => 'Banco Pichincha',
            'transfer_receipt' => 'REC-987654321',
            'status' => PremiumRequestStatus::PendingVerification->value,
            'payment_method' => PaymentMethod::Transfer->value,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'payment_bank' => 'Banco Pichincha',
            'payment_receipt' => 'REC-987654321',
        ]);

        Mail::assertSent(PaymentProofSubmittedMail::class);
    }

    public function test_payment_proof_submitted_mail_renders_admin_dashboard_url(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Juan Perez',
            'email' => 'juan@ejemplo.com',
        ]);

        $premiumRequest = PremiumRequest::create([
            'user_id' => $user->id,
            'origin_bank' => 'Banco Guayaquil',
            'transfer_receipt' => 'REC-12345',
            'status' => PremiumRequestStatus::PendingVerification,
            'payment_method' => PaymentMethod::Transfer,
        ]);

        $mailable = new PaymentProofSubmittedMail($user, $premiumRequest);

        $mailable->assertSeeInHtml('https://rastro-ec.vercel.app/admin/login');
        $mailable->assertSeeInHtml('Banco Guayaquil');
        $mailable->assertSeeInHtml('REC-12345');
    }
}
