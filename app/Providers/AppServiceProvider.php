<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Transaction;
use App\Policies\TransactionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Transaction::class, TransactionPolicy::class);

        Str::createUuidsUsing(function () {
            return (string) Uuid::uuid7();
        });
    }
}
