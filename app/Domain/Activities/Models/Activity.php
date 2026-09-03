<?php

namespace App\Domain\Activities\Models;

use App\Domain\Activities\Enums\ActivityStatus;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Models\User;
use App\Domain\Submissions\Models\Submission;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Activity extends Model
{
    protected $fillable = ['teacher_id', 'classroom_id', 'title', 'description', 'deadline_at', 'status', 'published_at', 'released_at', 'total_score'];
    protected $casts = ['status' => ActivityStatus::class, 'deadline_at' => 'datetime', 'published_at' => 'datetime', 'released_at' => 'datetime', 'total_score' => 'decimal:2'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
    }

    public function getRouteKeyName(): string { return 'public_id'; }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function questions(): HasMany { return $this->hasMany(ActivityQuestion::class)->orderBy('position'); }
    public function submissions(): HasMany { return $this->hasMany(Submission::class); }

    public function ensureDraft(): void
    {
        if ($this->status !== ActivityStatus::Draft) {
            throw new DomainException('Somente atividades em rascunho podem ser alteradas.');
        }
    }
}
