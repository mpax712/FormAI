<?php

namespace App\Domain\Grading\Models;

use App\Domain\Grading\Enums\GradingRunStatus;
use App\Domain\Submissions\Models\Answer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class GradingRun extends Model
{
    protected $fillable = ['answer_id', 'idempotency_key', 'status', 'provider', 'model', 'prompt_version', 'attempts', 'started_at', 'finished_at', 'input_tokens', 'output_tokens', 'estimated_cost', 'error_code', 'error_message', 'request_snapshot', 'execution_plan', 'destination_index', 'technical_failures', 'wait_reason', 'next_attempt_at', 'expires_at'];

    protected $hidden = ['request_snapshot', 'execution_plan'];

    protected $casts = ['status' => GradingRunStatus::class, 'started_at' => 'datetime', 'finished_at' => 'datetime', 'estimated_cost' => 'decimal:6', 'request_snapshot' => 'encrypted:array', 'execution_plan' => 'array', 'next_attempt_at' => 'datetime', 'expires_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
        static::updating(function (self $model) {
            if ($model->isDirty(['request_snapshot', 'execution_plan', 'idempotency_key', 'answer_id'])) {
                throw new \DomainException('O conteúdo e a configuração de uma execução são imutáveis.');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function retryAvailableAt(): \Illuminate\Support\Carbon
    {
        return ($this->finished_at ?? $this->created_at)->copy()
            ->addSeconds(config('ai_grading.retry_cooldown_seconds', 300));
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }

    public function suggestion(): HasOne
    {
        return $this->hasOne(GradingSuggestion::class);
    }

    public function attemptRecords(): HasMany
    {
        return $this->hasMany(GradingAttempt::class)->orderBy('number');
    }
}
