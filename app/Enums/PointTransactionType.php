<?php

declare(strict_types=1);

namespace App\Enums;

enum PointTransactionType: string
{
    case EARN_ORDER = 'earn_order';
    case EARN_REFERRAL = 'earn_referral';
    case EARN_REGISTRATION = 'earn_registration';
    case EARN_REVIEW = 'earn_review';
    case EARN_DAILY_LOGIN = 'earn_daily_login';
    case REDEEM = 'redeem';
    case REVOKE = 'revoke';
    case REFUND = 'refund';
}
