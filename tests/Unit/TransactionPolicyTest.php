<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UserPlan;
use App\Models\User;
use App\Policies\TransactionPolicy;
use PHPUnit\Framework\TestCase;

class TransactionPolicyTest extends TestCase
{
    public function test_usuario_premium_puede_exportar_transacciones(): void
    {
        $policy = new TransactionPolicy;
        $user = new User;
        $user->plan = UserPlan::Premium;

        $this->assertTrue($policy->export($user));
    }

    public function test_usuario_free_no_puede_exportar_transacciones(): void
    {
        $policy = new TransactionPolicy;
        $user = new User;
        $user->plan = UserPlan::Free;

        $this->assertFalse($policy->export($user));
    }
}
