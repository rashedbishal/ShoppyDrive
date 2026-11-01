<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** The cart no longer matches what checkout priced, so nothing was charged. */
class CartChangedException extends RuntimeException {}
