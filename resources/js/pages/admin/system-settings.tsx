import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle, Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';

interface AiProps {
    provider_name: string;
    model: string;
    key_is_set: boolean;
}

interface MetaProps {
    app_id: string;
    app_secret_set: boolean;
    webhook_verify_token_set: boolean;
    redirect_uri: string;
    graph_api_version: string;
}

interface StripeProps {
    key_set: boolean;
    secret_set: boolean;
    webhook_secret_set: boolean;
}

interface SslczProps {
    store_id: string;
    store_password_set: boolean;
    is_sandbox: boolean;
}

interface MailProps {
    host: string;
    port: string;
    username: string;
    password_set: boolean;
    encryption: string;
    from_address: string;
    from_name: string;
}

interface Props {
    ai: AiProps;
    meta: MetaProps;
    stripe: StripeProps;
    sslcz: SslczProps;
    mail: MailProps;
}

interface SharedProps {
    flash?: { success?: string; error?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'API Keys & Settings', href: '/admin/system-settings' },
];

function SecretInput({
    label,
    name,
    isSet,
    value,
    onChange,
    placeholder,
}: {
    label: string;
    name: string;
    isSet: boolean;
    value: string;
    onChange: (v: string) => void;
    placeholder?: string;
}) {
    const [show, setShow] = useState(false);

    return (
        <div className="flex flex-col gap-1">
            <div className="flex items-center justify-between">
                <Label className="text-xs">{label}</Label>
                {isSet && (
                    <span className="flex items-center gap-1 text-[11px] text-green-600">
                        <CheckCircle className="h-3 w-3" />
                        Set
                    </span>
                )}
            </div>
            <div className="relative">
                <Input
                    type={show ? 'text' : 'password'}
                    name={name}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={isSet ? 'Leave empty to keep current value' : (placeholder ?? 'Paste key here')}
                    className="pr-9 font-mono text-xs"
                    autoComplete="new-password"
                />
                <button
                    type="button"
                    onClick={() => setShow((s) => !s)}
                    className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    tabIndex={-1}
                >
                    {show ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
                </button>
            </div>
        </div>
    );
}

function SectionCard({ title, description, children }: { title: string; description?: string; children: React.ReactNode }) {
    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-base">{title}</CardTitle>
                {description && <p className="text-xs text-muted-foreground">{description}</p>}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

function Flash() {
    const { flash } = usePage<SharedProps>().props;
    if (!flash?.success && !flash?.error) return null;
    return (
        <div className={`rounded-md px-4 py-2 text-sm ${flash.success ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200'}`}>
            {flash.success ?? flash.error}
        </div>
    );
}

export default function SystemSettings({ ai, meta, stripe, sslcz, mail }: Props) {
    // ── AI Provider ────────────────────────────────────────────────────────
    const aiForm = useForm({
        provider_name: ai.provider_name,
        model: ai.model,
        api_key: '',
    });

    // ── Facebook / Meta ────────────────────────────────────────────────────
    const metaForm = useForm({
        app_id: meta.app_id,
        app_secret: '',
        webhook_verify_token: '',
        redirect_uri: meta.redirect_uri,
        graph_api_version: meta.graph_api_version,
    });

    // ── Stripe ─────────────────────────────────────────────────────────────
    const stripeForm = useForm({
        stripe_key: '',
        stripe_secret: '',
        stripe_webhook_secret: '',
    });

    // ── SSLCommerz ─────────────────────────────────────────────────────────
    const sslczForm = useForm({
        store_id: sslcz.store_id,
        store_password: '',
        is_sandbox: sslcz.is_sandbox,
    });

    // ── Mail ───────────────────────────────────────────────────────────────
    const mailForm = useForm({
        host: mail.host,
        port: mail.port,
        username: mail.username,
        password: '',
        encryption: mail.encryption,
        from_address: mail.from_address,
        from_name: mail.from_name,
    });

    const AI_PROVIDERS = [
        { value: 'gemini', label: 'Google Gemini' },
        { value: 'anthropic', label: 'Anthropic Claude' },
        { value: 'openai', label: 'OpenAI GPT' },
    ];

    const AI_MODELS: Record<string, string[]> = {
        gemini: ['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-pro', 'gemini-1.5-flash'],
        anthropic: ['claude-opus-4-8', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001'],
        openai: ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'],
    };

    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin: API Keys & Settings" />
            <div className="space-y-6 p-4 max-w-3xl">
                <div>
                    <h1 className="text-xl font-semibold">API Keys & Settings</h1>
                    <p className="text-sm text-muted-foreground mt-0.5">
                        Manage API credentials. Sensitive values are stored encrypted and override .env at runtime.
                    </p>
                </div>

                <Flash />

                {/* ── AI Provider ─────────────────────────────────────────────── */}
                <SectionCard title="🤖 AI Provider" description="Controls which AI powers the bot replies.">
                    <form
                        onSubmit={(e) => { e.preventDefault(); aiForm.post('/admin/settings/ai'); }}
                        className="space-y-3"
                    >
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs">Provider</Label>
                                <select
                                    value={aiForm.data.provider_name}
                                    onChange={(e) => {
                                        aiForm.setData('provider_name', e.target.value);
                                        aiForm.setData('model', AI_MODELS[e.target.value]?.[0] ?? '');
                                    }}
                                    className="rounded border bg-background px-2 py-1.5 text-sm"
                                >
                                    {AI_PROVIDERS.map((p) => (
                                        <option key={p.value} value={p.value}>{p.label}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs">Model</Label>
                                <select
                                    value={aiForm.data.model}
                                    onChange={(e) => aiForm.setData('model', e.target.value)}
                                    className="rounded border bg-background px-2 py-1.5 text-sm font-mono"
                                >
                                    {(AI_MODELS[aiForm.data.provider_name] ?? []).map((m) => (
                                        <option key={m} value={m}>{m}</option>
                                    ))}
                                    <option value={aiForm.data.model}>{aiForm.data.model}</option>
                                </select>
                            </div>
                        </div>
                        <SecretInput
                            label="API Key"
                            name="api_key"
                            isSet={ai.key_is_set}
                            value={aiForm.data.api_key}
                            onChange={(v) => aiForm.setData('api_key', v)}
                        />
                        <Button type="submit" size="sm" disabled={aiForm.processing}>Save AI Settings</Button>
                    </form>
                </SectionCard>

                {/* ── Facebook / Meta ──────────────────────────────────────────── */}
                <SectionCard title="📘 Facebook / Meta App" description="Required for Messenger webhooks and page connections.">
                    <form
                        onSubmit={(e) => { e.preventDefault(); metaForm.post('/admin/settings/meta'); }}
                        className="space-y-3"
                    >
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs">App ID</Label>
                                <Input
                                    value={metaForm.data.app_id}
                                    onChange={(e) => metaForm.setData('app_id', e.target.value)}
                                    placeholder="2226848041458185"
                                    className="font-mono text-xs"
                                />
                            </div>
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs">Graph API Version</Label>
                                <Input
                                    value={metaForm.data.graph_api_version}
                                    onChange={(e) => metaForm.setData('graph_api_version', e.target.value)}
                                    placeholder="v20.0"
                                    className="font-mono text-xs"
                                />
                            </div>
                        </div>
                        <SecretInput
                            label="App Secret"
                            name="app_secret"
                            isSet={meta.app_secret_set}
                            value={metaForm.data.app_secret}
                            onChange={(v) => metaForm.setData('app_secret', v)}
                        />
                        <SecretInput
                            label="Webhook Verify Token"
                            name="webhook_verify_token"
                            isSet={meta.webhook_verify_token_set}
                            value={metaForm.data.webhook_verify_token}
                            onChange={(v) => metaForm.setData('webhook_verify_token', v)}
                            placeholder="fb_webhook_verify_2026_secret"
                        />
                        <div className="flex flex-col gap-1">
                            <Label className="text-xs">OAuth Redirect URI</Label>
                            <Input
                                value={metaForm.data.redirect_uri}
                                onChange={(e) => metaForm.setData('redirect_uri', e.target.value)}
                                placeholder="https://yourdomain.com/facebook/callback"
                                className="font-mono text-xs"
                            />
                        </div>
                        <Button type="submit" size="sm" disabled={metaForm.processing}>Save Meta Settings</Button>
                    </form>
                </SectionCard>

                {/* ── Stripe ───────────────────────────────────────────────────── */}
                <SectionCard title="💳 Stripe" description="Payment gateway for subscription billing.">
                    <form
                        onSubmit={(e) => { e.preventDefault(); stripeForm.post('/admin/settings/stripe'); }}
                        className="space-y-3"
                    >
                        <SecretInput
                            label="Publishable Key (pk_live_…)"
                            name="stripe_key"
                            isSet={stripe.key_set}
                            value={stripeForm.data.stripe_key}
                            onChange={(v) => stripeForm.setData('stripe_key', v)}
                            placeholder="pk_live_…"
                        />
                        <SecretInput
                            label="Secret Key (sk_live_…)"
                            name="stripe_secret"
                            isSet={stripe.secret_set}
                            value={stripeForm.data.stripe_secret}
                            onChange={(v) => stripeForm.setData('stripe_secret', v)}
                            placeholder="sk_live_…"
                        />
                        <SecretInput
                            label="Webhook Secret (whsec_…)"
                            name="stripe_webhook_secret"
                            isSet={stripe.webhook_secret_set}
                            value={stripeForm.data.stripe_webhook_secret}
                            onChange={(v) => stripeForm.setData('stripe_webhook_secret', v)}
                            placeholder="whsec_…"
                        />
                        <Button type="submit" size="sm" disabled={stripeForm.processing}>Save Stripe Settings</Button>
                    </form>
                </SectionCard>

                {/* ── SSLCommerz ───────────────────────────────────────────────── */}
                <SectionCard title="🏦 SSLCommerz" description="Local payment gateway (Bangladesh).">
                    <form
                        onSubmit={(e) => { e.preventDefault(); sslczForm.post('/admin/settings/sslcz'); }}
                        className="space-y-3"
                    >
                        <div className="flex flex-col gap-1">
                            <Label className="text-xs">Store ID</Label>
                            <Input
                                value={sslczForm.data.store_id}
                                onChange={(e) => sslczForm.setData('store_id', e.target.value)}
                                placeholder="your_store_id"
                                className="font-mono text-xs"
                            />
                        </div>
                        <SecretInput
                            label="Store Password"
                            name="store_password"
                            isSet={sslcz.store_password_set}
                            value={sslczForm.data.store_password}
                            onChange={(v) => sslczForm.setData('store_password', v)}
                            placeholder="your_store_password"
                        />
                        <div className="flex items-center gap-3">
                            <input
                                type="checkbox"
                                id="is_sandbox"
                                checked={sslczForm.data.is_sandbox}
                                onChange={(e) => sslczForm.setData('is_sandbox', e.target.checked)}
                                className="h-4 w-4 rounded border"
                            />
                            <Label htmlFor="is_sandbox" className="text-xs cursor-pointer">
                                Sandbox / Test mode
                            </Label>
                        </div>
                        <Button type="submit" size="sm" disabled={sslczForm.processing}>Save SSLCommerz Settings</Button>
                    </form>
                </SectionCard>

                {/* ── Mail / SMTP ──────────────────────────────────────────────── */}
                <SectionCard title="✉️ Mail / SMTP" description="Outgoing email for notifications and OTP.">
                    <form
                        onSubmit={(e) => { e.preventDefault(); mailForm.post('/admin/settings/mail'); }}
                        className="space-y-3"
                    >
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div className="col-span-2 flex flex-col gap-1">
                                <Label className="text-xs">SMTP Host</Label>
                                <Input
                                    value={mailForm.data.host}
                                    onChange={(e) => mailForm.setData('host', e.target.value)}
                                    placeholder="smtp.gmail.com"
                                    className="font-mono text-xs"
                                />
                            </div>
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs">Port</Label>
                                <Input
                                    type="number"
                                    value={mailForm.data.port}
                                    onChange={(e) => mailForm.setData('port', e.target.value)}
                                    placeholder="587"
                                    className="font-mono text-xs"
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs">Username / Email</Label>
                                <Input
                                    value={mailForm.data.username}
                                    onChange={(e) => mailForm.setData('username', e.target.value)}
                                    placeholder="you@gmail.com"
                                    className="font-mono text-xs"
                                />
                            </div>
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs">Encryption</Label>
                                <select
                                    value={mailForm.data.encryption}
                                    onChange={(e) => mailForm.setData('encryption', e.target.value)}
                                    className="rounded border bg-background px-2 py-1.5 text-sm"
                                >
                                    <option value="tls">TLS (port 587)</option>
                                    <option value="ssl">SSL (port 465)</option>
                                    <option value="">None</option>
                                </select>
                            </div>
                        </div>
                        <SecretInput
                            label="App Password"
                            name="mail_password"
                            isSet={mail.password_set}
                            value={mailForm.data.password}
                            onChange={(v) => mailForm.setData('password', v)}
                            placeholder="Gmail App Password (not your Gmail password)"
                        />
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs">From Address</Label>
                                <Input
                                    value={mailForm.data.from_address}
                                    onChange={(e) => mailForm.setData('from_address', e.target.value)}
                                    placeholder="noreply@yoursite.com"
                                    className="font-mono text-xs"
                                />
                            </div>
                            <div className="flex flex-col gap-1">
                                <Label className="text-xs">From Name</Label>
                                <Input
                                    value={mailForm.data.from_name}
                                    onChange={(e) => mailForm.setData('from_name', e.target.value)}
                                    placeholder="Befit Automation"
                                    className="text-xs"
                                />
                            </div>
                        </div>
                        <Button type="submit" size="sm" disabled={mailForm.processing}>Save Mail Settings</Button>
                    </form>
                </SectionCard>
            </div>
        </AdminDashboardLayout>
    );
}
