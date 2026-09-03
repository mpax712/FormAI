<?php

namespace App\Domain\Grading\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Submissions\Models\Answer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingDecision extends Model
{
    public $timestamps = false;
    protected $fillable = ['answer_id', 'grading_suggestion_id', 'reviewer_id', 'score', 'feedback', 'confirmed_at'];
    protected $casts = ['score' => 'decimal:2', 'confirmed_at' => 'datetime'];
    public function answer(): BelongsTo { return $this->belongsTo(Answer::class); }
    public function suggestion(): BelongsTo { return $this->belongsTo(GradingSuggestion::class, 'grading_suggestion_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
}
