<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A courier send, or an order action that conflicts with one, was refused
 * before anything was sent. The message is shown to the admin as-is.
 */
class CourierSubmissionBlockedException extends RuntimeException {}
