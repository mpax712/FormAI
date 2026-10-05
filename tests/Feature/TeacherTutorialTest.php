<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeacherTutorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_teacher_sees_tutorial_without_progress_table_or_header_button(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()
            ->assertSee('id="teacher-tutorial"', false)
            ->assertSee('&quot;autoOpen&quot;:true', false)
            ->assertDontSee('data-tour-help', false);
        $this->assertFalse(Schema::hasTable('tutorial_progress'));
    }

    public function test_seen_marker_is_one_write_and_does_not_track_steps(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)->postJson(route('teacher.tutorial.seen'))->assertOk()->assertJson(['seen' => true]);
        $seenAt = $teacher->fresh()->teacher_tutorial_seen_at;
        $this->assertNotNull($seenAt);
        $this->postJson(route('teacher.tutorial.seen'))->assertOk();
        $this->assertEquals($seenAt, $teacher->fresh()->teacher_tutorial_seen_at);
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->actingAs($teacher->fresh())->get(route('dashboard'))->assertOk()->assertSee('&quot;autoOpen&quot;:false', false);
        $this->assertFalse(collect(DB::getQueryLog())->contains(fn ($query) => str_contains($query['query'], 'tutorial_progress')));
        DB::disableQueryLog();
    }

    public function test_migration_marks_existing_teachers_but_not_students(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $migration = require database_path('migrations/2026_09_28_000002_replace_tutorial_progress_and_add_indexes.php');
        $migration->down();
        $migration->up();
        $this->assertNotNull($teacher->fresh()->teacher_tutorial_seen_at);
        $this->assertNull($student->fresh()->teacher_tutorial_seen_at);
        $this->actingAs($teacher->fresh())->get(route('dashboard'))->assertSee('&quot;autoOpen&quot;:false', false);
    }

    public function test_replay_is_available_to_teacher_and_student_in_profile(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)->get(route('profile.edit'))->assertOk()->assertSee('data-tour-restart', false);
        $this->actingAs(User::factory()->student()->create())->get(route('profile.edit'))->assertOk()->assertSee('data-tour-restart', false);
        $this->actingAs(User::factory()->admin()->create())->get(route('profile.edit'))->assertOk()->assertDontSee('data-tour-restart', false);
    }

    public function test_seen_marker_requires_authenticated_teacher_and_rejects_other_user_data(): void
    {
        $url = route('teacher.tutorial.seen');
        $this->postJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->student()->create())->postJson($url)->assertForbidden();
        $other = User::factory()->teacher()->create();
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)->postJson($url, ['user_id' => $other->id])->assertUnprocessable();
        $this->postJson($url, ['step' => 3])->assertUnprocessable();
        $this->postJson($url, ['url' => 'https://example.com'])->assertUnprocessable();
        $this->assertNull($other->fresh()->teacher_tutorial_seen_at);
        $this->assertNull($teacher->fresh()->teacher_tutorial_seen_at);
    }

    public function test_seen_marker_requires_real_csrf_token(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery {
            protected function runningUnitTests() { return false; }
        });
        $this->actingAs(User::factory()->teacher()->create());
        $this->postJson(route('teacher.tutorial.seen'))->assertStatus(419);
        $this->withSession(['_token' => 'tutorial-test-token'])->withHeader('X-CSRF-TOKEN', 'tutorial-test-token')
            ->postJson(route('teacher.tutorial.seen'))->assertOk();
    }
}
