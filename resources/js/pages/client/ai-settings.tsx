import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';

interface AiPrompt {
    id: number;
    facebook_page_id: number;
    preferred_language: string;
    tone: string;
    reply_length: string;
    use_emoji: boolean;
    unknown_answer_action: string;
    restricted_instructions: string | null;
}

interface Page {
    id: number;
    page_name: string;
}

interface Props {
    aiPrompts: AiPrompt[];
    pages: Page[];
    selectedPageId: number | null;
}

interface SharedProps {
    flash?: { success?: string; error?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'AI Settings', href: '/ai-settings' },
];

const defaultPromptData = {
    facebook_page_id: '',
    preferred_language: 'en',
    tone: 'friendly',
    reply_length: 'short',
    use_emoji: false,
    unknown_answer_action: 'human_handover',
    restricted_instructions: '',
};

export default function AiSettings({ aiPrompts, pages, selectedPageId }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [testQuestion, setTestQuestion] = useState('');
    const [testResult, setTestResult] = useState<{ reply: string; unknown: boolean } | null>(null);
    const [testPageId, setTestPageId] = useState(String(selectedPageId ?? pages[0]?.id ?? ''));
    const [testing, setTesting] = useState(false);

    const selectedPage = pages.find((p) => p.id === selectedPageId) ?? pages[0] ?? null;

    const form = useForm({
        ...defaultPromptData,
        facebook_page_id: String(selectedPage?.id ?? ''),
    });

    function loadPromptForPage(pageId: string) {
        form.setData('facebook_page_id', pageId);
        const existing = aiPrompts.find((p) => String(p.facebook_page_id) === pageId);
        if (existing) {
            form.setData({
                facebook_page_id: pageId,
                preferred_language: existing.preferred_language,
                tone: existing.tone,
                reply_length: existing.reply_length,
                use_emoji: existing.use_emoji,
                unknown_answer_action: existing.unknown_answer_action,
                restricted_instructions: existing.restricted_instructions ?? '',
            });
        } else {
            form.setData({ ...defaultPromptData, facebook_page_id: pageId });
        }
    }

    function submitSettings(e: React.FormEvent) {
        e.preventDefault();
        form.post('/ai-settings');
    }

    async function runTest() {
        if (!testQuestion.trim() || !testPageId) return;
        setTesting(true);
        setTestResult(null);
        try {
            const res = await axios.post('/ai-settings/test-reply', {
                facebook_page_id: testPageId,
                question: testQuestion,
            });
            setTestResult(res.data);
        } catch {
            setTestResult({ reply: 'Error running test.', unknown: false });
        } finally {
            setTesting(false);
        }
    }

    function filterByPage(pageId: number | null) {
        router.get('/ai-settings', pageId ? { page_id: pageId } : {}, { preserveState: true });
    }

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Settings" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">AI Settings</h1>
                        <p className="text-muted-foreground text-sm">Configure AI reply tone and behaviour per page.</p>
                    </div>
                    <Select value={String(selectedPageId ?? '')} onValueChange={(v) => filterByPage(v ? Number(v) : null)}>
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="All pages" />
                        </SelectTrigger>
                        <SelectContent>
                            {pages.map((p) => (
                                <SelectItem key={p.id} value={String(p.id)}>{p.page_name}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {flash?.success && <Alert><AlertDescription>{flash.success}</AlertDescription></Alert>}

                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Settings Form */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">AI Prompt Configuration</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitSettings} className="grid gap-4">
                                <div>
                                    <Label>Page</Label>
                                    <Select
                                        value={form.data.facebook_page_id}
                                        onValueChange={(v) => loadPromptForPage(v)}
                                    >
                                        <SelectTrigger><SelectValue placeholder="Select page" /></SelectTrigger>
                                        <SelectContent>
                                            {pages.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>
                                                    {p.page_name}
                                                    {aiPrompts.some((ap) => ap.facebook_page_id === p.id) && (
                                                        <Badge variant="outline" className="ml-2 text-xs">configured</Badge>
                                                    )}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <Label>Tone</Label>
                                        <Select value={form.data.tone} onValueChange={(v) => form.setData('tone', v)}>
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="friendly">Friendly</SelectItem>
                                                <SelectItem value="professional">Professional</SelectItem>
                                                <SelectItem value="casual">Casual</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label>Reply Length</Label>
                                        <Select value={form.data.reply_length} onValueChange={(v) => form.setData('reply_length', v)}>
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="short">Short</SelectItem>
                                                <SelectItem value="medium">Medium</SelectItem>
                                                <SelectItem value="long">Long</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <Label>Language</Label>
                                        <Input value={form.data.preferred_language} onChange={(e) => form.setData('preferred_language', e.target.value)} maxLength={10} />
                                    </div>
                                    <div>
                                        <Label>Unknown Answer</Label>
                                        <Select value={form.data.unknown_answer_action} onValueChange={(v) => form.setData('unknown_answer_action', v)}>
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="human_handover">Human Handover</SelectItem>
                                                <SelectItem value="send_default">Send Default Reply</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        id="use_emoji"
                                        checked={form.data.use_emoji}
                                        onChange={(e) => form.setData('use_emoji', e.target.checked)}
                                        className="h-4 w-4"
                                    />
                                    <Label htmlFor="use_emoji">Use emojis in replies</Label>
                                </div>
                                <div>
                                    <Label>Restricted Instructions</Label>
                                    <textarea
                                        className="w-full rounded border p-2 text-sm"
                                        rows={3}
                                        placeholder="e.g. Never mention competitor names. Always end with contact info."
                                        value={form.data.restricted_instructions}
                                        onChange={(e) => form.setData('restricted_instructions', e.target.value)}
                                    />
                                </div>
                                <Button type="submit" disabled={form.processing}>Save Settings</Button>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Test Box */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Test AI Reply</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div>
                                <Label>Page to test</Label>
                                <Select value={testPageId} onValueChange={setTestPageId}>
                                    <SelectTrigger><SelectValue placeholder="Select page" /></SelectTrigger>
                                    <SelectContent>
                                        {pages.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>{p.page_name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Your question</Label>
                                <textarea
                                    className="w-full rounded border p-2 text-sm"
                                    rows={3}
                                    placeholder="e.g. What are your delivery times?"
                                    value={testQuestion}
                                    onChange={(e) => setTestQuestion(e.target.value)}
                                />
                            </div>
                            <Button onClick={runTest} disabled={testing || !testQuestion.trim()} className="w-full">
                                {testing ? 'Generating...' : 'Test Reply'}
                            </Button>
                            {testResult && (
                                <div className={`rounded border p-3 text-sm ${testResult.unknown ? 'border-orange-300 bg-orange-50 dark:bg-orange-950' : 'border-green-300 bg-green-50 dark:bg-green-950'}`}>
                                    {testResult.unknown && (
                                        <p className="mb-1 font-medium text-orange-700 dark:text-orange-300">Unknown — would trigger handover</p>
                                    )}
                                    <p className="whitespace-pre-wrap">{testResult.reply}</p>
                                </div>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Requires knowledge base items and a configured OpenAI API key.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </ClientDashboardLayout>
    );
}
