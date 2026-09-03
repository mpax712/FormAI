<?php

namespace App\Domain\Submissions\Models;

use App\Domain\Activities\Models\Activity;
use App\Domain\Grading\Models\GradingDecision;
use App\Domain\Identity\Models\User;
use App\Domain\Submissions\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Submission extends Model
{
    protected $fillable = ['activity_id', 'student_id', 'status', 'version', 'submitted_at', 'reviewed_at', 'released_at', 'reopened_until', 'objective_score', 'final_score'];
    protected $casts = ['status' => SubmissionStatus::class, 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'released_at' => 'datetime', 'reopened_until' => 'datetime', 'objective_score' => 'decimal:2', 'final_score' => 'decimal:2'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string { return 'public_id'; }
    public function activity(): BelongsTo { return $this->belongsTo(Activity::class); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
    public function answers(): HasMany { return $this->hasMany(Answer::class); }
    public function decisions(): HasMany { return $this->hasManyThrough(GradingDecision::class, Answer::class); }
}
