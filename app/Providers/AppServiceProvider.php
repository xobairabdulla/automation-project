<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->overrideConfigFromDatabase();
    }

    private function overrideConfigFromDatabase(): void
    {
        try {
            $s = Cache::remember('system_settings_config', 300, fn () => SystemSetting::allDecrypted());

            // Facebook / Meta
            if (! empty($s['meta_app_id'])) {
                config(['services.meta.app_id' => $s['meta_app_id']]);
            }
            if (! empty($s['meta_app_secret'])) {
                config(['services.meta.app_secret' => $s['meta_app_secret']]);
            }
            if (! empty($s['meta_webhook_verify_token'])) {
                config(['services.meta.webhook_verify_token' => $s['meta_webhook_verify_token']]);
            }
            if (! empty($s['meta_redirect_uri'])) {
                config(['services.meta.redirect_uri' => $s['meta_redirect_uri']]);
            }
            if (! empty($s['meta_graph_api_version'])) {
                config(['services.meta.graph_api_version' => $s['meta_graph_api_version']]);
            }

            // Stripe
            if (! empty($s['stripe_key'])) {
                config(['services.payments.stripe_key' => $s['stripe_key']]);
            }
            if (! empty($s['stripe_secret'])) {
                config(['services.payments.stripe_secret' => $s['stripe_secret']]);
            }
            if (! empty($s['stripe_webhook_secret'])) {
                config(['services.payments.stripe_webhook_secret' => $s['stripe_webhook_secret']]);
            }

            // SSLCommerz
            if (! empty($s['sslcz_store_id'])) {
                config(['services.sslcommerz.store_id' => $s['sslcz_store_id']]);
            }
            if (! empty($s['sslcz_store_password'])) {
                config(['services.sslcommerz.store_password' => $s['sslcz_store_password']]);
            }
            if (isset($s['sslcz_is_sandbox'])) {
                config(['services.sslcommerz.sandbox' => $s['sslcz_is_sandbox'] === 'true']);
            }

            // Mail / SMTP
            if (! empty($s['mail_host'])) {
                config(['mail.mailers.smtp.host' => $s['mail_host']]);
            }
            if (! empty($s['mail_port'])) {
                config(['mail.mailers.smtp.port' => (int) $s['mail_port']]);
            }
            if (! empty($s['mail_username'])) {
                config(['mail.mailers.smtp.username' => $s['mail_username']]);
            }
            if (! empty($s['mail_password'])) {
                config(['mail.mailers.smtp.password' => $s['mail_password']]);
            }
            if (! empty($s['mail_encryption'])) {
                config(['mail.mailers.smtp.encryption' => $s['mail_encryption']]);
            }
            if (! empty($s['mail_from_address'])) {
                config(['mail.from.address' => $s['mail_from_address']]);
            }
            if (! empty($s['mail_from_name'])) {
                config(['mail.from.name' => $s['mail_from_name']]);
            }
        } catch (\Throwable) {
            // Silently skip if DB is unavailable (e.g. first deploy, artisan commands before migrate)
        }
    }
}
