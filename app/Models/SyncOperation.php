<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An offline operation that has been applied (see App\Sync\SyncOperationService). */
class SyncOperation extends Model
{
    public const UPDATED_AT = null;

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['uuid', 'user_id', 'type', 'result'];

    protected function casts(): array
    {
        return ['result' => 'array', 'created_at' => 'datetime'];
    }
}
