<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FacebookPage;
use App\Models\Role;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use App\Services\TeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class Phase15TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        Role::firstOrCreate(['slug' => 'support-admin'], ['name' => 'Support Admin']);
    }

    // --- TeamService unit tests ---

    public function test_invite_creates_team_invitation(): void
    {
        Notification::fake();

        $service = app(TeamService::class);
        $invitation = $service->invite($this->owner, 'agent@example.com', 'agent');

        $this->assertDatabaseHas('team_invitations', [
            'owner_id' => $this->owner->id,
            'email' => 'agent@example.com',
            'role' => 'agent',
        ]);
        $this->assertNotEmpty($invitation->token);
        $this->assertNull($invitation->accepted_at);
    }

    public function test_invite_sends_notification(): void
    {
        Notification::fake();

        $service = app(TeamService::class);
        $service->invite($this->owner, 'agent@example.com', 'agent');

        // Notification sent on-demand (Notification::route) to the invited email
        Notification::assertSentOnDemand(TeamInvitationNotification::class);
    }

    public function test_invite_rejects_invalid_role(): void
    {
        $service = app(TeamService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->invite($this->owner, 'agent@example.com', 'super-admin');
    }

    public function test_invite_rejects_duplicate_existing_member(): void
    {
        Notification::fake();
        User::factory()->create(['owner_id' => $this->owner->id, 'email' => 'agent@example.com']);

        $service = app(TeamService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->invite($this->owner, 'agent@example.com', 'agent');
    }

    public function test_invite_revokes_previous_pending_invite_for_same_email(): void
    {
        Notification::fake();

        $old = TeamInvitation::factory()->create(['owner_id' => $this->owner->id, 'email' => 'test@example.com']);

        $service = app(TeamService::class);
        $new = $service->invite($this->owner, 'test@example.com', 'agent');

        $this->assertDatabaseMissing('team_invitations', ['id' => $old->id]);
        $this->assertDatabaseHas('team_invitations', ['id' => $new->id]);
    }

    public function test_accept_invitation_creates_member_with_role(): void
    {
        $invitation = TeamInvitation::factory()->create([
            'owner_id' => $this->owner->id,
            'email' => 'newagent@example.com',
            'role' => 'agent',
        ]);

        $service = app(TeamService::class);
        $member = $service->acceptInvitation($invitation, 'New Agent', 'password123');

        $this->assertDatabaseHas('users', [
            'email' => 'newagent@example.com',
            'owner_id' => $this->owner->id,
        ]);
        $this->assertTrue($member->hasRole('agent'));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_accept_expired_invitation_throws(): void
    {
        $invitation = TeamInvitation::factory()->expired()->create(['owner_id' => $this->owner->id]);

        $service = app(TeamService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->acceptInvitation($invitation, 'Name', 'password123');
    }

    public function test_accept_already_accepted_invitation_throws(): void
    {
        $invitation = TeamInvitation::factory()->accepted()->create(['owner_id' => $this->owner->id]);

        $service = app(TeamService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->acceptInvitation($invitation, 'Name', 'password123');
    }

    public function test_remove_member_deletes_user(): void
    {
        $member = User::factory()->create(['owner_id' => $this->owner->id]);

        $service = app(TeamService::class);
        $service->removeMember($this->owner, $member);

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
    }

    public function test_remove_member_from_wrong_owner_throws(): void
    {
        $other = User::factory()->create();
        $member = User::factory()->create(['owner_id' => $other->id]);

        $service = app(TeamService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->removeMember($this->owner, $member);
    }

    public function test_list_members_returns_only_owner_members(): void
    {
        User::factory()->count(3)->create(['owner_id' => $this->owner->id]);
        User::factory()->create(); // unrelated

        $service = app(TeamService::class);
        $members = $service->listMembers($this->owner);

        $this->assertCount(3, $members);
    }

    public function test_effective_tenant_id_returns_owner_id_for_team_member(): void
    {
        $member = User::factory()->create(['owner_id' => $this->owner->id]);
        $this->assertSame($this->owner->id, $member->effectiveTenantId());
    }

    public function test_effective_tenant_id_returns_own_id_for_owner(): void
    {
        $this->assertSame($this->owner->id, $this->owner->effectiveTenantId());
    }

    // --- API endpoint tests ---

    public function test_team_page_renders(): void
    {
        $response = $this->actingAs($this->owner)->get('/team');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('client/team'));
    }

    public function test_invite_endpoint_creates_invitation(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->owner)->post('/team/invite', [
            'email' => 'member@example.com',
            'role' => 'agent',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('team_invitations', ['email' => 'member@example.com']);
    }

    public function test_invite_endpoint_validates_role(): void
    {
        $response = $this->actingAs($this->owner)->post('/team/invite', [
            'email' => 'member@example.com',
            'role' => 'client-admin',
        ]);

        $response->assertSessionHasErrors('role');
    }

    public function test_revoke_invitation_endpoint(): void
    {
        $invitation = TeamInvitation::factory()->create(['owner_id' => $this->owner->id]);

        $response = $this->actingAs($this->owner)->delete("/team/invitations/{$invitation->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('team_invitations', ['id' => $invitation->id]);
    }

    public function test_remove_member_endpoint(): void
    {
        $member = User::factory()->create(['owner_id' => $this->owner->id]);

        $response = $this->actingAs($this->owner)->delete("/team/members/{$member->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $member->id]);
    }

    public function test_show_accept_page_renders(): void
    {
        $invitation = TeamInvitation::factory()->create(['owner_id' => $this->owner->id]);

        $response = $this->get("/team/accept/{$invitation->token}");
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('client/team-accept'));
    }

    public function test_show_accept_returns_410_for_expired(): void
    {
        $invitation = TeamInvitation::factory()->expired()->create(['owner_id' => $this->owner->id]);

        $response = $this->get("/team/accept/{$invitation->token}");
        $response->assertStatus(410);
    }

    public function test_accept_endpoint_creates_member_and_redirects(): void
    {
        $invitation = TeamInvitation::factory()->create([
            'owner_id' => $this->owner->id,
            'email' => 'newmember@example.com',
            'role' => 'agent',
        ]);

        $response = $this->post("/team/accept/{$invitation->token}", [
            'name' => 'New Member',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', [
            'email' => 'newmember@example.com',
            'owner_id' => $this->owner->id,
        ]);
    }

    // --- Team member inbox scope ---

    public function test_team_member_can_see_owner_conversations(): void
    {
        $member = User::factory()->create(['owner_id' => $this->owner->id]);

        $page = FacebookPage::factory()->create(['user_id' => $this->owner->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->owner->id, 'facebook_page_id' => $page->id]);
        Conversation::factory()->create([
            'tenant_id' => $this->owner->id,
            'facebook_page_id' => $page->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->actingAs($member)->getJson('/inbox/conversations');
        $response->assertOk();
        $response->assertJsonPath('total', 1);
    }

    public function test_team_member_cannot_see_other_owner_conversations(): void
    {
        $member = User::factory()->create(['owner_id' => $this->owner->id]);
        $other = User::factory()->create();

        $page = FacebookPage::factory()->create(['user_id' => $other->id]);
        $customer = Customer::factory()->create(['tenant_id' => $other->id, 'facebook_page_id' => $page->id]);
        Conversation::factory()->create([
            'tenant_id' => $other->id,
            'facebook_page_id' => $page->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->actingAs($member)->getJson('/inbox/conversations');
        $response->assertOk();
        $response->assertJsonPath('total', 0);
    }
}
