<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['teacher_id', 'is_active']);
        });
        Schema::create('classroom_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['classroom_id', 'user_id']);
        });
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['classroom_id', 'email']);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 30);
            $table->text('body');
            $table->text('expected_answer')->nullable();
            $table->text('teacher_instruction')->nullable();
            $table->decimal('max_score', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['owner_id', 'type', 'is_active']);
        });
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('option_key', 20);
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('position');
            $table->unique(['question_id', 'option_key']);
        });
        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);
            $table->text('description');
            $table->decimal('weight', 7, 4);
            $table->unsignedSmallInteger('position');
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamp('deadline_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->decimal('total_score', 8, 2)->default(0);
            $table->timestamps();
            $table->index(['teacher_id', 'status', 'deadline_at']);
            $table->index(['classroom_id', 'status']);
        });
        Schema::create('activity_questions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->string('type', 30);
            $table->text('body');
            $table->text('expected_answer')->nullable();
            $table->text('teacher_instruction')->nullable();
            $table->decimal('max_score', 8, 2);
            $table->json('options_snapshot')->nullable();
            $table->json('rubric_snapshot')->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['activity_id', 'position']);
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('reopened_until')->nullable();
            $table->decimal('objective_score', 8, 2)->default(0);
            $table->decimal('final_score', 8, 2)->nullable();
            $table->timestamps();
            $table->unique(['activity_id', 'student_id']);
            $table->index(['activity_id', 'status']);
            $table->index(['student_id', 'status']);
        });
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_question_id')->constrained()->cascadeOnDelete();
            $table->longText('response_text')->nullable();
            $table->string('selected_option_key', 20)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['submission_id', 'activity_question_id']);
        });

        Schema::create('grading_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('answer_id')->constrained()->cascadeOnDelete();
            $table->char('idempotency_key', 64)->unique();
            $table->string('status', 30)->default('pending')->index();
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->unsignedInteger('prompt_version');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost', 12, 6)->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['answer_id', 'status']);
        });
        Schema::create('grading_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('score', 8, 2);
            $table->json('criterion_scores');
            $table->json('evidence');
            $table->text('feedback');
            $table->decimal('confidence', 5, 4);
            $table->json('warnings')->nullable();
            $table->timestamp('created_at');
        });
        Schema::create('grading_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('grading_suggestion_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->decimal('score', 8, 2);
            $table->text('feedback')->nullable();
            $table->timestamp('confirmed_at');
        });

        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80);
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['key', 'version']);
            $table->index(['key', 'is_active']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 100)->index();
            $table->string('auditable_type', 150)->nullable();
            $table->string('auditable_id', 100)->nullable();
            $table->text('route')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->char('correlation_id', 36)->nullable()->index();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id']);
        });
        Schema::create('system_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestamp('last_seen_at')->index();
            $table->json('metadata')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['system_heartbeats', 'audit_logs', 'prompt_templates', 'grading_decisions', 'grading_suggestions', 'grading_runs', 'answers', 'submissions', 'activity_questions', 'activities', 'rubric_criteria', 'question_options', 'questions', 'invitations', 'classroom_memberships', 'classrooms'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
