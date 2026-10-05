<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('teacher_tutorial_seen_at')->nullable();
        });

        DB::table('users')->where('role', 'teacher')->update(['teacher_tutorial_seen_at' => now()]);
        Schema::dropIfExists('tutorial_progress');

        Schema::table('activities', function (Blueprint $table) {
            $table->index(['teacher_id', 'published_at'], 'activities_teacher_published_index');
        });
        Schema::table('classroom_memberships', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'classroom_id'], 'memberships_user_status_classroom_index');
        });
    }

    public function down(): void
    {
        Schema::table('classroom_memberships', fn (Blueprint $table) => $table->dropIndex('memberships_user_status_classroom_index'));
        Schema::table('activities', fn (Blueprint $table) => $table->dropIndex('activities_teacher_published_index'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('teacher_tutorial_seen_at'));
    }
};
