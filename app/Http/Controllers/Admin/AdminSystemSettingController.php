<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProviderSetting;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class AdminSystemSettingController extends Controller
{
    public function index(): Response
    {
        $ai = AiProviderSetting::where('status', 'active')->first()
            ?? AiProviderSetting::orderByDesc('id')->first();

        $get = fn (string $key, string $default = '') => SystemSetting::getValue($key) ?? $default;
        $isSet = fn (string $key) => SystemSetting::where('key', $key)
            ->whereNotNull('value_encrypted_or_json')
            ->exists();

        return Inertia::render('admin/system-settings', [
            'ai' => [
                'provider_name' => $ai?->provider_name ?? 'gemini',
                'model' => $ai?->model ?? '',
                'key_is_set' => $ai !== null,
            ],
            'meta' => [
                'app_id' => $get('meta_app_id'),
                'app_secret_set' => $isSet('meta_app_secret'),
                'webhook_verify_token_set' => $isSet('meta_webhook_verify_token'),
                'redirect_uri' => $get('meta_redirect_uri'),
                'graph_api_version' => $get('meta_graph_api_version', 'v20.0'),
            ],
            'stripe' => [
                'key_set' => $isSet('stripe_key'),
                'secret_set' => $isSet('stripe_secret'),
                'webhook_secret_set' => $isSet('stripe_webhook_secret'),
            ],
            'sslcz' => [
                'store_id' => $get('sslcz_store_id'),
                'store_password_set' => $isSet('sslcz_store_password'),
                'is_sandbox' => $get('sslcz_is_sandbox', 'true') === 'true',
            ],
            'mail' => [
                'host' => $get('mail_host'),
                'port' => $get('mail_port', '587'),
                'username' => $get('mail_username'),
                'password_set' => $isSet('mail_password'),
                'encryption' => $get('mail_encryption', 'tls'),
                'from_address' => $get('mail_from_address'),
                'from_name' => $get('mail_from_name'),
            ],
        ]);
    }

    public function saveAi(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'provider_name' => ['required', 'in:gemini,anthropic,openai'],
            'model' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
        ]);

        // Deactivate all, then upsert the chosen provider as active
        AiProviderSetting::query()->update(['status' => 'inactive']);

        $setting = AiProviderSetting::where('provider_name', $validated['provider_name'])->first()
            ?? new AiProviderSetting(['provider_name' => $validated['provider_name']]);

        $setting->provider_name = $validated['provider_name'];
        $setting->model = $validated['model'];
        $setting->status = 'active';

        if (! empty($validated['api_key'])) {
            $setting->api_key_encrypted = $validated['api_key'];
        }

        $setting->save();

        $this->bustCache();

        return back()->with('success', 'AI provider saved.');
    }

    public function saveMeta(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_id' => ['nullable', 'string', 'max:50'],
            'app_secret' => ['nullable', 'string', 'max:500'],
            'webhook_verify_token' => ['nullable', 'string', 'max:200'],
            'redirect_uri' => ['nullable', 'url', 'max:500'],
            'graph_api_version' => ['nullable', 'string', 'max:20'],
        ]);

        SystemSetting::setValue('meta_app_id', $validated['app_id']);
        SystemSetting::setValue('meta_app_secret', $validated['app_secret'], 'string', true);
        SystemSetting::setValue('meta_webhook_verify_token', $validated['webhook_verify_token'], 'string', true);
        SystemSetting::setValue('meta_redirect_uri', $validated['redirect_uri']);
        SystemSetting::setValue('meta_graph_api_version', $validated['graph_api_version'] ?: 'v20.0');

        $this->bustCache();

        return back()->with('success', 'Facebook / Meta settings saved.');
    }

    public function saveStripe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stripe_key' => ['nullable', 'string', 'max:500'],
            'stripe_secret' => ['nullable', 'string', 'max:500'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:500'],
        ]);

        SystemSetting::setValue('stripe_key', $validated['stripe_key'], 'string', true);
        SystemSetting::setValue('stripe_secret', $validated['stripe_secret'], 'string', true);
        SystemSetting::setValue('stripe_webhook_secret', $validated['stripe_webhook_secret'], 'string', true);

        $this->bustCache();

        return back()->with('success', 'Stripe settings saved.');
    }

    public function saveSslcommerz(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_id' => ['nullable', 'string', 'max:100'],
            'store_password' => ['nullable', 'string', 'max:500'],
            'is_sandbox' => ['boolean'],
        ]);

        SystemSetting::setValue('sslcz_store_id', $validated['store_id']);
        SystemSetting::setValue('sslcz_store_password', $validated['store_password'], 'string', true);
        SystemSetting::setValue('sslcz_is_sandbox', $validated['is_sandbox'] ? 'true' : 'false');

        $this->bustCache();

        return back()->with('success', 'SSLCommerz settings saved.');
    }

    public function saveMail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'encryption' => ['nullable', 'in:tls,ssl,starttls,'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:100'],
        ]);

        SystemSetting::setValue('mail_host', $validated['host']);
        SystemSetting::setValue('mail_port', isset($validated['port']) ? (string) $validated['port'] : null);
        SystemSetting::setValue('mail_username', $validated['username']);
        SystemSetting::setValue('mail_password', $validated['password'], 'string', true);
        SystemSetting::setValue('mail_encryption', $validated['encryption']);
        SystemSetting::setValue('mail_from_address', $validated['from_address']);
        SystemSetting::setValue('mail_from_name', $validated['from_name']);

        $this->bustCache();

        return back()->with('success', 'Mail settings saved.');
    }

    // Legacy generic upsert — kept for backward compatibility
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

        $this->bustCache();

        return back()->with('success', 'Setting saved.');
    }

    public function destroy(SystemSetting $systemSetting): RedirectResponse
    {
        $systemSetting->delete();
        $this->bustCache();

        return back()->with('success', 'Setting deleted.');
    }

    private function bustCache(): void
    {
        Cache::forget('system_settings_config');
    }
}
