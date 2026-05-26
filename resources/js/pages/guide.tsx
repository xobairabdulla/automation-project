import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    BookOpen,
    Bot,
    CheckCircle2,
    Facebook,
    HelpCircle,
    Inbox,
    MessageCircle,
    Settings,
    Shield,
    ThumbsUp,
    UserCheck,
    Zap,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'User Guide', href: '/guide' },
];

interface Step {
    number: number;
    title: string;
    description: string;
    icon: React.ReactNode;
    color: string;
    tips?: string[];
    action?: { label: string; href: string };
}

const steps: Step[] = [
    {
        number: 1,
        title: 'Create Your Account & Log In',
        description:
            'Start by registering with your email address. Once registered, log in to access your dashboard. If you were invited by a team admin, use the invitation link from your email.',
        icon: <UserCheck className="h-6 w-6 text-white" />,
        color: 'bg-gradient-to-br from-blue-500 to-blue-700',
        tips: [
            'Use a business email for better deliverability.',
            'Save your password securely.',
            'Check your inbox for a verification email.',
        ],
        action: { label: 'Go to Login', href: '/login' },
    },
    {
        number: 2,
        title: 'Connect Your Facebook Account',
        description:
            'Go to Facebook Pages and click "Connect Facebook Account". You will be redirected to Facebook to authorize the app. Make sure you are logged into the Facebook account that manages the page you want to automate.',
        icon: <Facebook className="h-6 w-6 text-white" />,
        color: 'bg-gradient-to-br from-[#1877F2] to-blue-800',
        tips: [
            'You need Admin access to the Facebook page.',
            'Grant all requested permissions — they are needed for messaging and comments.',
            'You can connect multiple Facebook accounts.',
        ],
        action: { label: 'Connect Facebook', href: '/facebook/pages' },
    },
    {
        number: 3,
        title: 'Connect Your Facebook Page',
        description:
            'After authorizing, your Facebook pages will appear in the list. Find the page you want to automate and click "Connect". This subscribes that page to receive webhooks (incoming messages and comments).',
        icon: <CheckCircle2 className="h-6 w-6 text-white" />,
        color: 'bg-gradient-to-br from-emerald-500 to-emerald-700',
        tips: [
            'Only connected pages receive automation.',
            'You can connect or disconnect pages anytime.',
            'A green "Connected" badge confirms success.',
        ],
        action: { label: 'Manage Pages', href: '/facebook/pages' },
    },
    {
        number: 4,
        title: 'Enable Message & Comment Automation',
        description:
            'On the Facebook Pages screen, you will see toggle buttons for "Messages" and "Comments" under each connected page. Turn them ON to enable automatic replies. You can enable them independently.',
        icon: <Zap className="h-6 w-6 text-white" />,
        color: 'bg-gradient-to-br from-amber-500 to-orange-600',
        tips: [
            '"Messages ON" — automatically replies to anyone who sends a private message to your page.',
            '"Comments ON" — automatically replies to comments on your posts.',
            'Both can be toggled ON/OFF without disconnecting the page.',
        ],
        action: { label: 'Facebook Pages', href: '/facebook/pages' },
    },
    {
        number: 5,
        title: 'Set Up Automation Rules (Optional)',
        description:
            'Automation Rules let you define keyword-based responses. When a message or comment contains a keyword you define, a specific reply is sent. Rules are matched in priority order — lower number = higher priority.',
        icon: <Bot className="h-6 w-6 text-white" />,
        color: 'bg-gradient-to-br from-violet-500 to-violet-700',
        tips: [
            'Example: keyword "price" → reply "Our prices start from $10. Visit our website!"',
            'Example: keyword "hello" → reply "Hi there! How can we help you?"',
            'If no rule matches, the system falls back to AI reply or a default message.',
            'Channel: choose "Message", "Comment", or "Both".',
        ],
        action: { label: 'Automation Rules', href: '/automation-rules' },
    },
    {
        number: 6,
        title: 'Set Up AI Knowledge Base (Optional)',
        description:
            'The AI can answer questions based on your Knowledge Base. Add FAQs, product details, policies, or any information you want the AI to use when generating replies. The more you add, the smarter the AI replies become.',
        icon: <BookOpen className="h-6 w-6 text-white" />,
        color: 'bg-gradient-to-br from-teal-500 to-teal-700',
        tips: [
            'Add your business FAQs, product info, and contact details.',
            'Keep entries concise and clear.',
            'Go to AI Settings to configure the AI reply behavior.',
        ],
        action: { label: 'Knowledge Base', href: '/knowledge-base' },
    },
    {
        number: 7,
        title: 'Configure AI Settings',
        description:
            'In AI Settings, you can customize how the AI behaves: set the tone of replies, configure what happens when the AI does not know the answer (e.g., transfer to human), and set the AI model to use.',
        icon: <Settings className="h-6 w-6 text-white" />,
        color: 'bg-gradient-to-br from-rose-500 to-rose-700',
        tips: [
            '"Unknown Answer Action": choose to send a default message or trigger human handover.',
            'Test the AI with sample questions before going live.',
        ],
        action: { label: 'AI Settings', href: '/ai-settings' },
    },
    {
        number: 8,
        title: 'Test It — Send a Message to Your Page',
        description:
            'From a personal Facebook account (not the page admin account), send a message to your page. Within seconds you should see an automatic reply. Check the Inbox to confirm the conversation was recorded.',
        icon: <MessageCircle className="h-6 w-6 text-white" />,
        color: 'bg-gradient-to-br from-cyan-500 to-cyan-700',
        tips: [
            'Make sure the Queue worker is running on the server (php artisan queue:work).',
            'Check Inbox — all conversations are stored there.',
            'If no reply came: verify automation is ON, webhook is configured, and queue worker is running.',
        ],
        action: { label: 'View Inbox', href: '/inbox' },
    },
    {
        number: 9,
        title: 'Monitor Inbox & Reply Manually',
        description:
            'The Inbox shows all conversations from your Facebook pages. You can read messages, reply manually, assign conversations to team members, add internal notes, and enable "Human Takeover" when you want to handle a conversation personally.',
        icon: <Inbox className="h-6 w-6 text-white" />,
        color: 'bg-gradient-to-br from-indigo-500 to-indigo-700',
        tips: [
            '"Human Takeover" pauses auto-replies for that conversation.',
            'You can assign conversations to specific team members.',
            'Use Tags to organize conversations (e.g., "urgent", "sales").',
        ],
        action: { label: 'Open Inbox', href: '/inbox' },
    },
    {
        number: 10,
        title: 'Invite Team Members',
        description:
            'Go to Team and invite colleagues by email. They can handle inbox conversations, manage automation rules, and view analytics. Assign appropriate roles to control their access level.',
        icon: <UserCheck className="h-6 w-6 text-white" />,
        color: 'bg-gradient-to-br from-pink-500 to-pink-700',
        tips: [
            'Roles: Client Admin (full access), Agent (inbox + comments), Support Admin (read-only).',
            'Invited members will receive an email with a link to join.',
        ],
        action: { label: 'Team Management', href: '/team' },
    },
    {
        number: 11,
        title: 'Check Analytics & Notifications',
        description:
            'Use the Analytics page to see reply counts, response rates, and usage trends. Enable browser Notifications to get alerted when new messages arrive. Keep an eye on your usage limits to avoid running out of reply quota.',
        icon: <BarChart3 className="h-6 w-6 text-white" />,
        color: 'bg-gradient-to-br from-orange-500 to-orange-700',
        tips: [
            'Usage resets monthly.',
            'Upgrade your plan from Billing if you need more quota.',
            'Set up Reply Templates for faster manual replies.',
        ],
        action: { label: 'View Analytics', href: '/analytics' },
    },
];

