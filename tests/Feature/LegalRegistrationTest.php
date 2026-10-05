<?php

namespace Tests\Feature;

use App\Domain\Classrooms\Models\Classroom;
use App\Domain\Classrooms\Models\Invitation;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\GuardianAuthorizationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class LegalRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_page_and_teacher_acceptance_are_required(): void
    {
        $this->get(route('legal.terms'))->assertOk()->assertSee('romulomachado712@gmail.com');
        $form = ['name' => 'Professora', 'email' => 'professora.formai@gmail.com', 'password' => 'segura123', 'password_confirmation' => 'segura123'];
        $this->post(route('register'), $form)->assertSessionHasErrors('terms');
        $this->assertDatabaseMissing('users', ['email' => $form['email']]);
        $this->post(route('register'), $form + ['terms' => '1'])->assertRedirect(route('dashboard'));
        $teacher = User::query()->where('email', $form['email'])->firstOrFail();
        $this->assertSame(config('legal.terms_version'), $teacher->terms_version);
        $this->assertNotNull($teacher->terms_accepted_at);
    }

    public function test_code_registration_requires_terms_and_guardian_approval_for_under_13(): void
    {
        Notification::fake();
        $classroom = $this->classroom();
        $this->post(route('class-code.lookup'), ['code' => $classroom->join_code])->assertRedirect();
        $form = $this->studentData();
        $this->post(route('class-code.store'), $form)->assertSessionHasErrors('terms');
        $this->assertDatabaseMissing('users', ['email' => $form['email']]);
        $this->post(route('class-code.store'), $form + ['terms' => '1'])->assertRedirect(route('guardian.pending'));
        $child = User::query()->where('email', $form['email'])->firstOrFail();
        $this->assertFalse($child->is_active);
        $this->assertSame(config('legal.terms_version'), $child->terms_version);
        $this->assertNotNull($child->terms_accepted_at);
        $this->assertGuest();
        $this->post('/login', ['email' => $form['email'], 'password' => $form['password']])->assertSessionHasErrors('email');
        $this->actingAs($classroom->teacher)->patch(route('teacher.classrooms.requests.approve', [$classroom, $child]))->assertSessionHasErrors('student');
        $this->assertDatabaseHas('classroom_memberships', ['classroom_id' => $classroom->id, 'user_id' => $child->id, 'status' => 'pending']);

        $token = $this->sentGuardianToken();
        $this->get(route('guardian.show', $token))->assertOk()->assertSee($child->name);
        $this->post(route('guardian.decide', $token), ['decision' => 'approve'])->assertSessionHasErrors(['guardian_name', 'relationship', 'terms']);
        $this->post(route('guardian.decide', $token), ['decision' => 'approve', 'guardian_name' => 'Responsável', 'relationship' => '1', 'terms' => '1'])->assertRedirect(route('guardian.result', 'approved'));
        $this->assertTrue($child->fresh()->is_active);
        $this->assertNotNull($child->fresh()->guardian_approved_at);
        $this->assertNotNull($child->fresh()->guardianAuthorization->accepted_at);
        $this->post(route('guardian.decide', $token), ['decision' => 'approve', 'guardian_name' => 'Outro', 'relationship' => '1', 'terms' => '1'])->assertRedirect(route('guardian.result', 'invalid'));
    }

    public function test_automatic_entry_for_under_13_still_requires_guardian_authorization(): void
    {
        Notification::fake();
        $classroom = $this->classroom();
        $classroom->update(['auto_approve_join' => true]);
        $this->post(route('class-code.lookup'), ['code' => $classroom->join_code]);
        $this->post(route('class-code.store'), $this->studentData() + ['terms' => '1'])
            ->assertRedirect(route('guardian.pending'));

        $child = User::query()->where('email', 'aluno.formai@gmail.com')->firstOrFail();
        $this->assertDatabaseHas('classroom_memberships', [
            'classroom_id' => $classroom->id,
            'user_id' => $child->id,
            'status' => 'approved',
            'approved_by' => null,
        ]);
        $this->assertFalse($child->is_active);
        $this->assertGuest();
        $this->post('/login', ['email' => $child->email, 'password' => 'segura123'])->assertSessionHasErrors('email');

        $this->post(route('guardian.decide', $this->sentGuardianToken()), [
            'decision' => 'approve',
            'guardian_name' => 'Responsável',
            'relationship' => '1',
            'terms' => '1',
        ])->assertRedirect(route('guardian.result', 'approved'));

        $this->assertTrue($child->fresh()->is_active);
        $this->assertNull($child->fresh()->email_verified_at);
        $this->assertTrue($classroom->students()->whereKey($child->id)->exists());
    }

    public function test_expired_link_is_reissued_and_decline_keeps_account_blocked(): void
    {
        Notification::fake();
        $classroom = $this->classroom();
        $this->post(route('class-code.lookup'), ['code' => $classroom->join_code]);
        $this->post(route('class-code.store'), $this->studentData() + ['terms' => '1'])->assertRedirect(route('guardian.pending'));
        $child = User::query()->where('email', 'aluno.formai@gmail.com')->firstOrFail();
        $oldToken = $this->sentGuardianToken();
        $child->guardianAuthorization->update(['expires_at' => now()->subMinute()]);
        $this->get(route('guardian.show', $oldToken))->assertSee('não está mais disponível');
        $this->post(route('guardian.decide', $oldToken), ['decision' => 'approve', 'guardian_name' => 'Responsável', 'relationship' => '1', 'terms' => '1'])->assertRedirect(route('guardian.result', 'invalid'));
        $this->post(route('guardian.resend'), ['email' => $child->email])->assertRedirect();
        $newToken = $this->sentGuardianToken();
        $this->assertNotSame($oldToken, $newToken);
        $this->post(route('guardian.decide', $newToken), ['decision' => 'decline'])->assertRedirect(route('guardian.result', 'declined'));
        $this->assertFalse($child->fresh()->is_active);
        $this->assertNotNull($child->fresh()->guardianAuthorization->declined_at);
        $this->post(route('guardian.resend'), ['email' => $child->email])->assertRedirect();
        Notification::assertSentOnDemandTimes(GuardianAuthorizationNotification::class, 2);
    }

    public function test_invite_registration_requires_terms_and_guardian_for_child(): void
    {
        Notification::fake();
        $classroom = $this->classroom();
        $token = str_repeat('a', 64);
        $classroom->invitations()->create(['email' => 'aluno.formai@gmail.com', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay(), 'invited_by' => $classroom->teacher_id]);
        $form = $this->studentData();
        unset($form['email']);
        $this->get(route('invitations.accept', $token))->assertOk();
        $this->post(route('invitations.store', $token), $form)->assertSessionHasErrors('terms');
        $this->assertDatabaseMissing('users', ['email' => 'aluno.formai@gmail.com']);
        $this->post(route('invitations.store', $token), $form + ['terms' => '1'])->assertRedirect(route('guardian.pending'));
        $child = User::query()->where('email', 'aluno.formai@gmail.com')->firstOrFail();
        $this->assertFalse($child->is_active);
        $this->assertSame(config('legal.terms_version'), $child->terms_version);
        $this->assertDatabaseHas('classroom_memberships', ['classroom_id' => $classroom->id, 'user_id' => $child->id, 'status' => 'approved']);
        $this->assertGuest();
        $this->assertNotNull($child->guardianAuthorization);
    }

    public function test_student_tutorial_can_be_seen_and_replayed(): void
    {
        $student = User::factory()->student()->create();
        $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertSee('id="student-tutorial"', false)->assertSee('&quot;autoOpen&quot;:true', false);
        $this->postJson(route('student.tutorial.seen'))->assertOk();
        $this->assertNotNull($student->fresh()->student_tutorial_seen_at);
        $this->actingAs($student->fresh())->get(route('dashboard'))->assertSee('&quot;autoOpen&quot;:false', false);
        $this->get(route('profile.edit'))->assertSee('data-tour-restart', false);
    }

    private function classroom(): Classroom
    {
        $teacher = User::factory()->teacher()->create();
        return Classroom::query()->create(['teacher_id' => $teacher->id, 'name' => 'Turma piloto', 'is_active' => true]);
    }

    private function studentData(): array
    {
        return ['name' => 'Aluno', 'email' => 'aluno.formai@gmail.com', 'password' => 'segura123', 'password_confirmation' => 'segura123', 'age_band' => 'under_13', 'guardian_email' => 'responsavel.formai@gmail.com'];
    }

    private function sentGuardianToken(): string
    {
        $notification = Notification::sent(new AnonymousNotifiable, GuardianAuthorizationNotification::class)->last();
        return (new \ReflectionProperty($notification, 'token'))->getValue($notification);
    }
}
