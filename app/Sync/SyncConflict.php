<?php

namespace App\Sync;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** The record changed on the server since the device last saw it. */
class SyncConflict extends RuntimeException
{
    public function __construct(public readonly Model $current, string $message = 'This record was changed by someone else since your device last synced.')
    {
        parent::__construct($message);
    }
}
