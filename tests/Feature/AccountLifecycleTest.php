<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Enums\AccountType;
use App\Enums\DeactivationReason;
use App\Enums\TransactionKind;
use App\Enums\UserPlan;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_soft_delete_cascades_deletion_reason_to_transactions(): void
    {
        $user = User::factory()->create(['plan' => UserPlan::Free]);
        $token = $user->createToken('test')->plainTextToken;

        $account = Account::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Ahorros',
            'type' => AccountType::Bank,
            'amount' => 500.0,
            'is_default' => false,
        ]);

        $tx1 = Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'amount' => 50.0,
            'reason' => 'Comida',
            'paymentType' => 'transfer',
            'date' => now(),
        ]);

        $tx2 = Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'account_id' => $account->id,
            'kind' => TransactionKind::Income,
            'amount' => 100.0,
            'reason' => 'Freelance',
            'paymentType' => 'transfer',
            'date' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'operations' => [
                    [
                        'opId' => (string) Str::uuid(),
                        'entity' => 'accounts',
                        'op' => 'delete',
                        'payload' => [
                            'id' => $account->id,
                            'deactivation_reason' => 'plan_downgrade',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(HttpCode::OK);

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
        $refreshedAccount = Account::withTrashed()->find($account->id);
        $this->assertEquals(DeactivationReason::PlanDowngrade, $refreshedAccount->deactivation_reason);

        // Movimientos desactivados en cascada con deletion_reason obligatorio (Regla 18)
        $this->assertSoftDeleted('transactions', ['id' => $tx1->id]);
        $this->assertSoftDeleted('transactions', ['id' => $tx2->id]);
        $this->assertEquals('cascade_account_deactivation', Transaction::withTrashed()->find($tx1->id)->deleteReason);
        $this->assertEquals('cascade_account_deactivation', Transaction::withTrashed()->find($tx2->id)->deleteReason);
    }

    public function test_account_restore_resets_deactivation_reason_without_restoring_transactions(): void
    {
        $user = User::factory()->create(['plan' => UserPlan::Free]);
        $token = $user->createToken('test')->plainTextToken;

        $account = Account::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Ahorros',
            'type' => AccountType::Bank,
            'amount' => 500.0,
            'is_default' => false,
            'deactivation_reason' => DeactivationReason::PlanDowngrade,
        ]);
        $account->delete(); // Soft deleted

        $tx = Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'amount' => 50.0,
            'reason' => 'Comida',
            'paymentType' => 'transfer',
            'date' => now(),
            'deleteReason' => 'cascade_account_deactivation',
        ]);
        $tx->delete();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'operations' => [
                    [
                        'opId' => (string) Str::uuid(),
                        'entity' => 'accounts',
                        'op' => 'restore',
                        'payload' => [
                            'id' => $account->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(HttpCode::OK);

        $refreshedAccount = Account::find($account->id);
        $this->assertNotNull($refreshedAccount);
        $this->assertNull($refreshedAccount->deactivation_reason);

        // Transacciones permanecen desactivadas
        $this->assertTrue(Transaction::withTrashed()->find($tx->id)->trashed());
    }

    public function test_direct_hard_delete_account_removes_it_and_transactions_permanently(): void
    {
        $user = User::factory()->create(['plan' => UserPlan::Free]);
        $token = $user->createToken('test')->plainTextToken;

        $account = Account::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Cuenta a Borrar',
            'type' => AccountType::Bank,
            'amount' => 500.0,
            'is_default' => false,
        ]);

        $tx = Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'amount' => 20.0,
            'reason' => 'Prueba',
            'paymentType' => 'transfer',
            'date' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
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
            ]);

        $response->assertStatus(HttpCode::OK);

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
    }

    public function test_relocate_transaction_from_deactivated_account_creates_active_destination_tx(): void
    {
        $user = User::factory()->create(['plan' => UserPlan::Free]);
        $token = $user->createToken('test')->plainTextToken;

        $deactivatedAccount = Account::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Banco Cerrado',
            'type' => AccountType::Bank,
            'amount' => 0.0,
            'is_default' => false,
        ]);
        $deactivatedAccount->delete();

        $activeAccount = Account::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Efectivo Personal',
            'type' => AccountType::Cash,
            'amount' => 100.0,
            'is_default' => false,
        ]);

        $originalTx = Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'account_id' => $deactivatedAccount->id,
            'kind' => TransactionKind::Expense,
            'amount' => 45.0,
            'reason' => 'Supermercado',
            'description' => 'Compra semanal',
            'paymentType' => 'transfer',
            'date' => now(),
            'deleteReason' => 'cascade_account_deactivation',
        ]);
        $originalTx->delete();

        $newTxId = (string) Str::uuid();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'operations' => [
                    [
                        'opId' => (string) Str::uuid(),
                        'entity' => 'transactions',
                        'op' => 'relocate',
                        'payload' => [
                            'id' => $originalTx->id,
                            'new_id' => $newTxId,
                            'destination_account_id' => $activeAccount->id,
                            'reason' => 'Corrección contable',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(HttpCode::OK);

        // Nuevo registro es activo en la cuenta destino y hereda campos
        $newTx = Transaction::find($newTxId);
        $this->assertNotNull($newTx);
        $this->assertEquals($activeAccount->id, $newTx->account_id);
        $this->assertEquals(45.0, $newTx->amount);
        $this->assertEquals('cash', $newTx->paymentType); // Derivado automáticamente de cuenta cash
        $this->assertNull($newTx->deleted_at);

        // El registro original mantiene cascade_account_deactivation y anexa nota en description
        $refreshedOriginal = Transaction::withTrashed()->find($originalTx->id);
        $this->assertTrue($refreshedOriginal->trashed());
        $this->assertEquals('cascade_account_deactivation', $refreshedOriginal->deleteReason);
        $this->assertStringContainsString('[Trasladado a Efectivo Personal', $refreshedOriginal->description);
        $this->assertStringContainsString('Corrección contable]', $refreshedOriginal->description);
    }

    public function test_relocate_transaction_to_same_account_is_rejected(): void
    {
        $user = User::factory()->create(['plan' => UserPlan::Free]);
        $token = $user->createToken('test')->plainTextToken;

        $account = Account::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Billetera',
            'type' => AccountType::Cash,
            'amount' => 100.0,
            'is_default' => false,
        ]);

        $tx = Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'amount' => 20.0,
            'reason' => 'Café',
            'paymentType' => 'cash',
            'date' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'operations' => [
                    [
                        'opId' => (string) Str::uuid(),
                        'entity' => 'transactions',
                        'op' => 'relocate',
                        'payload' => [
                            'id' => $tx->id,
                            'new_id' => (string) Str::uuid(),
                            'destination_account_id' => $account->id, // Misma cuenta origen
                            'reason' => 'Misma cuenta',
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(HttpCode::OK);
        $this->assertEquals('error', $response->json('data.results.0.status'));
        $this->assertEquals(ApiMessage::RELOCATE_SAME_ACCOUNT, $response->json('data.results.0.message'));
    }

    public function test_hard_delete_cuenta_por_defecto_es_rechazado_con_403(): void
    {
        $user = User::factory()->create(['plan' => UserPlan::Free]);
        $token = $user->createToken('test')->plainTextToken;

        $defaultAccount = Account::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Efectivo',
            'type' => AccountType::Cash,
            'amount' => 0.0,
            'is_default' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'operations' => [
                    [
                        'opId' => (string) Str::uuid(),
                        'entity' => 'accounts',
                        'op' => 'hard-delete',
                        'payload' => [
                            'id' => $defaultAccount->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(HttpCode::OK);
        $this->assertEquals('error', $response->json('data.results.0.status'));
        $this->assertEquals(403, $response->json('data.results.0.code'));
        $this->assertEquals(ApiMessage::ACCOUNT_DEFAULT_PROTECTED, $response->json('data.results.0.message'));
        $this->assertDatabaseHas('accounts', ['id' => $defaultAccount->id]);
    }

    public function test_restaurar_cuenta_cuando_se_excede_limite_de_3_falla_con_403(): void
    {
        $user = User::factory()->create(['plan' => UserPlan::Free]);
        $token = $user->createToken('test')->plainTextToken;

        // 3 active accounts
        Account::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Efectivo', 'type' => AccountType::Cash, 'amount' => 0, 'is_default' => true]);
        Account::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Banco 1', 'type' => AccountType::Bank, 'amount' => 10, 'is_default' => false]);
        Account::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Banco 2', 'type' => AccountType::Bank, 'amount' => 20, 'is_default' => false]);

        // 4th account is soft-deleted
        $fourthAccount = Account::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Banco 3',
            'type' => AccountType::Bank,
            'amount' => 30,
            'is_default' => false,
        ]);
        $fourthAccount->delete();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/sync/push', [
                'operations' => [
                    [
                        'opId' => (string) Str::uuid(),
                        'entity' => 'accounts',
                        'op' => 'restore',
                        'payload' => [
                            'id' => $fourthAccount->id,
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(HttpCode::OK);
        $this->assertEquals('error', $response->json('data.results.0.status'));
        $this->assertEquals(403, $response->json('data.results.0.code'));
        $this->assertEquals(ApiMessage::LIMIT_ACCOUNTS, $response->json('data.results.0.message'));
        $this->assertSoftDeleted('accounts', ['id' => $fourthAccount->id]);
    }
}
