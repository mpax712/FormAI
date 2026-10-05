<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('feedback_detail', 20)->default('medium');
            $table->string('intelligence_profile', 20)->default('balanced');
        });
        Schema::table('grading_runs', function (Blueprint $table) {
            $table->text('request_snapshot')->nullable();
            $table->json('execution_plan')->nullable();
            $table->unsignedSmallInteger('destination_index')->default(0);
            $table->unsignedSmallInteger('technical_failures')->default(0);
            $table->string('wait_reason', 40)->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
        });
        Schema::table('grading_suggestions', fn (Blueprint $table) => $table->text('teacher_feedback')->nullable());
        Schema::create('grading_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->string('status', 30)->default('processing');
            $table->string('reason', 60)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unique(['grading_run_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_attempts');
        Schema::table('grading_suggestions', fn (Blueprint $table) => $table->dropColumn('teacher_feedback'));
        Schema::table('grading_runs', fn (Blueprint $table) => $table->dropColumn(['request_snapshot', 'execution_plan', 'destination_index', 'technical_failures', 'wait_reason', 'next_attempt_at', 'expires_at']));
        Schema::table('activities', fn (Blueprint $table) => $table->dropColumn(['feedback_detail', 'intelligence_profile']));
    }
};
