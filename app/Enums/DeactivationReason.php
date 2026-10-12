<?php

declare(strict_types=1);

namespace App\Enums;

enum DeactivationReason: string
{
    case UserAction = 'user_action';
    case PlanDowngrade = 'plan_downgrade';
}
