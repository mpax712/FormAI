<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorialController extends Controller
{
    public function seen(Request $request): JsonResponse
    {
        abort_unless($request->user()->isStudent(), 403);
        $request->validate([
            'user_id' => ['prohibited'], 'step' => ['prohibited'], 'url' => ['prohibited'], 'route' => ['prohibited'],
        ]);
        $request->user()->newQuery()->whereKey($request->user()->id)
            ->whereNull('student_tutorial_seen_at')->update(['student_tutorial_seen_at' => now()]);

        return response()->json(['seen' => true]);
    }
}
