<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
}

if (! class_exists('App\Enums\TransactionKind', false) && ! enum_exists('App\Enums\TransactionKind', false)) {
    class_alias(TransactionType::class, 'App\Enums\TransactionKind');
}
