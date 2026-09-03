<?php

namespace Tests\Feature;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletion_request_disables_account_and_records_audit(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)->delete(route('account.destroy'), ['password' => 'password'])->assertRedirect(route('home'));
        $this->assertFalse($teacher->fresh()->is_active);
        $this->assertNotNull($teacher->fresh()->deleted_requested_at);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $teacher->id, 'event' => 'account.deletion_requested']);
    }
}
