<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\TransactionKind;
use App\Enums\UserPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_puede_listar_sus_cuentas(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $user->accounts()->create([
            'name' => 'Mi Billetera',
            'type' => AccountType::Cash,
            'amount' => 100.00,
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/accounts');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Mi Billetera')
            ->assertJsonPath('data.0.is_default', true);
        $this->assertEquals(100.0, $response->json('data.0.initial_balance'));
    }

    public function test_un_usuario_puede_crear_una_cuenta_mediante_sync(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $accId = (string) Str::uuid();
        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'accounts',
                    'op' => 'create',
                    'payload' => [
                        'id' => $accId,
                        'name' => 'Banco Pichincha',
                        'type' => 'bank',
                        'amount' => 500.00,
                        'description' => 'Ahorros',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertDatabaseHas('accounts', [
            'id' => $accId,
            'user_id' => $user->id,
            'name' => 'Banco Pichincha',
            'type' => 'bank',
            'is_default' => false,
        ]);
    }

    public function test_nombres_de_cuentas_deben_ser_unicos_por_usuario(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $user->accounts()->create([
            'name' => 'Ahorros',
            'type' => AccountType::Bank,
            'amount' => 100.00,
        ]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'accounts',
                    'op' => 'create',
                    'payload' => [
                        'id' => (string) Str::uuid(),
                        'name' => 'Ahorros',
                        'type' => 'bank',
                        'amount' => 200.00,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 422);
    }

    public function test_limite_de_3_cuentas_activas_para_plan_free(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        // Crear 3 cuentas activas (incluyendo la default)
        $user->accounts()->create(['name' => 'Efectivo', 'type' => AccountType::Cash, 'amount' => 0, 'is_default' => true]);
        $user->accounts()->create(['name' => 'Banco 1', 'type' => AccountType::Bank, 'amount' => 10]);
        $user->accounts()->create(['name' => 'Banco 2', 'type' => AccountType::Bank, 'amount' => 20]);

        // Intentar crear la 4ta
        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'accounts',
                    'op' => 'create',
                    'payload' => [
                        'id' => (string) Str::uuid(),
                        'name' => 'Banco 3',
                        'type' => 'bank',
                        'amount' => 30.00,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 403);
    }

    public function test_desactivar_cuenta_desactiva_movimientos_en_cascada_y_libera_slot(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $account = $user->accounts()->create([
            'name' => 'Billetera Digital',
            'type' => AccountType::Cash,
            'amount' => 50.00,
            'is_default' => false,
        ]);

        $tx = $user->transactions()->create([
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'amount' => 10.00,
            'reason' => 'Café',
            'paymentType' => 'cash',
        ]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'accounts',
                    'op' => 'delete',
                    'payload' => [
                        'id' => $account->id,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
        $this->assertSoftDeleted('transactions', ['id' => $tx->id]);
    }

    public function test_reactivar_cuenta_no_reactiva_movimientos_y_ocupa_slot(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['plan' => UserPlan::Free]);

        $account = $user->accounts()->create([
            'name' => 'Cuenta Desactivada',
            'type' => AccountType::Bank,
            'amount' => 50.00,
        ]);

        $tx = $user->transactions()->create([
            'account_id' => $account->id,
            'kind' => TransactionKind::Income,
            'amount' => 100.00,
            'reason' => 'Sueldo',
            'paymentType' => 'transfer',
        ]);

        $account->delete(); // Soft delete en cascada
        $this->assertSoftDeleted('transactions', ['id' => $tx->id]);

        // Reactivar cuenta
        $payload = [
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
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertNotSoftDeleted('accounts', ['id' => $account->id]);
        // Los movimientos asociados deben PERMANECER desactivados (sin cascada de restauración per PRD)
        $this->assertSoftDeleted('transactions', ['id' => $tx->id]);
    }

    public function test_cambio_de_tipo_de_cuenta_actualiza_en_cascada_metodo_de_pago_de_movimientos(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $account = $user->accounts()->create([
            'name' => 'Mi Banco',
            'type' => AccountType::Bank,
            'amount' => 100.00,
            'is_default' => false,
        ]);

        $activeTx = $user->transactions()->create([
            'account_id' => $account->id,
            'kind' => TransactionKind::Income,
            'amount' => 50.00,
            'reason' => 'Depósito activo',
            'paymentType' => 'transfer',
        ]);

        $deletedTx = $user->transactions()->create([
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'amount' => 20.00,
            'reason' => 'Gasto desactivado',
            'paymentType' => 'transfer',
        ]);
        $deletedTx->delete();

        // Cambiar tipo de cuenta de bank a cash (Regla 4 y 5)
        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'accounts',
                    'op' => 'update',
                    'payload' => [
                        'id' => $account->id,
                        'type' => 'cash',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertEquals(AccountType::Cash, $account->fresh()->type);

        // Movimientos activos y desactivados deben haber actualizado a cash en cascada
        $this->assertEquals('cash', $activeTx->fresh()->paymentType);
        $this->assertEquals('cash', Transaction::withTrashed()->findOrFail($deletedTx->id)->paymentType);
    }

    public function test_cambio_de_tipo_de_cuenta_cash_a_bank_actualiza_en_cascada_a_transfer(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $account = $user->accounts()->create([
            'name' => 'Mi Billetera Personal',
            'type' => AccountType::Cash,
            'amount' => 150.00,
            'is_default' => false,
        ]);

        $tx = $user->transactions()->create([
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'amount' => 30.00,
            'reason' => 'Compra',
            'paymentType' => 'cash',
        ]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'accounts',
                    'op' => 'update',
                    'payload' => [
                        'id' => $account->id,
                        'type' => 'bank',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertEquals(AccountType::Bank, $account->fresh()->type);
        $this->assertEquals('transfer', $tx->fresh()->paymentType);
    }

    public function test_cambio_de_tipo_de_cuenta_sin_movimientos_es_libre_y_sin_cascada(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $account = $user->accounts()->create([
            'name' => 'Cuenta Nueva',
            'type' => AccountType::Cash,
            'amount' => 0.0,
            'is_default' => false,
        ]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'accounts',
                    'op' => 'update',
                    'payload' => [
                        'id' => $account->id,
                        'type' => 'bank',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertEquals(AccountType::Bank, $account->fresh()->type);
    }

    public function test_filtrado_de_cuentas_por_tipo_y_estado_activo(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $cashAcc = $user->accounts()->create(['name' => 'Efectivo 1', 'type' => AccountType::Cash, 'amount' => 10]);
        $bankAcc = $user->accounts()->create(['name' => 'Banco 1', 'type' => AccountType::Bank, 'amount' => 20]);
        $deletedBank = $user->accounts()->create(['name' => 'Banco 2', 'type' => AccountType::Bank, 'amount' => 30]);
        $deletedBank->delete();

        // Filtrar solo cuentas bancarias activas (Regla 1)
        $response = $this->actingAs($user)->getJson('/api/accounts?type=bank&active_only=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bankAcc->id);

        // Filtrar cuentas cash
        $responseCash = $this->actingAs($user)->getJson('/api/accounts?type=cash&active_only=true');
        $responseCash->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cashAcc->id);
    }

    public function test_cuenta_efectivo_por_defecto_es_inmutable_y_permanente(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $defaultAccount = $user->accounts()->create([
            'name' => 'Efectivo',
            'type' => AccountType::Cash,
            'amount' => 0.0,
            'is_default' => true,
        ]);

        // Intentar borrar
        $deletePayload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'accounts',
                    'op' => 'delete',
                    'payload' => ['id' => $defaultAccount->id],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $deletePayload);
        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 403);

        $this->assertNotSoftDeleted('accounts', ['id' => $defaultAccount->id]);
    }

    public function test_modificar_cuenta_por_defecto_es_rechazado_con_403(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $defaultAccount = $user->accounts()->create([
            'name' => 'Efectivo',
            'type' => AccountType::Cash,
            'amount' => 0.0,
            'is_default' => true,
        ]);

        $updatePayload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'accounts',
                    'op' => 'update',
                    'payload' => [
                        'id' => $defaultAccount->id,
                        'name' => 'Nuevo Nombre Efectivo',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $updatePayload);
        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 403);

        $this->assertEquals('Efectivo', $defaultAccount->fresh()->name);
    }
}
