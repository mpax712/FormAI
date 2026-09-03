<?php

namespace App\Infrastructure\Observability\Models;

use Illuminate\Database\Eloquent\Model;

class SystemHeartbeat extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'last_seen_at', 'metadata'];
    protected $casts = ['last_seen_at' => 'datetime', 'metadata' => 'array'];
}
