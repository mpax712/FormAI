<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email_verified_at');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->char('join_code', 8)->nullable()->unique()->after('public_id');
        });

        Schema::table('classroom_memberships', function (Blueprint $table) {
            $table->string('status', 20)->default('approved')->after('user_id')->index();
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        DB::table('classrooms')->orderBy('id')->each(function (object $classroom) use ($alphabet): void {
            do {
                $code = collect(range(1, 8))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
            } while (DB::table('classrooms')->where('join_code', $code)->exists());

            DB::table('classrooms')->where('id', $classroom->id)->update(['join_code' => $code]);
        });

        DB::table('classroom_memberships')->where('status', 'approved')->update(['approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('classroom_memberships', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'approved_at', 'approved_by']);
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropUnique(['join_code']);
            $table->dropColumn('join_code');
        });

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('avatar_path'));
    }
};
