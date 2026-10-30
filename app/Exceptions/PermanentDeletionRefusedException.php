<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A step of the mother-admin permanent order deletion was refused. The
 * message is safe to show the admin; `field` is the form field it belongs to.
 */
class PermanentDeletionRefusedException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'password')
    {
        parent::__construct($message);
    }
}
