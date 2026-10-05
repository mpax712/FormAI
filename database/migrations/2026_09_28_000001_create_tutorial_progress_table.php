<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutorial_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tutorial', 40);
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('not_started');
            $table->unsignedSmallInteger('step')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'tutorial', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutorial_progress');
    }
};
