<?php

namespace App\Domain\Grading\Models;

use Illuminate\Database\Eloquent\Model;

class PromptTemplate extends Model
{
    protected $fillable = ['key', 'version', 'content', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
