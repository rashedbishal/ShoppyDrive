<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * PAY-RETURN-001: what a courier_delivery_evidence row records.
 *
 * - ANCHOR: the start of a consignment's chain (written when the consignment
 *   is stored — `send` when this app created it, `reconcile` when an admin
 *   linked it later, which is never trusted).
 * - TRANSITION / OBSERVATION: a courier raw-status change that did / did not
 *   move the order's own status.
 * - GAP: a detected break in the chain (a raw status changed without
 *   evidence, or a courier history note has no evidence row).
 * - LEGACY_PARTIAL: partial-delivery evidence recovered from pre-evidence
 *   data by the migration backfill. It can only ever block a refund.
 */
enum CourierEvidenceType: string
{
    case ANCHOR = 'anchor';
    case TRANSITION = 'transition';
    case OBSERVATION = 'observation';
    case GAP = 'gap';
    case LEGACY_PARTIAL = 'legacy_partial';
}
