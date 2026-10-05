<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->text('grading_instructions')->nullable()->after('description');
        });
        Schema::table('rubric_criteria', function (Blueprint $table): void {
            $table->decimal('weight', 8, 4)->change();
        });

        DB::table('questions')->select('id', 'max_score')->orderBy('id')->chunkById(100, function ($questions): void {
            foreach ($questions as $question) {
                $criteria = DB::table('rubric_criteria')->where('question_id', $question->id)->get();
                if ($criteria->isEmpty() || abs($criteria->sum(fn ($criterion) => (float) $criterion->weight) - 1.0) > 0.001) {
                    continue;
                }

                foreach ($criteria as $criterion) {
                    DB::table('rubric_criteria')->where('id', $criterion->id)->update([
                        'weight' => round((float) $criterion->weight * (float) $question->max_score, 4),
                    ]);
                }
            }
        });

        $this->transformActivityRubrics(fn (float $weight, float $maximumScore): float => $weight * $maximumScore, 1.0);
    }

    public function down(): void
    {
        DB::table('questions')->select('id', 'max_score')->orderBy('id')->chunkById(100, function ($questions): void {
            foreach ($questions as $question) {
                $criteria = DB::table('rubric_criteria')->where('question_id', $question->id)->get();
                if ($criteria->isEmpty() || abs($criteria->sum(fn ($criterion) => (float) $criterion->weight) - (float) $question->max_score) > 0.001) {
                    continue;
                }

                foreach ($criteria as $criterion) {
                    DB::table('rubric_criteria')->where('id', $criterion->id)->update([
                        'weight' => round((float) $criterion->weight / (float) $question->max_score, 4),
                    ]);
                }
            }
        });

        $this->transformActivityRubrics(fn (float $weight, float $maximumScore): float => $weight / $maximumScore, null);

        Schema::table('rubric_criteria', function (Blueprint $table): void {
            $table->decimal('weight', 7, 4)->change();
        });

        Schema::table('activities', fn (Blueprint $table) => $table->dropColumn('grading_instructions'));
    }

    private function transformActivityRubrics(callable $transform, ?float $expectedTotal): void
    {
        DB::table('activity_questions')->select('id', 'max_score', 'rubric_snapshot')->orderBy('id')->chunkById(100, function ($questions) use ($transform, $expectedTotal): void {
            foreach ($questions as $question) {
                $rubric = json_decode((string) $question->rubric_snapshot, true);
                if (! is_array($rubric) || $rubric === []) {
                    continue;
                }

                $target = $expectedTotal ?? (float) $question->max_score;
                $sum = collect($rubric)->sum(fn ($criterion) => (float) ($criterion['weight'] ?? 0));
                if (abs($sum - $target) > 0.001) {
                    continue;
                }

                foreach ($rubric as &$criterion) {
                    $criterion['weight'] = round($transform((float) ($criterion['weight'] ?? 0), (float) $question->max_score), 4);
                }
                unset($criterion);

                DB::table('activity_questions')->where('id', $question->id)->update([
                    'rubric_snapshot' => json_encode($rubric, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            }
        });
    }
};
