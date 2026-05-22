<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminSystemSettingController extends Controller
{
    public function index(): \Inertia\Response
    {
        $settings = SystemSetting::orderBy('key')->get();

        return Inertia::render('admin/system-settings', [
            'settings' => $settings,
        ]);
    }

    public function upsert(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'value' => ['required', 'string'],
            'type' => ['required', 'in:string,boolean,integer,json'],
        ]);

        SystemSetting::updateOrCreate(
            ['key' => $validated['key']],
            [
                'value_encrypted_or_json' => $validated['value'],
                'type' => $validated['type'],
                'is_sensitive' => false,
            ]
        );

        return back()->with('success', 'Setting saved.');
    }

    public function destroy(SystemSetting $systemSetting): RedirectResponse
    {
        $systemSetting->delete();

        return back()->with('success', 'Setting deleted.');
    }
}
