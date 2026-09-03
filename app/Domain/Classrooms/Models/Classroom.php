<?php

namespace App\Domain\Classrooms\Models;

use App\Domain\Activities\Models\Activity;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Classroom extends Model
{
    protected $fillable = ['teacher_id', 'name', 'description', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->public_id ??= (string) Str::ulid();
            $model->join_code ??= self::newJoinCode();
        });
    }

    public function getRouteKeyName(): string { return 'public_id'; }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_memberships')
            ->withPivot(['status', 'approved_at', 'approved_by'])
            ->withTimestamps();
    }
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_memberships')
            ->wherePivot('status', 'approved')
            ->withPivot(['status', 'approved_at', 'approved_by'])
            ->withTimestamps();
    }
    public function pendingStudents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_memberships')
            ->wherePivot('status', 'pending')
            ->withPivot(['status', 'approved_at', 'approved_by'])
            ->withTimestamps();
    }
    public function activities(): HasMany { return $this->hasMany(Activity::class); }
    public function invitations(): HasMany { return $this->hasMany(Invitation::class); }

    private static function newJoinCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = collect(range(1, 8))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        } while (self::query()->where('join_code', $code)->exists());

        return $code;
    }
}
