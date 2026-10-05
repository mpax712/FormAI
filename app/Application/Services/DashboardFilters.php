<?php

namespace App\Application\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardFilters
{
    public function resolve(Request $request): array
    {
        $data = $request->validate([
            'classroom' => ['nullable', Rule::exists('classrooms', 'public_id')->where('teacher_id', $request->user()->id)],
            'period' => ['nullable', Rule::in(['30', '90', 'custom', 'all'])],
            'from' => ['exclude_unless:period,custom', 'required', 'date_format:Y-m-d'],
            'to' => ['exclude_unless:period,custom', 'required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        return array_replace(['classroom' => null, 'period' => '30', 'from' => null, 'to' => null, 'page' => 1], array_filter($data, fn ($value) => $value !== null));
    }
}
