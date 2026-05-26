import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { BarChart3, Bot, CheckCircle2, Facebook, HelpCircle, Inbox, MessageCircle, ThumbsUp, Zap } from 'lucide-react';

const features = [
    {
        icon: <MessageCircle className="h-6 w-6 text-blue-600" />,
        title: 'Auto Message Replies',
        desc: 'Instantly reply to Facebook DMs 24/7 using keyword rules or AI.',
        bg: 'bg-blue-50',
    },
    {
        icon: <ThumbsUp className="h-6 w-6 text-violet-600" />,
        title: 'Auto Comment Replies',
        desc: 'Respond to post comments automatically and increase engagement.',
        bg: 'bg-violet-50',
    },
    {
        icon: <Bot className="h-6 w-6 text-amber-600" />,
        title: 'AI-Powered Answers',
        desc: 'Train the AI with your business info for smart, accurate replies.',
        bg: 'bg-amber-50',
    },
    {
        icon: <Zap className="h-6 w-6 text-emerald-600" />,
        title: 'Automation Rules',
        desc: 'Set keyword triggers to send specific replies — no coding needed.',
        bg: 'bg-emerald-50',
    },
    {
        icon: <Inbox className="h-6 w-6 text-indigo-600" />,
        title: 'Unified Inbox',
        desc: 'Manage all your Facebook conversations in one organized inbox.',
        bg: 'bg-indigo-50',
    },
    {
        icon: <BarChart3 className="h-6 w-6 text-rose-600" />,
        title: 'Analytics & Reports',
        desc: 'Track reply rates, usage, and team performance over time.',
        bg: 'bg-rose-50',
    },
];

