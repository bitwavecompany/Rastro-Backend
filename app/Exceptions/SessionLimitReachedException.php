<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Constants\ApiMessage;
use App\Constants\HttpCode;
use Exception;

class SessionLimitReachedException extends Exception
{
    public function __construct(string $message = ApiMessage::SESSION_LIMIT_REACHED, int $code = HttpCode::CONFLICT)
    {
        parent::__construct($message, $code);
    }
}
