<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionCategory;
use App\Enums\TransactionKind;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_puede_obtener_catalogo_de_categorias(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.id', 'comida')
            ->assertJsonPath('data.0.name', 'Comida');
    }

    public function test_crear_gasto_sin_categoria_falla_con_error_de_validacion(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $account = $user->accounts()->create([
            'name' => 'Efectivo',
            'type' => AccountType::Cash,
            'amount' => 100.0,
            'is_default' => true,
        ]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'create',
                    'payload' => [
                        'id' => (string) Str::uuid(),
                        'account_id' => $account->id,
                        'kind' => TransactionKind::Expense->value,
                        'amount' => 20.0,
                        'reason' => 'Almuerzo sin categoría',
                        'payment_method' => PaymentMethod::Cash->value,
                        // Sin category
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 422);
    }

    public function test_crear_gasto_con_categoria_invalida_falla(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $account = $user->accounts()->create([
            'name' => 'Efectivo',
            'type' => AccountType::Cash,
            'amount' => 100.0,
            'is_default' => true,
        ]);

        $payload = [
            'operations' => [
                [
                    'opId' => (string) Str::uuid(),
                    'entity' => 'transactions',
                    'op' => 'create',
                    'payload' => [
                        'id' => (string) Str::uuid(),
                        'account_id' => $account->id,
                        'kind' => TransactionKind::Expense->value,
                        'amount' => 20.0,
                        'reason' => 'Almuerzo categoría inventada',
                        'payment_method' => PaymentMethod::Cash->value,
                        'category' => 'categoria_inexistente',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'error')
            ->assertJsonPath('data.results.0.code', 422);
    }

    public function test_crear_gasto_con_categoria_valida_es_exitoso(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $account = $user->accounts()->create([
            'name' => 'Efectivo',
            'type' => AccountType::Cash,
            'amount' => 100.0,
            'is_default' => true,
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
                        'kind' => TransactionKind::Expense->value,
                        'amount' => 15.0,
                        'reason' => 'Cena rápida',
                        'payment_method' => PaymentMethod::Cash->value,
                        'category' => TransactionCategory::Food->value,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertDatabaseHas('transactions', [
            'id' => $txId,
            'category' => 'comida',
            'type' => 'expense',
        ]);
    }

    public function test_ingresos_no_guardan_categoria_y_se_persisten_como_null(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $account = $user->accounts()->create([
            'name' => 'Efectivo',
            'type' => AccountType::Cash,
            'amount' => 100.0,
            'is_default' => true,
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
                        'kind' => TransactionKind::Income->value,
                        'amount' => 500.0,
                        'reason' => 'Honorarios',
                        'payment_method' => PaymentMethod::Cash->value,
                        'category' => 'trabajo', // Aunque se envíe, para income se ignora/fuerza a null
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/api/sync/push', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.results.0.status', 'success');

        $this->assertDatabaseHas('transactions', [
            'id' => $txId,
            'category' => null,
            'type' => 'income',
        ]);
    }
}
