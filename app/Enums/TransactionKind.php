<?php

declare(strict_types=1);

namespace App\Enums;

if (! enum_exists('App\Enums\TransactionType', false)) {
    require_once __DIR__.'/TransactionType.php';
}

if (! class_exists('App\Enums\TransactionKind', false) && ! enum_exists('App\Enums\TransactionKind', false)) {
    class_alias(TransactionType::class, 'App\Enums\TransactionKind');
}
