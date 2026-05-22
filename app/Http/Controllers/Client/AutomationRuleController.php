<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use App\Models\AutomationRuleAction;
use App\Models\AutomationRuleCondition;
use App\Models\FacebookPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationRuleController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $pageId = $request->query('page_id');

        $pages = FacebookPage::where('user_id', $user->id)
            ->where('is_connected', true)
            ->get(['id', 'page_name']);

        $rules = AutomationRule::with(['conditions', 'actions.replyTemplate'])
            ->where('tenant_id', $user->id)
            ->when($pageId, fn ($q) => $q->where('facebook_page_id', $pageId))
            ->orderBy('priority')
            ->get();

        return Inertia::render('client/automation-rules', [
            'rules' => $rules,
            'pages' => $pages,
            'selectedPageId' => $pageId ? (int) $pageId : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'facebook_page_id' => ['required', 'exists:facebook_pages,id'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:message,comment,both'],
            'priority' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*.condition_type' => ['required', 'in:keyword_contains,exact_match,starts_with'],
            'conditions.*.keywords_json' => ['required', 'array', 'min:1'],
            'conditions.*.case_sensitive' => ['boolean'],
            'action.action_type' => ['required', 'in:send_text,send_template,human_handover,mark_as_lead,do_nothing'],
            'action.reply_text' => ['nullable', 'string'],
            'action.reply_template_id' => ['nullable', 'exists:reply_templates,id'],
        ]);

        $page = FacebookPage::where('id', $validated['facebook_page_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $rule = AutomationRule::create([
            'tenant_id' => $request->user()->id,
            'facebook_page_id' => $page->id,
            'name' => $validated['name'],
            'channel' => $validated['channel'],
            'priority' => $validated['priority'],
            'status' => $validated['status'],
        ]);

        foreach ($validated['conditions'] as $cond) {
            AutomationRuleCondition::create([
                'automation_rule_id' => $rule->id,
                'condition_type' => $cond['condition_type'],
                'keywords_json' => $cond['keywords_json'],
                'case_sensitive' => $cond['case_sensitive'] ?? false,
            ]);
        }

        AutomationRuleAction::create([
            'automation_rule_id' => $rule->id,
            'action_type' => $validated['action']['action_type'],
            'reply_text' => $validated['action']['reply_text'] ?? null,
            'reply_template_id' => $validated['action']['reply_template_id'] ?? null,
        ]);

        return back()->with('success', 'Rule created.');
    }

    public function update(Request $request, AutomationRule $automationRule): RedirectResponse
    {
        abort_if($automationRule->tenant_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', 'in:message,comment,both'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $automationRule->update($validated);

        return back()->with('success', 'Rule updated.');
    }

    public function destroy(Request $request, AutomationRule $automationRule): RedirectResponse
    {
        abort_if($automationRule->tenant_id !== $request->user()->id, 403);

        $automationRule->delete();

        return back()->with('success', 'Rule deleted.');
    }
}
