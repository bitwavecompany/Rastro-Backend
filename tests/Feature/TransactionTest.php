<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_puede_crear_una_transaccion()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        $account = $user->accounts()->create([
            'name' => 'Billetera',
            'type' => 'cash',
            'amount' => 500.00
        ]);

        $payload = [
            'account_id' => $account->id,
            'kind' => 'expense',
            'amount' => 50.00,
            'reason' => 'Almuerzo',
            'paymentType' => 'cash'
        ];

        $response = $this->actingAs($user)->postJson('/api/transactions', $payload);

        $response->assertStatus(201)
                 ->assertJsonPath('data.reason', 'Almuerzo');

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'amount' => 50.00,
            'kind' => 'expense'
        ]);
    }

    public function test_un_usuario_puede_filtrar_transacciones_por_tipo()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        $account = $user->accounts()->create([
            'name' => 'Banco',
            'type' => 'bank',
            'amount' => 1000.00
        ]);

        $user->transactions()->create([
            'account_id' => $account->id,
            'kind' => 'income',
            'amount' => 200.00,
            'reason' => 'Venta',
            'paymentType' => 'transfer'
        ]);

        $user->transactions()->create([
            'account_id' => $account->id,
            'kind' => 'expense',
            'amount' => 50.00,
            'reason' => 'Taxi',
            'paymentType' => 'cash'
        ]);

        $response = $this->actingAs($user)->getJson('/api/transactions?kind=expense');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data') 
                 ->assertJsonPath('data.0.reason', 'Taxi'); 
    }

    public function test_anular_transaccion_requiere_motivo_obligatorio()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();

        $account = $user->accounts()->create([
            'name' => 'Banco',
            'type' => 'bank',
            'amount' => 1000.00
        ]);

        $tx = $user->transactions()->create([
            'account_id' => $account->id,
            'kind' => 'expense',
            'amount' => 50.00,
            'reason' => 'Taxi',
            'paymentType' => 'cash'
        ]);

        $responseFail = $this->actingAs($user)->deleteJson("/api/transactions/{$tx->id}");
        $responseFail->assertStatus(422)
                     ->assertJsonValidationErrors(['deleteReason']);

        $responseSuccess = $this->actingAs($user)->deleteJson("/api/transactions/{$tx->id}", [
            'deleteReason' => 'Me equivoqué en el monto'
        ]);
        $responseSuccess->assertStatus(200);

        $this->assertSoftDeleted('transactions', [
            'id' => $tx->id,
            'deleteReason' => 'Me equivoqué en el monto'
        ]);
    }
}