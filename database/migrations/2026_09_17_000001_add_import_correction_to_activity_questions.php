<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_questions', fn (Blueprint $table) => $table->boolean('import_correction')->default(true));
    }

    public function down(): void
    {
        Schema::table('activity_questions', fn (Blueprint $table) => $table->dropColumn('import_correction'));
    }
};
