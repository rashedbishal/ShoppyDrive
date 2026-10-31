<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * PAY-RETURN-001: recording a refund was refused (nothing was written). The
 * message is safe to show the admin.
 */
class CouldNotRecordRefundException extends RuntimeException {}
