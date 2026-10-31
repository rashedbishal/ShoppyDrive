<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * PAY-RETURN-001: where a piece of courier evidence came from. Always passed
 * as a typed argument by the fixed call site that received the courier's
 * data — never taken from request input.
 */
enum CourierEvidenceSource: string
{
    case COURIER_SEND = 'courier_send';
    case COURIER_RECONCILE_RETURNED = 'courier_reconcile_returned';
    case COURIER_RECONCILE_MANUAL = 'courier_reconcile_manual';
    case COURIER_RECONCILE_REFRESH = 'courier_reconcile_refresh';
    case PATHAO_POLL = 'pathao_poll';
    case PATHAO_ADMIN_REFRESH = 'pathao_admin_refresh';
    case STEADFAST_WEBHOOK = 'steadfast_webhook';
    case STEADFAST_ADMIN_REFRESH = 'steadfast_admin_refresh';
    case LEGACY_BACKFILL = 'legacy_backfill';
    case UNSPECIFIED = 'unspecified';
}
