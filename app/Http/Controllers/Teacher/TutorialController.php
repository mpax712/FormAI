<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorialController extends Controller
{
    public function seen(Request $request): JsonResponse
    {
        abort_unless($request->user()->isTeacher(), 403);
        $request->validate([
            'user_id' => ['prohibited'],
            'step' => ['prohibited'],
            'url' => ['prohibited'],
            'route' => ['prohibited'],
        ]);
        $request->user()->newQuery()->whereKey($request->user()->id)
            ->whereNull('teacher_tutorial_seen_at')->update(['teacher_tutorial_seen_at' => now()]);

        return response()->json(['seen' => true]);
    }
}
