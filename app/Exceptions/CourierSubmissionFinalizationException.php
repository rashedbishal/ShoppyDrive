<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The courier confirmed a consignment, but recording it on the order failed.
 * The consignment ID has already been saved on the attempt
 * (`returned_consignment_id`) whenever the database allowed it, and the
 * claim is kept, so the order can't be sent again until an admin links it.
 */
class CourierSubmissionFinalizationException extends RuntimeException
{
    public function __construct(
        public readonly string $courier,
        public readonly string $consignmentId,
        ?Throwable $previous = null,
    ) {
        parent::__construct("The courier created consignment {$consignmentId}, but it could not be recorded on the order.", 0, $previous);
    }
}
