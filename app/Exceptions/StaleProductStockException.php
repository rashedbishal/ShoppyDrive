<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Stock changed after the admin product form loaded, so the save was refused. */
class StaleProductStockException extends RuntimeException {}
