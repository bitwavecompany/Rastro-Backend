<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ApiMessage;
use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionCategory;
use App\Enums\TransactionKind;
use App\Enums\UserPlan;
use App\Models\Account;
use App\Models\SyncTombstone;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
                    'deleted_account_ids',
                    'deleted_transaction_ids',
                ],
            ])
            ->assertJsonCount(1, 'data.accounts')
            ->assertJsonCount(1, 'data.transactions')
            ->assertJsonPath('data.deleted_account_ids', [])
            ->assertJsonPath('data.deleted_transaction_ids', []);
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

    public function test_pull_incremental_retorna_tombstones_de_cuentas_y_transacciones_eliminadas_fisicamente(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $account = $user->accounts()->create([
            'name' => 'Banco a Eliminar',
            'type' => AccountType::Bank,
            'amount' => 200.0,
            'is_default' => false,
        ]);

        $tx = $user->transactions()->create([
            'account_id' => $account->id,
            'type' => TransactionKind::Expense,
            'amount' => 50.0,
            'reason' => 'Tx a eliminar',
            'payment_method' => PaymentMethod::Transfer,
            'category' => TransactionCategory::Food->value,
            'date' => now()->toDateString(),
        ]);

        // Ejecutar eliminación física mediante endpoint push
        $this->actingAs($user)->postJson('/api/sync/push', [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'accounts',
                    'op' => 'hard-delete',
                    'payload' => [
                        'id' => $account->id,
                    ],
                ],
            ],
        ])->assertStatus(200);

        // La base de datos no contiene los registros físicamente
        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);

        // Se crearon los tombstones correspondientes
        $this->assertDatabaseHas('sync_tombstones', [
            'user_id' => $user->id,
            'entity' => 'accounts',
            'entity_id' => $account->id,
        ]);
        $this->assertDatabaseHas('sync_tombstones', [
            'user_id' => $user->id,
            'entity' => 'transactions',
            'entity_id' => $tx->id,
        ]);

        // Pull incremental con since
        $sinceIso = now()->subMinutes(5)->toIso8601String();
        $response = $this->actingAs($user)->getJson("/api/sync/pull?since={$sinceIso}");

        $response->assertStatus(200)
            ->assertJsonPath('data.deleted_account_ids.0', (string) $account->id)
            ->assertJsonPath('data.deleted_transaction_ids.0', (string) $tx->id);

        // Pull completo (sin since) no debe retornar los tombstones
        $fullResponse = $this->actingAs($user)->getJson('/api/sync/pull');
        $fullResponse->assertStatus(200)
            ->assertJsonPath('data.deleted_account_ids', [])
            ->assertJsonPath('data.deleted_transaction_ids', []);
    }

    public function test_desactivacion_en_cascada_de_cuenta_actualiza_updated_at_en_transacciones(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $account = $user->accounts()->create([
            'name' => 'Banco Secundario',
            'type' => AccountType::Bank,
            'amount' => 1000.0,
            'is_default' => false,
        ]);

        $tx = $user->transactions()->create([
            'account_id' => $account->id,
            'type' => TransactionKind::Expense,
            'amount' => 100.0,
            'reason' => 'Compra previa',
            'payment_method' => PaymentMethod::Transfer,
            'category' => TransactionCategory::Food->value,
            'date' => now()->toDateString(),
        ]);

        // Forzar fechas pasadas en la transacción
        $tx->updated_at = now()->subHours(10);
        $tx->save();

        $sinceIso = now()->subMinutes(2)->toIso8601String();

        // Desactivar la cuenta (Soft Delete que dispara evento deleting en cascada)
        $account->delete();

        // El updated_at de la transacción debe haberse refrescado a now()
        $txFresh = Transaction::withTrashed()->find($tx->id);
        $this->assertNotNull($txFresh);
        $this->assertNotNull($txFresh->deleted_at);
        $this->assertTrue($txFresh->updated_at->greaterThan(now()->subMinute()));

        // El pull incremental debe capturar la transacción desactivada gracias al updated_at actualizado
        $response = $this->actingAs($user)->getJson("/api/sync/pull?since={$sinceIso}");
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.transactions')
            ->assertJsonPath('data.transactions.0.id', (string) $tx->id)
            ->assertNotNull($response->json('data.transactions.0.deleted_at'));
    }

    public function test_pull_incremental_aplica_buffer_de_reloj_de_dos_segundos(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $account = $user->accounts()->create([
            'name' => 'Billetera Digital',
            'type' => AccountType::Cash,
            'amount' => 150.0,
            'is_default' => false,
        ]);

        // Transacción modificada exactamente hace 1 segundo
        $account->updated_at = now()->subSecond();
        $account->save();

        // Petición enviando since = now() (en un caso sin buffer, se perdería)
        $sinceIso = now()->toIso8601String();
        $response = $this->actingAs($user)->getJson("/api/sync/pull?since={$sinceIso}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.accounts')
            ->assertJsonPath('data.accounts.0.id', (string) $account->id);
    }

    public function test_purge_commands_registran_tombstones_en_lote(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $account = $user->accounts()->create([
            'name' => 'Cuenta para Purga',
            'type' => AccountType::Cash,
            'amount' => 500.0,
            'is_default' => false,
        ]);

        $tx1 = $user->transactions()->create([
            'account_id' => $account->id,
            'type' => TransactionKind::Expense,
            'amount' => 10.0,
            'reason' => 'Tx 1',
            'payment_method' => PaymentMethod::Cash,
            'category' => TransactionCategory::Food->value,
            'date' => now()->toDateString(),
        ]);

        $tx2 = $user->transactions()->create([
            'account_id' => $account->id,
            'type' => TransactionKind::Expense,
            'amount' => 20.0,
            'reason' => 'Tx 2',
            'payment_method' => PaymentMethod::Cash,
            'category' => TransactionCategory::Food->value,
            'date' => now()->toDateString(),
        ]);

        // Ejecutar purge-account vía push
        $this->actingAs($user)->postJson('/api/sync/push', [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'purge-account',
                    'payload' => [
                        'account_id' => $account->id,
                    ],
                ],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseMissing('transactions', ['id' => $tx1->id]);
        $this->assertDatabaseMissing('transactions', ['id' => $tx2->id]);

        $this->assertDatabaseHas('sync_tombstones', [
            'user_id' => $user->id,
            'entity' => 'transactions',
            'entity_id' => $tx1->id,
        ]);
        $this->assertDatabaseHas('sync_tombstones', [
            'user_id' => $user->id,
            'entity' => 'transactions',
            'entity_id' => $tx2->id,
        ]);
    }
}
