<?php

namespace App\Domain\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    public $timestamps = false;
    protected $fillable = ['option_key', 'text', 'is_correct', 'position'];
    protected $casts = ['is_correct' => 'boolean'];
    public function question(): BelongsTo { return $this->belongsTo(Question::class); }
}
