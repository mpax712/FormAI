<?php

namespace App\Domain\Grading\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingSuggestion extends Model
{
    public $timestamps = false;
    protected $fillable = ['score', 'criterion_scores', 'evidence', 'feedback', 'confidence', 'warnings', 'created_at'];
    protected $casts = ['score' => 'decimal:2', 'criterion_scores' => 'array', 'evidence' => 'array', 'warnings' => 'array', 'confidence' => 'decimal:4', 'created_at' => 'datetime'];
    protected static function booted(): void
    {
        static::updating(fn () => throw new \DomainException('Sugestoes de IA sao imutaveis.'));
        static::deleting(fn () => throw new \DomainException('Sugestoes de IA sao imutaveis.'));
    }
    public function run(): BelongsTo { return $this->belongsTo(GradingRun::class, 'grading_run_id'); }
}
