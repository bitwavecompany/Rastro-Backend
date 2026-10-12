<?php

declare(strict_types=1);

namespace App\Factories;

use App\Commands\Sync\Accounts\CreateAccountCommand;
use App\Commands\Sync\Accounts\DeleteAccountCommand;
use App\Commands\Sync\Accounts\HardDeleteAccountCommand;
use App\Commands\Sync\Accounts\RestoreAccountCommand;
use App\Commands\Sync\Accounts\UpdateAccountCommand;
use App\Commands\Sync\Transactions\CreateTransactionCommand;
use App\Commands\Sync\Transactions\DeleteTransactionCommand;
use App\Commands\Sync\Transactions\HardDeleteTransactionCommand;
use App\Commands\Sync\Transactions\PurgeAccountTransactionsCommand;
use App\Commands\Sync\Transactions\PurgeAllTransactionsCommand;
use App\Commands\Sync\Transactions\RelocateTransactionCommand;
use App\Commands\Sync\Transactions\RestoreTransactionCommand;
use App\Commands\Sync\Transactions\UpdateTransactionCommand;
use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\Contracts\SyncCommandInterface;
use App\DTOs\Sync\BatchOperationData;
use App\Enums\SyncEntity;
use App\Enums\SyncOperation;
use Exception;

class SyncCommandFactory
{
    public static function make(BatchOperationData $operation): SyncCommandInterface
    {
        return match ($operation->entity) {
            SyncEntity::Accounts => self::makeAccountCommand($operation),
            SyncEntity::Transactions => self::makeTransactionCommand($operation),
        };
    }

    private static function makeAccountCommand(BatchOperationData $operation): SyncCommandInterface
    {
        return match ($operation->op) {
            SyncOperation::Create => new CreateAccountCommand($operation->payload),
            SyncOperation::Update => new UpdateAccountCommand($operation->payload),
            SyncOperation::Delete => new DeleteAccountCommand($operation->payload),
            SyncOperation::Restore => new RestoreAccountCommand($operation->payload),
            SyncOperation::HardDelete => new HardDeleteAccountCommand($operation->payload),
            default => throw new Exception(ApiMessage::UNSUPPORTED_OPERATION, HttpCode::BAD_REQUEST),
        };
    }

    private static function makeTransactionCommand(BatchOperationData $operation): SyncCommandInterface
    {
        return match ($operation->op) {
            SyncOperation::Create => new CreateTransactionCommand($operation->payload),
            SyncOperation::Update => new UpdateTransactionCommand($operation->payload),
            SyncOperation::Delete => new DeleteTransactionCommand($operation->payload),
            SyncOperation::Restore => new RestoreTransactionCommand($operation->payload),
            SyncOperation::HardDelete => new HardDeleteTransactionCommand($operation->payload),
            SyncOperation::PurgeAccount => new PurgeAccountTransactionsCommand($operation->payload),
            SyncOperation::PurgeAll => new PurgeAllTransactionsCommand($operation->payload),
            SyncOperation::Relocate => new RelocateTransactionCommand($operation->payload),
            default => throw new Exception(ApiMessage::UNSUPPORTED_OPERATION, HttpCode::BAD_REQUEST),
        };
    }
}
