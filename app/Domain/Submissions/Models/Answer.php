<?php

namespace App\Domain\Submissions\Models;

use App\Domain\Activities\Models\ActivityQuestion;
use App\Domain\Grading\Models\GradingDecision;
use App\Domain\Grading\Models\GradingRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Answer extends Model
{
    protected $fillable = ['activity_question_id', 'response_text', 'selected_option_key', 'version'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function activityQuestion(): BelongsTo
    {
        return $this->belongsTo(ActivityQuestion::class);
    }

    public function gradingRuns(): HasMany
    {
        return $this->hasMany(GradingRun::class);
    }

    public function latestGradingRun(): HasOne
    {
        return $this->hasOne(GradingRun::class)->latestOfMany();
    }

    public function gradingDecision(): HasOne
    {
        return $this->hasOne(GradingDecision::class);
    }
}
