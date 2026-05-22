<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiReplyLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminAiLogController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $logs = AiReplyLog::query()
            ->with('facebookPage:id,page_name')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->model, fn ($q, $v) => $q->where('model', $v))
            ->when($request->tenant_id, fn ($q, $v) => $q->where('tenant_id', $v))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $stats = [
            'total' => AiReplyLog::count(),
            'success' => AiReplyLog::where('status', 'success')->count(),
            'failed' => AiReplyLog::where('status', 'failed')->count(),
            'total_tokens' => (int) AiReplyLog::sum('tokens_used'),
        ];

        return Inertia::render('admin/ai-logs', [
            'logs' => $logs,
            'stats' => $stats,
            'filters' => $request->only('status', 'model', 'tenant_id'),
        ]);
    }
}
