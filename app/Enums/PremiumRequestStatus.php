<?php

declare(strict_types=1);

namespace App\Enums;

enum PremiumRequestStatus: string
{
    case PendingVerification = 'pending_verification';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
