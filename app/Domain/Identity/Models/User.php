<?php

namespace App\Domain\Identity\Models;

use App\Domain\Activities\Models\Activity;
use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\QuestionBank\Models\Question;
use App\Domain\Submissions\Models\Submission;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Database\Factories\UserFactory;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'avatar_path'];

    protected $hidden = ['password', 'remember_token', 'mfa_code_hash'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deleted_requested_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'mfa_expires_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $user) => $user->public_id ??= (string) Str::ulid());
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isAdmin(): bool { return $this->role === UserRole::Admin; }
    public function isTeacher(): bool { return $this->role === UserRole::Teacher; }
    public function isStudent(): bool { return $this->role === UserRole::Student; }
    public function avatarUrl(): ?string { return $this->avatar_path ? Storage::disk('avatars')->url($this->avatar_path) : null; }

    public function classroomsOwned(): HasMany { return $this->hasMany(Classroom::class, 'teacher_id'); }
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_memberships')
            ->wherePivot('status', 'approved')
            ->withPivot(['status', 'approved_at', 'approved_by'])
            ->withTimestamps();
    }
    public function pendingClassrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'classroom_memberships')
            ->wherePivot('status', 'pending')
            ->withPivot(['status', 'approved_at', 'approved_by'])
            ->withTimestamps();
    }
    public function questions(): HasMany { return $this->hasMany(Question::class, 'owner_id'); }
    public function activities(): HasMany { return $this->hasMany(Activity::class, 'teacher_id'); }
    public function submissions(): HasMany { return $this->hasMany(Submission::class, 'student_id'); }
}
