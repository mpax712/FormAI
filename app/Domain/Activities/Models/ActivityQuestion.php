<?php

namespace App\Domain\Activities\Models;

use App\Domain\QuestionBank\Enums\QuestionType;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\Submissions\Models\Answer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ActivityQuestion extends Model
{
    protected $fillable = ['activity_id', 'source_question_id', 'type', 'body', 'expected_answer', 'teacher_instruction', 'max_score', 'options_snapshot', 'rubric_snapshot', 'position'];
    protected $casts = ['type' => QuestionType::class, 'max_score' => 'decimal:2', 'options_snapshot' => 'array', 'rubric_snapshot' => 'array'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string { return 'public_id'; }
    public function activity(): BelongsTo { return $this->belongsTo(Activity::class); }
    public function sourceQuestion(): BelongsTo { return $this->belongsTo(Question::class, 'source_question_id'); }
    public function answers(): HasMany { return $this->hasMany(Answer::class); }
}
