<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationTag;
use App\Models\Customer;
use App\Models\FacebookPage;
use App\Models\InternalNote;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageLimit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase12InboxTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FacebookPage $page;

    private Customer $customer;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->page = FacebookPage::factory()->create([
            'user_id' => $this->user->id,
            'is_connected' => true,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'external_customer_id' => 'FB_SENDER_999',
        ]);
        $this->conversation = Conversation::factory()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'customer_id' => $this->customer->id,
            'status' => 'open',
            'is_read' => false,
        ]);

        $plan = Plan::factory()->create([
            'message_reply_limit' => 100,
            'comment_reply_limit' => 100,
            'ai_reply_limit' => 50,
        ]);
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        UsageLimit::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'message_reply_limit' => 100,
            'message_reply_used' => 0,
            'comment_reply_limit' => 100,
            'comment_reply_used' => 0,
            'ai_reply_limit' => 50,
            'ai_reply_used' => 0,
            'reset_at' => now()->addMonth(),
        ]);
    }

    public function test_inbox_page_renders_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->user)->get('/inbox');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('client/inbox'));
    }

    public function test_conversations_list_returns_paginated_data(): void
    {
        $response = $this->actingAs($this->user)->getJson('/inbox/conversations');
        $response->assertOk();
        $response->assertJsonStructure(['data', 'total']);
    }

    public function test_conversations_list_scoped_to_tenant(): void
    {
        $otherUser = User::factory()->create();
        $otherPage = FacebookPage::factory()->create(['user_id' => $otherUser->id]);
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $otherUser->id,
            'facebook_page_id' => $otherPage->id,
        ]);
        Conversation::factory()->create([
            'tenant_id' => $otherUser->id,
            'facebook_page_id' => $otherPage->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/inbox/conversations');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($this->conversation->id, $ids->toArray());
        $this->assertCount(1, $ids);
    }

    public function test_filter_unread_returns_only_unread(): void
    {
        Conversation::factory()->create([
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'customer_id' => Customer::factory()->create([
                'tenant_id' => $this->user->id,
                'facebook_page_id' => $this->page->id,
            ])->id,
            'is_read' => true,
        ]);

        $response = $this->actingAs($this->user)->getJson('/inbox/conversations?filter=unread');
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($this->conversation->id, $ids->toArray());
        foreach ($ids as $id) {
            $this->assertFalse(Conversation::find($id)->is_read);
        }
    }

    public function test_conversation_show_returns_messages_notes_tags(): void
    {
        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'tenant_id' => $this->user->id,
            'facebook_page_id' => $this->page->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/inbox/conversations/{$this->conversation->id}");
        $response->assertOk();
        $response->assertJsonStructure(['id', 'customer', 'messages', 'notes', 'tags']);
    }

    public function test_cannot_view_another_tenants_conversation(): void
    {
        $other = User::factory()->create();
        $response = $this->actingAs($other)->getJson("/inbox/conversations/{$this->conversation->id}");
        $response->assertForbidden();
    }

    public function test_mark_read_sets_is_read_true(): void
    {
        $this->assertFalse($this->conversation->is_read);
        $this->actingAs($this->user)->postJson("/inbox/conversations/{$this->conversation->id}/read");
        $this->assertTrue($this->conversation->fresh()->is_read);
    }

    public function test_close_conversation_updates_status(): void
    {
        $response = $this->actingAs($this->user)->postJson("/inbox/conversations/{$this->conversation->id}/close");
        $response->assertOk();
        $this->assertEquals('closed', $this->conversation->fresh()->status);
    }

    public function test_manual_reply_sends_message_and_logs_usage(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['message_id' => 'mid_manual_1'], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson("/inbox/conversations/{$this->conversation->id}/reply", [
            'message' => 'Hello from agent!',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'message_text' => 'Hello from agent!',
            'direction' => 'outgoing',
            'sender_type' => 'agent',
        ]);

        $this->assertDatabaseHas('usage_logs', [
            'user_id' => $this->user->id,
            'type' => 'manual_reply',
        ]);
    }

    public function test_add_internal_note(): void
    {
        $response = $this->actingAs($this->user)->postJson("/inbox/conversations/{$this->conversation->id}/notes", [
            'note' => 'Follow up tomorrow',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('internal_notes', [
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'note' => 'Follow up tomorrow',
        ]);
    }

    public function test_assign_conversation(): void
    {
        $agent = User::factory()->create();

        $response = $this->actingAs($this->user)->postJson("/inbox/conversations/{$this->conversation->id}/assign", [
            'assigned_to' => $agent->id,
        ]);

        $response->assertOk();
        $this->assertEquals($agent->id, $this->conversation->fresh()->assigned_to);
        $this->assertDatabaseHas('conversation_assignments', [
            'conversation_id' => $this->conversation->id,
            'assigned_to' => $agent->id,
            'assigned_by' => $this->user->id,
        ]);
    }

    public function test_tag_conversation(): void
    {
        $tag = ConversationTag::create([
            'tenant_id' => $this->user->id,
            'name' => 'VIP',
            'color' => '#ff0000',
        ]);

        $response = $this->actingAs($this->user)->postJson("/inbox/conversations/{$this->conversation->id}/tags", [
            'tag_ids' => [$tag->id],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('conversation_tag_pivot', [
            'conversation_id' => $this->conversation->id,
            'conversation_tag_id' => $tag->id,
        ]);
    }

    public function test_create_tag(): void
    {
        $response = $this->actingAs($this->user)->postJson('/inbox/tags', [
            'name' => 'Urgent',
            'color' => '#e11d48',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('conversation_tags', [
            'tenant_id' => $this->user->id,
            'name' => 'Urgent',
        ]);
    }

    public function test_incoming_message_marks_conversation_unread(): void
    {
        $this->conversation->update(['is_read' => true]);

        $conversation = Conversation::find($this->conversation->id);
        $conversationService = app(\App\Services\ConversationService::class);
        $conversationService->saveIncomingMessage($conversation, 'Hey there', 'mid_test');

        $this->assertFalse($this->conversation->fresh()->is_read);
    }
}
