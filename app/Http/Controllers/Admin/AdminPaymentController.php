<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminPaymentController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $payments = Payment::query()
            ->with('user:id,name,email')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->search, fn ($q, $v) => $q->whereHas('user', fn ($q) => $q->where('email', 'like', "%{$v}%")))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $stats = [
            'total_revenue' => (float) Payment::where('status', 'completed')->where('type', '!=', 'admin_granted')->sum('amount'),
            'total_payments' => Payment::count(),
            'completed' => Payment::where('status', 'completed')->count(),
            'failed' => Payment::where('status', 'failed')->count(),
        ];

        return Inertia::render('admin/payments', [
            'payments' => $payments,
            'stats' => $stats,
            'filters' => $request->only('status', 'type', 'search'),
        ]);
    }
}
