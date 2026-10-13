<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ApiMessage;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionCategory;
use App\Enums\TransactionKind;
use App\Enums\UserPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncPullTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_puede_hacer_pull_completo_de_sus_datos(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $account = $user->accounts()->create([
            'name' => 'Efectivo',
            'type' => AccountType::Cash,
            'amount' => 500.0,
            'is_default' => true,
        ]);

        $user->transactions()->create([
            'account_id' => $account->id,
            'type' => TransactionKind::Expense,
            'amount' => 50.0,
            'reason' => 'Almuerzo',
            'payment_method' => PaymentMethod::Cash,
            'category' => TransactionCategory::Food->value,
            'date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/sync/pull');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessage::SYNC_PULL_SUCCESS)
            ->assertJsonStructure([
                'data' => [
                    'server_time',
                    'accounts',
                    'transactions',
                ],
            ])
            ->assertJsonCount(1, 'data.accounts')
            ->assertJsonCount(1, 'data.transactions');
    }

    public function test_pull_soporta_filtro_incremental_since(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $oldAccount = $user->accounts()->create([
            'name' => 'Cuenta Vieja',
            'type' => AccountType::Cash,
            'amount' => 100.0,
            'is_default' => false,
        ]);
        $oldAccount->updated_at = now()->subHours(5);
        $oldAccount->save();

        $newAccount = $user->accounts()->create([
            'name' => 'Cuenta Nueva',
            'type' => AccountType::Cash,
            'amount' => 200.0,
            'is_default' => false,
        ]);
        $newAccount->updated_at = now()->subMinutes(10);
        $newAccount->save();

        $sinceIso = now()->subHour()->toIso8601String();
        $response = $this->actingAs($user)->getJson("/api/sync/pull?since={$sinceIso}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.accounts')
            ->assertJsonPath('data.accounts.0.name', 'Cuenta Nueva');
    }

    public function test_pull_incluye_registros_desactivados_con_deleted_at(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $account = $user->accounts()->create([
            'name' => 'Billetera Desactivada',
            'type' => AccountType::Cash,
            'amount' => 100.0,
            'is_default' => false,
        ]);
        $account->delete(); // Soft delete

        $response = $this->actingAs($user)->getJson('/api/sync/pull');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.accounts')
            ->assertJsonPath('data.accounts.0.name', 'Billetera Desactivada');

        $this->assertNotNull($response->json('data.accounts.0.deleted_at'));
    }

    public function test_pull_respeta_filtro_de_6_meses_en_plan_free(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $account = $user->accounts()->create([
            'name' => 'Efectivo',
            'type' => AccountType::Cash,
            'amount' => 500.0,
            'is_default' => true,
        ]);

        // Movimiento de hace 8 meses
        $user->transactions()->create([
            'account_id' => $account->id,
            'type' => TransactionKind::Expense,
            'amount' => 30.0,
            'reason' => 'Gasto Antiguo',
            'payment_method' => PaymentMethod::Cash,
            'category' => TransactionCategory::Other->value,
            'date' => now()->subMonths(8)->toDateString(),
        ]);

        // Movimiento reciente
        $user->transactions()->create([
            'account_id' => $account->id,
            'type' => TransactionKind::Expense,
            'amount' => 25.0,
            'reason' => 'Gasto Reciente',
            'payment_method' => PaymentMethod::Cash,
            'category' => TransactionCategory::Food->value,
            'date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/sync/pull');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.transactions')
            ->assertJsonPath('data.transactions.0.reason', 'Gasto Reciente');
    }
}
