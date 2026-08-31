<?php

namespace App\Providers;

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
        Str::createUuidsUsing(function () {
            return (string) Uuid::uuid7();
        });
    }
}