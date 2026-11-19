<?php

namespace App\Sync;

use RuntimeException;

/** The operation refers to a record the server does not have (yet). */
class MissingReference extends RuntimeException
{
    public function __construct(public readonly string $reference, string $what)
    {
        parent::__construct("The {$what} this change refers to is not on the server yet.");
    }
}
