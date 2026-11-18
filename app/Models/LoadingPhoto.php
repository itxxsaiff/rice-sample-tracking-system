<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadingPhoto extends Model
{
    protected $fillable = ['path', 'original_name', 'mime', 'size'];

    public function loading(): BelongsTo
    {
        return $this->belongsTo(Loading::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
