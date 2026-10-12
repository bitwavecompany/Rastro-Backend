<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

interface SyncCommandInterface
{
    public function execute(User $user): void;
}
