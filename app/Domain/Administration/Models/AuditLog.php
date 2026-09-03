<?php

namespace App\Domain\Administration\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = ['metadata' => 'encrypted:array'];
    protected static function booted(): void
    {
        static::updating(fn () => throw new \DomainException('Registros de auditoria sao imutaveis.'));
        static::deleting(fn () => throw new \DomainException('Registros de auditoria sao imutaveis.'));
    }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
