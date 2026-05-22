<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminEmailLogController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $logs = EmailLog::query()
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('to_email', 'like', "%{$v}%")
                    ->orWhere('subject', 'like', "%{$v}%");
            }))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('admin/email-logs', [
            'logs' => $logs,
            'filters' => $request->only('status', 'search'),
        ]);
    }
}
