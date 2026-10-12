<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncEntity: string
{
    case Accounts = 'accounts';
    case Transactions = 'transactions';
}
