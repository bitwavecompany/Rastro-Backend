<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ApiMessage;
use App\Enums\AccountType;
use App\Enums\TransactionKind;
use App\Enums\UserPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_puede_crear_transacciones_mediante_sync(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $account = $user->accounts()->create([
            'name' => 'Billetera',
            'type' => AccountType::Cash,
            'amount' => 500.00,
        ]);

        $txId = (string) Str::uuid();
        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'create',
                    'payload' => [
                        'id' => $txId,
                        'account_id' => $account->id,
                        'type' => 'expense',
                        'amount' => 50.00,
                        'reason' => 'Almuerzo',
                        'payment_method' => 'cash',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertDatabaseHas('transactions', [
            'id' => $txId,
            'account_id' => $account->id,
            'amount' => 50.00,
            'type' => 'expense',
            'reason' => 'Almuerzo',
        ]);
    }

    public function test_movimientos_son_ilimitados_en_todos_los_planes_sin_limite_de_50(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $account = $user->accounts()->create([
            'name' => 'Billetera',
            'type' => AccountType::Cash,
            'amount' => 5000.00,
        ]);

        // Crear 55 movimientos (más que el antiguo límite de 50)
        for ($i = 0; $i < 55; $i++) {
            $user->transactions()->create([
                'account_id' => $account->id,
                'kind' => TransactionKind::Expense,
                'amount' => 1.00,
                'reason' => "Gasto {$i}",
                'paymentType' => 'cash',
            ]);
        }

        // Crear el movimiento 56 mediante sync
        $txId = (string) Str::uuid();
        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'create',
                    'payload' => [
                        'id' => $txId,
                        'account_id' => $account->id,
                        'type' => 'expense',
                        'amount' => 10.00,
                        'reason' => 'Gasto 56',
                        'payment_method' => 'cash',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertDatabaseHas('transactions', ['id' => $txId]);
    }

    public function test_historial_de_6_meses_en_plan_free_vs_completo_en_premium(): void
    {
        /** @var User $freeUser */
        $freeUser = User::factory()->create(['plan' => UserPlan::Free]);

        $acc = $freeUser->accounts()->create([
            'name' => 'Cuenta Free',
            'type' => AccountType::Cash,
            'amount' => 1000.00,
        ]);

        // Movimiento reciente (hace 1 mes)
        $recentTx = $freeUser->transactions()->create([
            'account_id' => $acc->id,
            'kind' => TransactionKind::Expense,
            'amount' => 10.00,
            'reason' => 'Reciente',
            'paymentType' => 'cash',
            'date' => now()->subMonth(),
            'created_at' => now()->subMonth(),
        ]);

        // Movimiento antiguo (hace 8 meses)
        $oldTx = $freeUser->transactions()->create([
            'account_id' => $acc->id,
            'kind' => TransactionKind::Expense,
            'amount' => 20.00,
            'reason' => 'Antiguo',
            'paymentType' => 'cash',
            'date' => now()->subMonths(8),
            'created_at' => now()->subMonths(8),
        ]);

        // Usuario Free solo debe ver el reciente
        $responseFree = $this->actingAs($freeUser)->getJson('/api/transactions');
        $responseFree->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $recentTx->id);

        // Usuario Premium debe ver ambos
        $freeUser->update(['plan' => UserPlan::Premium]);
        $responsePremium = $this->actingAs($freeUser)->getJson('/api/transactions');
        $responsePremium->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_soft_delete_y_restauracion_de_transaccion(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $acc = $user->accounts()->create(['name' => 'Cuenta', 'type' => AccountType::Cash, 'amount' => 100]);

        $tx = $user->transactions()->create([
            'account_id' => $acc->id,
            'kind' => TransactionKind::Expense,
            'amount' => 15.00,
            'reason' => 'Comida',
            'paymentType' => 'cash',
        ]);

        // 1. Soft delete con motivo
        $deletePayload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'delete',
                    'payload' => [
                        'id' => $tx->id,
                        'deletion_reason' => 'Error de digitación',
                    ],
                ],
            ],
        ];

        $this->actingAs($user)->postJson('/api/sync/push', $deletePayload)
            ->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertSoftDeleted('transactions', [
            'id' => $tx->id,
            'deletion_reason' => 'Error de digitación',
        ]);

        // 2. Reactivar transacción (restore)
        $restorePayload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'restore',
                    'payload' => ['id' => $tx->id],
                ],
            ],
        ];

        $this->actingAs($user)->postJson('/api/sync/push', $restorePayload)
            ->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertNotSoftDeleted('transactions', ['id' => $tx->id]);
    }

    public function test_hard_delete_requiere_desactivacion_previa(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $acc = $user->accounts()->create(['name' => 'Cuenta', 'type' => AccountType::Cash, 'amount' => 100]);

        $tx = $user->transactions()->create([
            'account_id' => $acc->id,
            'kind' => TransactionKind::Expense,
            'amount' => 15.00,
            'reason' => 'Comida',
            'paymentType' => 'cash',
        ]);

        // Intentar hard-delete de transacción activa -> debe fallar con 422
        $hardDeletePayload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'hard-delete',
                    'payload' => ['id' => $tx->id],
                ],
            ],
        ];

        $this->actingAs($user)->postJson('/api/sync/push', $hardDeletePayload)
            ->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 422);

        // Desactivarla primero
        $tx->delete();

        // Ahora el hard-delete debe ser exitoso
        $this->actingAs($user)->postJson('/api/sync/push', $hardDeletePayload)
            ->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
    }

    public function test_purgar_todos_los_movimientos_de_una_cuenta(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $acc = $user->accounts()->create(['name' => 'Cuenta a purgar', 'type' => AccountType::Cash, 'amount' => 100]);

        $tx1 = $user->transactions()->create([
            'account_id' => $acc->id,
            'kind' => TransactionKind::Income,
            'amount' => 50.00,
            'reason' => 'Ingreso 1',
            'paymentType' => 'cash',
        ]);

        $tx2 = $user->transactions()->create([
            'account_id' => $acc->id,
            'kind' => TransactionKind::Expense,
            'amount' => 20.00,
            'reason' => 'Gasto 1',
            'paymentType' => 'cash',
        ]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'purge-account',
                    'payload' => ['account_id' => $acc->id],
                ],
            ],
        ];

        $this->actingAs($user)->postJson('/api/sync/push', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        // Las transacciones deben haber sido eliminadas permanentemente
        $this->assertDatabaseMissing('transactions', ['id' => $tx1->id]);
        $this->assertDatabaseMissing('transactions', ['id' => $tx2->id]);
        // La cuenta NO debe haberse eliminado
        $this->assertDatabaseHas('accounts', ['id' => $acc->id]);
    }

    public function test_purgar_todos_los_movimientos_globales_del_usuario(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $acc1 = $user->accounts()->create(['name' => 'Cuenta 1', 'type' => AccountType::Cash, 'amount' => 100]);
        $acc2 = $user->accounts()->create(['name' => 'Cuenta 2', 'type' => AccountType::Bank, 'amount' => 200]);

        $tx1 = Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'account_id' => $acc1->id,
            'kind' => TransactionKind::Expense,
            'amount' => 10,
            'reason' => 'R1',
            'paymentType' => 'cash',
        ]);
        $tx2 = Transaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'account_id' => $acc2->id,
            'kind' => TransactionKind::Expense,
            'amount' => 20,
            'reason' => 'R2',
            'paymentType' => 'transfer',
        ]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'purge-all',
                    'payload' => [],
                ],
            ],
        ];

        $this->actingAs($user)->postJson('/api/sync/push', $payload)
            ->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertDatabaseMissing('transactions', ['id' => $tx1->id]);
        $this->assertDatabaseMissing('transactions', ['id' => $tx2->id]);
        $this->assertDatabaseHas('accounts', ['id' => $acc1->id]);
        $this->assertDatabaseHas('accounts', ['id' => $acc2->id]);
    }

    public function test_exportar_csv_solo_permitido_para_usuarios_premium(): void
    {
        /** @var User $freeUser */
        $freeUser = User::factory()->create(['plan' => UserPlan::Free]);

        // Usuario Free es bloqueado con 403
        $this->actingAs($freeUser)->getJson('/api/transactions/export')
            ->assertStatus(403);

        /** @var User $premiumUser */
        $premiumUser = User::factory()->premium()->create();

        // Usuario Premium recibe el stream CSV (200 OK)
        $response = $this->actingAs($premiumUser)->get('/api/transactions/export');
        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_movimiento_cash_asociado_a_cuenta_bank_falla_con_403(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $bankAcc = $user->accounts()->create(['name' => 'Banco', 'type' => AccountType::Bank, 'amount' => 100]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'create',
                    'payload' => [
                        'id' => (string) Str::uuid(),
                        'account_id' => $bankAcc->id,
                        'type' => 'expense',
                        'amount' => 15.00,
                        'reason' => 'Pago',
                        'payment_method' => 'cash', // Incompatible con cuenta bancaria
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 403);
    }

    public function test_movimiento_transfer_asociado_a_cuenta_cash_falla_con_403(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $cashAcc = $user->accounts()->create(['name' => 'Billetera', 'type' => AccountType::Cash, 'amount' => 100]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'create',
                    'payload' => [
                        'id' => (string) Str::uuid(),
                        'account_id' => $cashAcc->id,
                        'type' => 'expense',
                        'amount' => 15.00,
                        'reason' => 'Transferencia',
                        'payment_method' => 'transfer', // Incompatible con cuenta en efectivo
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 403);
    }

    public function test_movimiento_asociado_a_cuenta_inactiva_falla_con_403(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $cashAcc = $user->accounts()->create(['name' => 'Vieja Billetera', 'type' => AccountType::Cash, 'amount' => 100]);
        $cashAcc->delete(); // Desactivada

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'create',
                    'payload' => [
                        'id' => (string) Str::uuid(),
                        'account_id' => $cashAcc->id,
                        'type' => 'expense',
                        'amount' => 10.00,
                        'reason' => 'Gasto',
                        'payment_method' => 'cash',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 403);
    }

    public function test_edicion_de_movimiento_a_tipo_incompatible_falla_con_403(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $cashAcc = $user->accounts()->create(['name' => 'Billetera', 'type' => AccountType::Cash, 'amount' => 100]);

        $tx = $user->transactions()->create([
            'account_id' => $cashAcc->id,
            'kind' => TransactionKind::Expense,
            'amount' => 10.00,
            'reason' => 'Almuerzo',
            'paymentType' => 'cash',
        ]);

        // Intentar actualizar payment_method a transfer manteniendo la cuenta cash
        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'update',
                    'payload' => [
                        'id' => $tx->id,
                        'payment_method' => 'transfer',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 403);
    }

    public function test_restauracion_de_movimiento_en_cuenta_inactiva_falla_con_403(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $cashAcc = $user->accounts()->create(['name' => 'Billetera', 'type' => AccountType::Cash, 'amount' => 100]);

        $tx = $user->transactions()->create([
            'account_id' => $cashAcc->id,
            'kind' => TransactionKind::Expense,
            'amount' => 10.00,
            'reason' => 'Almuerzo',
            'paymentType' => 'cash',
        ]);

        $tx->delete();
        $cashAcc->delete(); // Cuenta padre desactivada

        $restorePayload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'restore',
                    'payload' => ['id' => $tx->id],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $restorePayload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 403);
    }

    public function test_movimiento_con_fecha_sin_hora_recibe_hora_del_servidor(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $cashAcc = $user->accounts()->create(['name' => 'Billetera', 'type' => AccountType::Cash, 'amount' => 100]);
        $txId = (string) Str::uuid();

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'create',
                    'payload' => [
                        'id' => $txId,
                        'account_id' => $cashAcc->id,
                        'type' => 'income',
                        'amount' => 50.00,
                        'reason' => 'Cobro',
                        'payment_method' => 'cash',
                        'date' => '2026-10-10', // Solo YYYY-MM-DD
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        /** @var Transaction $createdTx */
        $createdTx = $user->transactions()->findOrFail($txId);

        $this->assertEquals('2026-10-10', $createdTx->date->toDateString());
        // Debe tener la hora del servidor asignada (now()->hour)
        $this->assertEquals(now()->hour, $createdTx->date->hour);
    }

    public function test_traslado_de_movimiento_entre_cuentas_con_herencia_y_derivacion_de_metodo_de_pago(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $sourceCash = $user->accounts()->create(['name' => 'Billetera', 'type' => AccountType::Cash, 'amount' => 100]);
        $destBank = $user->accounts()->create(['name' => 'Banco Pichincha', 'type' => AccountType::Bank, 'amount' => 500]);

        $originalTx = $user->transactions()->create([
            'account_id' => $sourceCash->id,
            'kind' => TransactionKind::Expense,
            'amount' => 45.50,
            'reason' => 'Cena familiar',
            'description' => 'Pago en restaurante',
            'paymentType' => 'cash',
            'date' => now()->subDays(2),
        ]);

        $newTxId = (string) Str::uuid();

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'relocate',
                    'payload' => [
                        'id' => $originalTx->id,
                        'new_id' => $newTxId,
                        'destination_account_id' => $destBank->id,
                        'reason' => 'Pagué con tarjeta por error',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        // 1. Registro original se desactiva con deleteReason de trazabilidad
        $refreshedOriginal = Transaction::withTrashed()->findOrFail($originalTx->id);
        $this->assertTrue($refreshedOriginal->trashed());
        $this->assertStringContainsString('Trasladado a Banco Pichincha: Pagué con tarjeta por error', $refreshedOriginal->deleteReason);

        // 2. Nuevo registro en cuenta destino hereda datos y deriva paymentType a transfer
        /** @var Transaction $newTx */
        $newTx = $user->transactions()->findOrFail($newTxId);
        $this->assertEquals($destBank->id, $newTx->account_id);
        $this->assertEquals(45.50, $newTx->amount);
        $this->assertEquals('Cena familiar', $newTx->reason);
        $this->assertEquals('Pago en restaurante', $newTx->description);
        $this->assertEquals(TransactionKind::Expense, $newTx->kind);
        $this->assertEquals('transfer', $newTx->paymentType);
        $this->assertEquals($originalTx->date->toDateTimeString(), $newTx->date->toDateTimeString());
        $this->assertNull($newTx->deleteReason);
    }

    public function test_traslado_hacia_cuenta_inactiva_falla_con_403(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $sourceCash = $user->accounts()->create(['name' => 'Billetera', 'type' => AccountType::Cash, 'amount' => 100]);
        $destBank = $user->accounts()->create(['name' => 'Banco Inactivo', 'type' => AccountType::Bank, 'amount' => 500]);
        $destBank->delete(); // Desactivada

        $originalTx = $user->transactions()->create([
            'account_id' => $sourceCash->id,
            'kind' => TransactionKind::Expense,
            'amount' => 20.00,
            'reason' => 'Compra',
            'paymentType' => 'cash',
        ]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'relocate',
                    'payload' => [
                        'id' => $originalTx->id,
                        'new_id' => (string) Str::uuid(),
                        'destination_account_id' => $destBank->id,
                        'reason' => 'Traslado de prueba',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 403);
    }

    public function test_traslado_sin_motivo_falla_con_422(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $sourceCash = $user->accounts()->create(['name' => 'Billetera', 'type' => AccountType::Cash, 'amount' => 100]);
        $destBank = $user->accounts()->create(['name' => 'Banco Activo', 'type' => AccountType::Bank, 'amount' => 500]);

        $originalTx = $user->transactions()->create([
            'account_id' => $sourceCash->id,
            'kind' => TransactionKind::Expense,
            'amount' => 20.00,
            'reason' => 'Compra',
            'paymentType' => 'cash',
        ]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'relocate',
                    'payload' => [
                        'id' => $originalTx->id,
                        'new_id' => (string) Str::uuid(),
                        'destination_account_id' => $destBank->id,
                        // Falta motivo
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 422);
    }

    public function test_reactivacion_de_movimiento_trasladado_no_se_bloquea_y_preserva_trazabilidad(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $sourceCash = $user->accounts()->create(['name' => 'Billetera', 'type' => AccountType::Cash, 'amount' => 100]);
        $destBank = $user->accounts()->create(['name' => 'Banco Pichincha', 'type' => AccountType::Bank, 'amount' => 500]);

        $originalTx = $user->transactions()->create([
            'account_id' => $sourceCash->id,
            'kind' => TransactionKind::Expense,
            'amount' => 45.50,
            'reason' => 'Cena familiar',
            'paymentType' => 'cash',
        ]);

        $newTxId = (string) Str::uuid();

        // 1. Trasladar
        $this->actingAs($user)->postJson('/api/sync/push', [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'relocate',
                    'payload' => [
                        'id' => $originalTx->id,
                        'new_id' => $newTxId,
                        'destination_account_id' => $destBank->id,
                        'reason' => 'Error de cuenta',
                    ],
                ],
            ],
        ]);

        $refreshedOriginal = Transaction::withTrashed()->findOrFail($originalTx->id);
        $this->assertTrue($refreshedOriginal->trashed());

        // 2. Reactivar el movimiento original trasladado (Regla 9: no se bloquea)
        $restoreResponse = $this->actingAs($user)->postJson('/api/sync/push', [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'restore',
                    'payload' => [
                        'id' => $originalTx->id,
                    ],
                ],
            ],
        ]);

        $restoreResponse->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $restoredTx = $user->transactions()->findOrFail($originalTx->id);
        $this->assertFalse($restoredTx->trashed());
    }

    public function test_un_usuario_puede_actualizar_movimiento_mediante_sync(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $account = $user->accounts()->create([
            'name' => 'Billetera',
            'type' => AccountType::Cash,
            'amount' => 500.00,
        ]);

        $tx = $user->transactions()->create([
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'amount' => 15.00,
            'reason' => 'Almuerzo inicial',
            'paymentType' => 'cash',
        ]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'update',
                    'payload' => [
                        'id' => $tx->id,
                        'amount' => 25.50,
                        'reason' => 'Almuerzo con postre',
                        'description' => 'Restaurante del centro',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $updatedTx = $tx->fresh();
        $this->assertEquals(25.50, $updatedTx->amount);
        $this->assertEquals('Almuerzo con postre', $updatedTx->reason);
        $this->assertEquals('Restaurante del centro', $updatedTx->description);
    }

    public function test_traslado_de_movimiento_desactivado_con_cuenta_padre_activa_es_rechazado_con_422(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $sourceAccount = $user->accounts()->create([
            'name' => 'Billetera Activa',
            'type' => AccountType::Cash,
            'amount' => 200.00,
        ]);

        $destAccount = $user->accounts()->create([
            'name' => 'Banco Destino',
            'type' => AccountType::Bank,
            'amount' => 300.00,
        ]);

        // Movimiento desactivado individualmente, pero la cuenta padre permanece activa
        $tx = $user->transactions()->create([
            'account_id' => $sourceAccount->id,
            'kind' => TransactionKind::Expense,
            'amount' => 20.00,
            'reason' => 'Gasto desactivado',
            'paymentType' => 'cash',
        ]);
        $tx->delete();

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'relocate',
                    'payload' => [
                        'id' => $tx->id,
                        'new_id' => (string) Str::uuid(),
                        'destination_account_id' => $destAccount->id,
                        'reason' => 'Intento de trasladar registro desactivado',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200);
        $this->assertEquals('error', $response->json('data.results.0.status'));
        $this->assertEquals(422, $response->json('data.results.0.code'));
        $this->assertEquals(ApiMessage::TRANSACTION_ALREADY_INACTIVE, $response->json('data.results.0.message'));
    }
}
