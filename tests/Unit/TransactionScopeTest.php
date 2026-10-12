<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AccountType;
use App\Enums\TransactionKind;
use App\Enums\UserPlan;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_for_user_plan_filters_to_six_months_for_free_users(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->create([
            'name' => 'Efectivo',
            'type' => AccountType::Cash,
            'amount' => 100,
        ]);

        $recentTx = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'kind' => TransactionKind::Income,
            'amount' => 50.0,
            'reason' => 'Reciente',
            'paymentType' => 'cash',
            'date' => now()->subMonths(2),
        ]);

        $oldTx = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'amount' => 30.0,
            'reason' => 'Antiguo',
            'paymentType' => 'cash',
            'date' => now()->subMonths(8),
        ]);

        $freeResults = Transaction::forUserPlan(UserPlan::Free)->pluck('id')->all();
        $this->assertContains($recentTx->id, $freeResults);
        $this->assertNotContains($oldTx->id, $freeResults);

        $premiumResults = Transaction::forUserPlan(UserPlan::Premium)->pluck('id')->all();
        $this->assertContains($recentTx->id, $premiumResults);
        $this->assertContains($oldTx->id, $premiumResults);
    }

    public function test_scope_active_only_filters_soft_deleted_records(): void
    {
        $user = User::factory()->create();
        $account = $user->accounts()->create([
            'name' => 'Efectivo',
            'type' => AccountType::Cash,
            'amount' => 100,
        ]);

        $activeTx = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'kind' => TransactionKind::Income,
            'amount' => 50.0,
            'reason' => 'Activo',
            'paymentType' => 'cash',
        ]);

        $deletedTx = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'kind' => TransactionKind::Expense,
            'amount' => 20.0,
            'reason' => 'Borrado',
            'paymentType' => 'cash',
        ]);
        $deletedTx->delete();

        $activeOnlyResults = Transaction::activeOnly(true)->pluck('id')->all();
        $this->assertContains($activeTx->id, $activeOnlyResults);
        $this->assertNotContains($deletedTx->id, $activeOnlyResults);

        $allResults = Transaction::activeOnly(false)->pluck('id')->all();
        $this->assertContains($activeTx->id, $allResults);
        $this->assertContains($deletedTx->id, $allResults);
    }

    public function test_scope_of_kind_and_for_account(): void
    {
        $user = User::factory()->create();
        $account1 = $user->accounts()->create([
            'name' => 'Billetera',
            'type' => AccountType::Cash,
            'amount' => 100,
        ]);
        $account2 = $user->accounts()->create([
            'name' => 'Banco',
            'type' => AccountType::Bank,
            'amount' => 200,
        ]);

        $txIncome = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account1->id,
            'kind' => TransactionKind::Income,
            'amount' => 40.0,
            'reason' => 'Ingreso',
            'paymentType' => 'cash',
        ]);

        $txExpense = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account2->id,
            'kind' => TransactionKind::Expense,
            'amount' => 25.0,
            'reason' => 'Gasto',
            'paymentType' => 'transfer',
        ]);

        // Filter by kind
        $incomeResults = Transaction::ofKind(TransactionKind::Income)->pluck('id')->all();
        $this->assertContains($txIncome->id, $incomeResults);
        $this->assertNotContains($txExpense->id, $incomeResults);

        // Filter by account
        $acc2Results = Transaction::forAccount($account2->id)->pluck('id')->all();
        $this->assertContains($txExpense->id, $acc2Results);
        $this->assertNotContains($txIncome->id, $acc2Results);

        // Empty filter returns all
        $allResults = Transaction::ofKind(null)->forAccount(null)->pluck('id')->all();
        $this->assertContains($txIncome->id, $allResults);
        $this->assertContains($txExpense->id, $allResults);
    }

    public function test_account_scopes_active_only_and_of_type(): void
    {
        $user = User::factory()->create();
        $cashAcc = $user->accounts()->create([
            'name' => 'Cash Account',
            'type' => AccountType::Cash,
            'amount' => 50,
        ]);
        $bankAcc = $user->accounts()->create([
            'name' => 'Bank Account',
            'type' => AccountType::Bank,
            'amount' => 150,
        ]);
        $deletedAcc = $user->accounts()->create([
            'name' => 'Old Bank',
            'type' => AccountType::Bank,
            'amount' => 25,
        ]);
        $deletedAcc->delete();

        $activeAccounts = Account::activeOnly(true)->pluck('id')->all();
        $this->assertContains($cashAcc->id, $activeAccounts);
        $this->assertContains($bankAcc->id, $activeAccounts);
        $this->assertNotContains($deletedAcc->id, $activeAccounts);

        $bankAccounts = Account::ofType(AccountType::Bank)->activeOnly(true)->pluck('id')->all();
        $this->assertContains($bankAcc->id, $bankAccounts);
        $this->assertNotContains($cashAcc->id, $bankAccounts);
    }
}
