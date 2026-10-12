<?php

declare(strict_types=1);

namespace App\Enums;

enum ErrorCode: string
{
    case SessionLimitReached = 'SESSION_LIMIT_REACHED';
    case SessionRevokedRemote = 'SESSION_REVOKED_REMOTE';
    case InvalidRefreshToken = 'INVALID_REFRESH_TOKEN';
}
