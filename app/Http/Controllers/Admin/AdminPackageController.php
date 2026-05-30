<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LimitExtensionPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/packages/index', [
            'packages' => LimitExtensionPackage::orderBy('price')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'message_extra' => ['required', 'integer', 'min:0'],
            'comment_extra' => ['required', 'integer', 'min:0'],
            'ai_extra' => ['required', 'integer', 'min:0'],
            'image_send_extra' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['boolean'],
        ]);

        $package = LimitExtensionPackage::create($validated);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'package.created',
            'entity_type' => 'LimitExtensionPackage',
            'entity_id' => $package->id,
            'new_values_json' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Package created.');
    }

    public function update(Request $request, LimitExtensionPackage $package): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'message_extra' => ['sometimes', 'integer', 'min:0'],
            'comment_extra' => ['sometimes', 'integer', 'min:0'],
            'ai_extra' => ['sometimes', 'integer', 'min:0'],
            'image_send_extra' => ['sometimes', 'integer', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'is_active' => ['boolean'],
        ]);

        $old = $package->toArray();
        $package->update($validated);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'package.updated',
            'entity_type' => 'LimitExtensionPackage',
            'entity_id' => $package->id,
            'old_values_json' => $old,
            'new_values_json' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Package updated.');
    }

    public function destroy(Request $request, LimitExtensionPackage $package): RedirectResponse
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'package.deleted',
            'entity_type' => 'LimitExtensionPackage',
            'entity_id' => $package->id,
            'old_values_json' => $package->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $package->delete();

        return back()->with('success', 'Package deleted.');
    }
}
