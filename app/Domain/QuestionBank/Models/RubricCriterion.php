<?php

namespace App\Domain\QuestionBank\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricCriterion extends Model
{
    public $timestamps = false;
    protected $fillable = ['label', 'description', 'weight', 'position'];
    protected $casts = ['weight' => 'decimal:4'];
    public function question(): BelongsTo { return $this->belongsTo(Question::class); }
}
