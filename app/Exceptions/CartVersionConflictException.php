<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A device saved its cart based on an older copy of the server cart
 * (another device or server-side action changed it since), so nothing was
 * written. The device fetches the current cart and re-applies its change.
 */
class CartVersionConflictException extends RuntimeException {}
