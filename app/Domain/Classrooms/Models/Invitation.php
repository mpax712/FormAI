<?php

namespace App\Domain\Classrooms\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = ['email', 'token_hash', 'expires_at', 'accepted_at', 'invited_by'];
    protected $hidden = ['token_hash'];
    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function classroom(): BelongsTo { return $this->belongsTo(Classroom::class); }
    public function inviter(): BelongsTo { return $this->belongsTo(User::class, 'invited_by'); }
}
