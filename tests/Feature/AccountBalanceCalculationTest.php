<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\TransactionKind;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountBalanceCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_balance_calculates_according_to_prd_formula(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // 1. Account created with initial balance of 100.00
        /** @var Account $account */
        $account = $user->accounts()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Billetera Principal',
            'type' => AccountType::Cash,
            'amount' => 100.00,
            'is_default' => true,
        ]);

        // 2. Add active income of 50.00
        $user->transactions()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'kind' => TransactionKind::Income,
            'paymentType' => 'cash',
            'amount' => 50.00,
            'reason' => 'Cobro de venta',
            'date' => now(),
        ]);

        // 3. Add active expense of 30.00
        $user->transactions()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'paymentType' => 'cash',
            'amount' => 30.00,
            'reason' => 'Compra de insumos',
            'date' => now(),
        ]);

        // 4. Add soft-deleted expense of 20.00 (should NOT affect balance)
        $deletedExpense = $user->transactions()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'paymentType' => 'cash',
            'amount' => 20.00,
            'reason' => 'Gasto cancelado',
            'date' => now(),
        ]);
        $deletedExpense->delete();

        // 5. Query accounts via API
        $response = $this->actingAs($user)->getJson('/api/accounts');

        $response->assertStatus(200);

        // Expected: initial_balance = 100.00, current_balance (amount) = 100 + 50 - 30 = 120.00
        $data = $response->json('data.0');
        $this->assertEquals(100.00, $data['initial_balance']);
        $this->assertEquals(120.00, $data['amount']);
    }

    public function test_multiple_accounts_calculate_balances_independently(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Account 1: Bank (initial: 500.00)
        /** @var Account $bankAccount */
        $bankAccount = $user->accounts()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Cuenta Banco',
            'type' => AccountType::Bank,
            'amount' => 500.00,
            'is_default' => false,
        ]);

        // Account 2: Cash (initial: 50.00)
        /** @var Account $cashAccount */
        $cashAccount = $user->accounts()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Billetera Efectivo',
            'type' => AccountType::Cash,
            'amount' => 50.00,
            'is_default' => true,
        ]);

        // Income of 200 in bank account
        $user->transactions()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $bankAccount->id,
            'kind' => TransactionKind::Income,
            'paymentType' => 'transfer',
            'amount' => 200.00,
            'reason' => 'Transferencia recibida',
            'date' => now(),
        ]);

        // Expense of 15 in cash account
        $user->transactions()->create([
            'id' => (string) Str::uuid(),
            'account_id' => $cashAccount->id,
            'kind' => TransactionKind::Expense,
            'paymentType' => 'cash',
            'amount' => 15.00,
            'reason' => 'Almuerzo',
            'date' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/accounts');

        $response->assertStatus(200);

        $accounts = collect($response->json('data'));

        $bankData = $accounts->firstWhere('id', $bankAccount->id);
        $this->assertNotNull($bankData);
        $this->assertEquals(500.00, $bankData['initial_balance']);
        $this->assertEquals(700.00, $bankData['amount']); // 500 + 200

        $cashData = $accounts->firstWhere('id', $cashAccount->id);
        $this->assertNotNull($cashData);
        $this->assertEquals(50.00, $cashData['initial_balance']);
        $this->assertEquals(35.00, $cashData['amount']); // 50 - 15
    }
}