const faqs = [
    {
        q: 'Why is the bot not replying?',
        a: 'Check: (1) Is the page connected? (2) Is message/comment automation ON? (3) Is the queue worker running on the server? (4) Are you within usage limits?',
    },
    {
        q: 'Can I use the bot for multiple pages?',
        a: 'Yes! Connect as many pages as your plan allows. Each page can have independent automation settings and rules.',
    },
    {
        q: 'What happens when the AI does not know the answer?',
        a: 'Depending on your AI Settings, it either sends a default fallback message ("We will get back to you shortly") or triggers a human handover.',
    },
    {
        q: 'How do Automation Rules work with AI?',
        a: 'Rules are checked first, in priority order. If a rule matches, that reply is sent and AI is not used. If no rule matches, AI is tried. If AI fails, the default reply is sent.',
    },
    {
        q: 'What is Human Takeover?',
        a: 'It pauses auto-replies for a specific conversation so a human agent can respond personally. You can disable it anytime to resume automation.',
    },
    {
        q: 'My Facebook token expired. What do I do?',
        a: 'Go to Facebook Pages, click "Connect Facebook Account" to re-authorize. This generates a fresh token. Then reconnect your pages.',
    },
];

export default function GuidePage() {
    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="User Guide" />
            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6 max-w-4xl mx-auto w-full">

                {/* Hero */}
                <div className="rounded-2xl bg-gradient-to-br from-blue-600 via-violet-600 to-indigo-700 p-8 text-white shadow-xl">
                    <div className="flex items-center gap-3 mb-3">
                        <div className="rounded-xl bg-white/20 p-2.5">
                            <HelpCircle className="h-6 w-6 text-white" />
                        </div>
                        <span className="text-sm font-medium text-white/80 uppercase tracking-widest">Getting Started</span>
                    </div>
                    <h1 className="text-3xl font-bold mb-2">Step-by-Step User Guide</h1>
                    <p className="text-white/80 text-base leading-relaxed max-w-2xl">
                        New here? Follow these steps to set up your Facebook automation from scratch. It takes about 10 minutes to get your first auto-reply working.
                    </p>
                    <div className="flex flex-wrap gap-3 mt-5">
                        <Button asChild className="bg-white text-blue-700 hover:bg-white/90 font-semibold shadow-md">
                            <Link href="/facebook/pages">
                                <Facebook className="mr-2 h-4 w-4" />
                                Connect Facebook Now
                            </Link>
                        </Button>
                        <Button asChild variant="outline" className="border-white/40 text-white hover:bg-white/10">
                            <Link href="/dashboard">Go to Dashboard</Link>
                        </Button>
                    </div>
                </div>

                {/* Steps */}
                <div className="space-y-4">
                    <h2 className="text-xl font-bold">Setup Steps</h2>
                    {steps.map((step) => (
                        <Card key={step.number} className="overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                            <CardContent className="p-0">
                                <div className="flex gap-0">
                                    {/* Step number sidebar */}
                                    <div className={`flex flex-col items-center justify-start p-4 ${step.color} min-w-[72px]`}>
                                        <div className="rounded-full bg-white/20 p-2 mb-2">{step.icon}</div>
                                        <span className="text-2xl font-black text-white">{step.number}</span>
                                    </div>
                                    {/* Content */}
                                    <div className="flex-1 p-4">
                                        <h3 className="font-semibold text-base mb-1">{step.title}</h3>
                                        <p className="text-sm text-muted-foreground mb-3 leading-relaxed">{step.description}</p>
                                        {step.tips && step.tips.length > 0 && (
                                            <ul className="space-y-1 mb-3">
                                                {step.tips.map((tip, i) => (
                                                    <li key={i} className="flex items-start gap-2 text-xs text-muted-foreground">
                                                        <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500 flex-shrink-0 mt-0.5" />
                                                        {tip}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                        {step.action && (
                                            <Button asChild size="sm" variant="outline">
                                                <Link href={step.action.href}>{step.action.label} →</Link>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Feature Overview */}
                <div>
                    <h2 className="text-xl font-bold mb-4">Feature Overview</h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {[
                            { icon: <MessageCircle className="h-5 w-5 text-blue-600" />, title: 'Inbox', desc: 'All Facebook conversations in one place. Reply, assign, tag, and add notes.', href: '/inbox', bg: 'bg-blue-50' },
                            { icon: <ThumbsUp className="h-5 w-5 text-violet-600" />, title: 'Comments', desc: 'View and auto-reply to comments on your Facebook posts.', href: '/comments', bg: 'bg-violet-50' },
                            { icon: <Zap className="h-5 w-5 text-amber-600" />, title: 'Automation Rules', desc: 'Keyword-triggered automatic replies for messages and comments.', href: '/automation-rules', bg: 'bg-amber-50' },
                            { icon: <Bot className="h-5 w-5 text-emerald-600" />, title: 'AI Settings', desc: 'Configure the AI reply engine, tone, and fallback behavior.', href: '/ai-settings', bg: 'bg-emerald-50' },
                            { icon: <BookOpen className="h-5 w-5 text-teal-600" />, title: 'Knowledge Base', desc: 'Feed your business info to the AI for smarter, accurate replies.', href: '/knowledge-base', bg: 'bg-teal-50' },
                            { icon: <Shield className="h-5 w-5 text-rose-600" />, title: 'Reply Templates', desc: 'Save common replies and use them quickly in the inbox.', href: '/reply-templates', bg: 'bg-rose-50' },
                            { icon: <BarChart3 className="h-5 w-5 text-orange-600" />, title: 'Analytics', desc: 'Track reply volume, response rates, and usage over time.', href: '/analytics', bg: 'bg-orange-50' },
                            { icon: <Bell className="h-5 w-5 text-pink-600" />, title: 'Notifications', desc: 'Get alerts for new messages, failed replies, and system events.', href: '/notifications', bg: 'bg-pink-50' },
                            { icon: <UserCheck className="h-5 w-5 text-indigo-600" />, title: 'Team', desc: 'Invite teammates and assign roles to collaborate on inbox.', href: '/team', bg: 'bg-indigo-50' },
                        ].map((f) => (
                            <Link key={f.href} href={f.href} className={`rounded-xl p-4 ${f.bg} hover:shadow-md transition-all hover:scale-[1.02] block`}>
                                <div className="flex items-center gap-2 mb-2">
                                    {f.icon}
                                    <span className="font-semibold text-sm">{f.title}</span>
                                </div>
                                <p className="text-xs text-muted-foreground leading-relaxed">{f.desc}</p>
                            </Link>
                        ))}
                    </div>
                </div>

                {/* FAQ */}
                <div>
                    <h2 className="text-xl font-bold mb-4">Frequently Asked Questions</h2>
                    <div className="space-y-3">
                        {faqs.map((faq, i) => (
                            <Card key={i} className="shadow-sm">
                                <CardContent className="p-4">
                                    <div className="flex gap-3">
                                        <div className="rounded-full bg-blue-100 p-1.5 flex-shrink-0 h-7 w-7 flex items-center justify-center">
                                            <HelpCircle className="h-4 w-4 text-blue-600" />
                                        </div>
                                        <div>
                                            <p className="font-semibold text-sm mb-1">{faq.q}</p>
                                            <p className="text-sm text-muted-foreground leading-relaxed">{faq.a}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>

                {/* CTA */}
                <Card className="bg-gradient-to-r from-emerald-500 to-teal-600 border-0 shadow-xl">
                    <CardContent className="p-6 text-center text-white">
                        <h3 className="text-xl font-bold mb-2">Ready to automate?</h3>
                        <p className="text-white/80 text-sm mb-4">Connect your Facebook page and your first auto-reply will be running in minutes.</p>
                        <div className="flex justify-center gap-3 flex-wrap">
                            <Button asChild className="bg-white text-emerald-700 hover:bg-white/90 font-semibold">
                                <Link href="/facebook/pages">
                                    <Facebook className="mr-2 h-4 w-4" />
                                    Connect Facebook
                                </Link>
                            </Button>
                            <Button asChild variant="outline" className="border-white/40 text-white hover:bg-white/10">
                                <Link href="/dashboard">Dashboard</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </ClientDashboardLayout>
    );
}
