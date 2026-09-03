<?php

namespace App\Domain\QuestionBank\Models;

use App\Domain\Identity\Models\User;
use App\Domain\QuestionBank\Enums\QuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Question extends Model
{
    use SoftDeletes;

    protected $fillable = ['owner_id', 'type', 'body', 'expected_answer', 'teacher_instruction', 'max_score', 'is_active'];
    protected $casts = ['type' => QuestionType::class, 'max_score' => 'decimal:2', 'is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string { return 'public_id'; }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function options(): HasMany { return $this->hasMany(QuestionOption::class)->orderBy('position'); }
    public function rubricCriteria(): HasMany { return $this->hasMany(RubricCriterion::class)->orderBy('position'); }
}
