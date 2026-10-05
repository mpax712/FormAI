<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('terms_version', 20)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('age_band', 20)->nullable();
            $table->timestamp('guardian_approved_at')->nullable();
            $table->timestamp('student_tutorial_seen_at')->nullable();
        });

        Schema::create('guardian_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('guardian_email');
            $table->string('guardian_name', 120)->nullable();
            $table->char('token_hash', 64)->unique();
            $table->string('terms_version', 20);
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_authorizations');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'terms_version', 'terms_accepted_at', 'age_band', 'guardian_approved_at', 'student_tutorial_seen_at',
        ]));
    }
};
