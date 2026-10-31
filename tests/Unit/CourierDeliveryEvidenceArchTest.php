<?php

declare(strict_types=1);

/*
 * PAY-RETURN-001: structured courier evidence is trusted only because a
 * single, known set of classes can write it. These rules fail the build if
 * any other application class starts using the append-only models.
 */

arch('courier delivery evidence is written only by the mapper and the courier send / reconcile paths')
    ->expect('App\Models\CourierDeliveryEvidence')
    ->toOnlyBeUsedIn([
        'App\Services\CourierOrderStatusMapper',
        'App\Services\CourierSubmissionService',
        'App\Services\CourierEvidenceChain',           // reads only
        'App\Services\OrderDeliveryVerificationService', // reads only (snapshot)
        'App\Models\Order',                            // relation
    ]);

arch('delivery verifications are written only by the verification service')
    ->expect('App\Models\OrderDeliveryVerification')
    ->toOnlyBeUsedIn([
        'App\Services\OrderDeliveryVerificationService',
        'App\Services\CourierEvidenceChain', // reads only
        'App\Models\Order',                  // relation
        'Database\Factories\OrderDeliveryVerificationFactory', // test data only
    ]);

arch('readiness attestations are written only by the readiness service')
    ->expect('App\Models\CourierVerificationReadiness')
    ->toOnlyBeUsedIn([
        'App\Services\CourierVerificationReadinessService',
        'App\Services\CourierEvidenceChain',                        // reads only
        'App\Models\OrderDeliveryVerification',                     // relation
        'App\Http\Controllers\Admin\CourierVerificationReadinessController', // reads only (history list)
        'Database\Factories\CourierVerificationReadinessFactory',          // test data only
    ]);

arch('the refund ledger is written only by the refund service')
    ->expect('App\Models\OrderRefund')
    ->toOnlyBeUsedIn([
        'App\Services\RefundService',
        'App\Services\RefundCalculator',     // reads only
        'App\Services\LoyaltyPointsService', // reads only
        'App\Models\Order',                  // relation
        'Database\Factories\OrderRefundFactory', // test data only
    ]);
