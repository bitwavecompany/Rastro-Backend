<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Restore = 'restore';
    case HardDelete = 'hard-delete';
    case PurgeAccount = 'purge-account';
    case PurgeAll = 'purge-all';
    case Relocate = 'relocate';

    public static function tryFromAlias(string $value): ?self
    {
        return match ($value) {
            'create' => self::Create,
            'update' => self::Update,
            'delete' => self::Delete,
            'restore' => self::Restore,
            'hard-delete', 'hardDelete', 'force-delete' => self::HardDelete,
            'purge-account', 'purgeAccount' => self::PurgeAccount,
            'purge-all', 'purgeAll' => self::PurgeAll,
            'relocate', 'transfer-record' => self::Relocate,
            default => self::tryFrom($value),
        };
    }
}
