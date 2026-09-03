<?php

namespace App\Domain\Grading\Models;

use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\Submissions\Models\Answer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class GradingRun extends Model
{
    protected $fillable = ['answer_id', 'idempotency_key', 'status', 'provider', 'model', 'prompt_version', 'attempts', 'started_at', 'finished_at', 'input_tokens', 'output_tokens', 'estimated_cost', 'error_code', 'error_message'];
    protected $casts = ['status' => GradingRunStatus::class, 'started_at' => 'datetime', 'finished_at' => 'datetime', 'estimated_cost' => 'decimal:6'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string { return 'public_id'; }
    public function answer(): BelongsTo { return $this->belongsTo(Answer::class); }
    public function suggestion(): HasOne { return $this->hasOne(GradingSuggestion::class); }
}