const steps = [
    { num: '01', title: 'Connect Facebook', desc: 'Link your Facebook account and authorize your pages.' },
    { num: '02', title: 'Enable Automation', desc: 'Turn on message and comment automation for each page.' },
    { num: '03', title: 'Set Up Rules', desc: 'Create keyword rules or let AI handle all replies.' },
    { num: '04', title: 'Sit Back & Grow', desc: 'Your pages reply 24/7 — you focus on growing your business.' },
];

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Facebook Automation Platform" />
            <div className="min-h-screen bg-gradient-to-b from-slate-950 via-blue-950 to-slate-950 text-white">

                {/* Nav */}
                <nav className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                    <div className="flex items-center gap-2">
                        <div className="rounded-xl bg-[#1877F2] p-2">
                            <Facebook className="h-5 w-5 text-white" />
                        </div>
                        <span className="text-lg font-bold">FBAutomate</span>
                    </div>
                    <div className="flex items-center gap-3">
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors"
                            >
                                Go to Dashboard →
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="text-sm text-slate-300 hover:text-white transition-colors"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition-colors"
                                >
                                    Get Started Free
                                </Link>
                            </>
                        )}
                    </div>
                </nav>

                {/* Hero */}
                <section className="mx-auto max-w-6xl px-6 py-20 text-center">
                    <div className="inline-flex items-center gap-2 rounded-full border border-blue-500/30 bg-blue-500/10 px-4 py-1.5 text-sm text-blue-300 mb-6">
                        <Zap className="h-3.5 w-3.5" />
                        Facebook Automation Made Simple
                    </div>
                    <h1 className="text-4xl font-black tracking-tight sm:text-5xl lg:text-6xl mb-6 leading-tight">
                        Auto-Reply to{' '}
                        <span className="bg-gradient-to-r from-blue-400 to-violet-400 bg-clip-text text-transparent">
                            Facebook Messages
                        </span>
                        <br />& Comments — 24/7
                    </h1>
                    <p className="text-slate-300 text-lg max-w-2xl mx-auto mb-8 leading-relaxed">
                        Connect your Facebook pages, set up automation rules, and let AI handle customer replies.
                        Never miss a message again.
                    </p>
                    <div className="flex justify-center gap-4 flex-wrap">
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="rounded-xl bg-blue-600 px-8 py-3.5 text-base font-semibold text-white hover:bg-blue-700 transition-all hover:scale-105 shadow-lg shadow-blue-600/30"
                            >
                                Open Dashboard →
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('register')}
                                    className="rounded-xl bg-blue-600 px-8 py-3.5 text-base font-semibold text-white hover:bg-blue-700 transition-all hover:scale-105 shadow-lg shadow-blue-600/30"
                                >
                                    Start for Free →
                                </Link>
                                <Link
                                    href={route('login')}
                                    className="rounded-xl border border-slate-600 px-8 py-3.5 text-base font-semibold text-slate-300 hover:bg-slate-800 transition-all"
                                >
                                    Log In
                                </Link>
                            </>
                        )}
                    </div>

                    {/* Social proof */}
                    <div className="mt-10 flex justify-center gap-8 flex-wrap text-sm text-slate-400">
                        {['No credit card required', 'Setup in 10 minutes', 'Connect unlimited pages'].map((t) => (
                            <div key={t} className="flex items-center gap-2">
                                <CheckCircle2 className="h-4 w-4 text-emerald-400" />
                                {t}
                            </div>
                        ))}
                    </div>
                </section>

                {/* Features */}
                <section className="mx-auto max-w-6xl px-6 py-16">
                    <h2 className="text-center text-3xl font-bold mb-10">Everything you need to automate Facebook</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {features.map((f) => (
                            <div key={f.title} className="rounded-2xl bg-slate-900/60 border border-slate-800 p-5 hover:border-slate-600 transition-colors">
                                <div className={`inline-flex rounded-xl p-2.5 mb-3 ${f.bg}`}>{f.icon}</div>
                                <h3 className="font-semibold text-base mb-1 text-white">{f.title}</h3>
                                <p className="text-sm text-slate-400 leading-relaxed">{f.desc}</p>
                            </div>
                        ))}
                    </div>
                </section>

                {/* How it works */}
                <section className="mx-auto max-w-6xl px-6 py-16">
                    <h2 className="text-center text-3xl font-bold mb-10">How it works</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {steps.map((s) => (
                            <div key={s.num} className="rounded-2xl bg-gradient-to-br from-slate-800 to-slate-900 border border-slate-700 p-5 text-center">
                                <div className="text-4xl font-black text-blue-400/30 mb-2">{s.num}</div>
                                <h3 className="font-bold text-base mb-2 text-white">{s.title}</h3>
                                <p className="text-sm text-slate-400 leading-relaxed">{s.desc}</p>
                            </div>
                        ))}
                    </div>
                </section>

                {/* CTA */}
                <section className="mx-auto max-w-6xl px-6 py-16">
                    <div className="rounded-3xl bg-gradient-to-br from-blue-600 to-violet-700 p-10 text-center shadow-2xl">
                        <h2 className="text-3xl font-black mb-3">Ready to automate your Facebook pages?</h2>
                        <p className="text-white/80 mb-6 text-base max-w-xl mx-auto">Join businesses that save hours every day by automating their Facebook replies.</p>
                        <div className="flex justify-center gap-4 flex-wrap">
                            {auth.user ? (
                                <>
                                    <Link
                                        href={route('dashboard')}
                                        className="rounded-xl bg-white text-blue-700 px-8 py-3 text-base font-bold hover:bg-white/90 transition-all"
                                    >
                                        Go to Dashboard
                                    </Link>
                                    <Link
                                        href="/guide"
                                        className="rounded-xl border border-white/40 text-white px-8 py-3 text-base font-semibold hover:bg-white/10 transition-all inline-flex items-center gap-2"
                                    >
                                        <HelpCircle className="h-4 w-4" />
                                        User Guide
                                    </Link>
                                </>
                            ) : (
                                <Link
                                    href={route('register')}
                                    className="rounded-xl bg-white text-blue-700 px-8 py-3 text-base font-bold hover:bg-white/90 transition-all"
                                >
                                    Create Free Account →
                                </Link>
                            )}
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="mx-auto max-w-6xl px-6 py-8 border-t border-slate-800 flex items-center justify-between flex-wrap gap-4 text-sm text-slate-500">
                    <div className="flex items-center gap-2">
                        <div className="rounded-lg bg-[#1877F2] p-1.5">
                            <Facebook className="h-4 w-4 text-white" />
                        </div>
                        <span className="font-semibold text-slate-400">FBAutomate</span>
                    </div>
                    <div className="flex gap-6">
                        {auth.user ? (
                            <>
                                <Link href="/dashboard" className="hover:text-slate-300 transition-colors">Dashboard</Link>
                                <Link href="/guide" className="hover:text-slate-300 transition-colors">Guide</Link>
                            </>
                        ) : (
                            <>
                                <Link href={route('login')} className="hover:text-slate-300 transition-colors">Login</Link>
                                <Link href={route('register')} className="hover:text-slate-300 transition-colors">Register</Link>
                            </>
                        )}
                    </div>
                </footer>
            </div>
        </>
    );
}
