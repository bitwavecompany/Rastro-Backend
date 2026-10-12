<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Commands\Sync\Accounts\CreateAccountCommand;
use App\Commands\Sync\Accounts\DeleteAccountCommand;
use App\Commands\Sync\Accounts\HardDeleteAccountCommand;
use App\Commands\Sync\Accounts\RestoreAccountCommand;
use App\Commands\Sync\Accounts\UpdateAccountCommand;
use App\Commands\Sync\Transactions\CreateTransactionCommand;
use App\Commands\Sync\Transactions\DeleteTransactionCommand;
use App\Commands\Sync\Transactions\HardDeleteTransactionCommand;
use App\Commands\Sync\Transactions\PurgeAccountTransactionsCommand;
use App\Commands\Sync\Transactions\RelocateTransactionCommand;
use App\Commands\Sync\Transactions\RestoreTransactionCommand;
use App\Commands\Sync\Transactions\UpdateTransactionCommand;
use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use App\DTOs\Sync\BatchOperationData;
use App\Enums\SyncEntity;
use App\Enums\SyncOperation;
use App\Factories\SyncCommandFactory;
use Exception;
use PHPUnit\Framework\TestCase;

class SyncCommandFactoryTest extends TestCase
{
    public function test_creates_account_commands_correctly(): void
    {
        $createOp = new BatchOperationData('op-1', SyncEntity::Accounts, SyncOperation::Create, ['name' => 'Test']);
        $this->assertInstanceOf(CreateAccountCommand::class, SyncCommandFactory::make($createOp));

        $updateOp = new BatchOperationData('op-2', SyncEntity::Accounts, SyncOperation::Update, ['name' => 'Test']);
        $this->assertInstanceOf(UpdateAccountCommand::class, SyncCommandFactory::make($updateOp));

        $deleteOp = new BatchOperationData('op-3', SyncEntity::Accounts, SyncOperation::Delete, ['id' => '123']);
        $this->assertInstanceOf(DeleteAccountCommand::class, SyncCommandFactory::make($deleteOp));

        $restoreOp = new BatchOperationData('op-4', SyncEntity::Accounts, SyncOperation::Restore, ['id' => '123']);
        $this->assertInstanceOf(RestoreAccountCommand::class, SyncCommandFactory::make($restoreOp));

        $hardDeleteOp = new BatchOperationData('op-5', SyncEntity::Accounts, SyncOperation::HardDelete, ['id' => '123']);
        $this->assertInstanceOf(HardDeleteAccountCommand::class, SyncCommandFactory::make($hardDeleteOp));
    }

    public function test_creates_transaction_commands_correctly(): void
    {
        $createOp = new BatchOperationData('op-1', SyncEntity::Transactions, SyncOperation::Create, []);
        $this->assertInstanceOf(CreateTransactionCommand::class, SyncCommandFactory::make($createOp));

        $updateOp = new BatchOperationData('op-2', SyncEntity::Transactions, SyncOperation::Update, []);
        $this->assertInstanceOf(UpdateTransactionCommand::class, SyncCommandFactory::make($updateOp));

        $deleteOp = new BatchOperationData('op-3', SyncEntity::Transactions, SyncOperation::Delete, []);
        $this->assertInstanceOf(DeleteTransactionCommand::class, SyncCommandFactory::make($deleteOp));

        $restoreOp = new BatchOperationData('op-4', SyncEntity::Transactions, SyncOperation::Restore, []);
        $this->assertInstanceOf(RestoreTransactionCommand::class, SyncCommandFactory::make($restoreOp));

        $hardDeleteOp = new BatchOperationData('op-5', SyncEntity::Transactions, SyncOperation::HardDelete, []);
        $this->assertInstanceOf(HardDeleteTransactionCommand::class, SyncCommandFactory::make($hardDeleteOp));

        $purgeOp = new BatchOperationData('op-6', SyncEntity::Transactions, SyncOperation::PurgeAccount, []);
        $this->assertInstanceOf(PurgeAccountTransactionsCommand::class, SyncCommandFactory::make($purgeOp));

        $relocateOp = new BatchOperationData('op-7', SyncEntity::Transactions, SyncOperation::Relocate, []);
        $this->assertInstanceOf(RelocateTransactionCommand::class, SyncCommandFactory::make($relocateOp));
    }

    public function test_resolves_contract_aliases_through_dto_from_array(): void
    {
        $opHardDelete = BatchOperationData::fromArray([
            'opId' => '01a12cab-21d5-711d-be0e-245c0635feea',
            'entity' => 'accounts',
            'op' => 'force-delete',
            'payload' => [],
        ]);
        $this->assertEquals(SyncOperation::HardDelete, $opHardDelete->op);
        $this->assertInstanceOf(HardDeleteAccountCommand::class, SyncCommandFactory::make($opHardDelete));

        $opTransferRecord = BatchOperationData::fromArray([
            'opId' => '01a12cab-21d5-711d-be0e-245c0635feeb',
            'entity' => 'transactions',
            'op' => 'transfer-record',
            'payload' => [],
        ]);
        $this->assertEquals(SyncOperation::Relocate, $opTransferRecord->op);
        $this->assertInstanceOf(RelocateTransactionCommand::class, SyncCommandFactory::make($opTransferRecord));

        $opPurge = BatchOperationData::fromArray([
            'opId' => '01a12cab-21d5-711d-be0e-245c0635feec',
            'entity' => 'transactions',
            'op' => 'purgeAccount',
            'payload' => [],
        ]);
        $this->assertEquals(SyncOperation::PurgeAccount, $opPurge->op);
        $this->assertInstanceOf(PurgeAccountTransactionsCommand::class, SyncCommandFactory::make($opPurge));
    }

    public function test_unsupported_operation_for_entity_throws_centralized_exception(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(ApiMessage::UNSUPPORTED_OPERATION);
        $this->expectExceptionCode(HttpCode::BAD_REQUEST);

        // PurgeAccount is not supported for accounts entity
        $invalidOp = new BatchOperationData('op-err', SyncEntity::Accounts, SyncOperation::PurgeAccount, []);
        SyncCommandFactory::make($invalidOp);
    }
}
