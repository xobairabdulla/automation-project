<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->action, fn ($q, $v) => $q->where('action', 'like', "%{$v}%"))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->entity_type, fn ($q, $v) => $q->where('entity_type', $v))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('admin/audit-logs', [
            'logs' => $logs,
            'filters' => $request->only('action', 'user_id', 'entity_type'),
        ]);
    }
}
