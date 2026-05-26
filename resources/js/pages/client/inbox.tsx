import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, CheckCheck, Clock, MessageSquare, Play, Search, Send, Tag, User, UserX, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface ConversationTag {
    id: number;
    name: string;
    color: string;
}

interface Page {
    id: number;
    page_name: string;
}

interface Customer {
    id: number;
    name: string | null;
    external_customer_id: string;
    profile_picture_url: string | null;
    phone: string | null;
    email: string | null;
}

interface Message {
    id: number;
    direction: string;
    sender_type: string;
    message_text: string;
    status: string;
    created_at: string;
}

interface Note {
    id: number;
    note: string;
    created_at: string;
    user: { id: number; name: string };
}

interface ConversationSummary {
    id: number;
    status: string;
    human_takeover: boolean;
    is_read: boolean;
    last_customer_message_at: string | null;
    customer: Customer;
    facebook_page: { id: number; page_name: string };
    tags: ConversationTag[];
    messages: Message[];
}

interface ConversationDetail extends ConversationSummary {
    notes: Note[];
}

interface Props {
    pages: Page[];
    tags: ConversationTag[];
}

interface SharedProps {
    auth: { user: { id: number; name: string } };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inbox', href: '/inbox' },
];

const FILTERS = [
    { key: 'all', label: 'All' },
    { key: 'unread', label: 'Unread' },
    { key: 'open', label: 'Open' },
    { key: 'pending', label: 'Pending' },
    { key: 'human_needed', label: 'Human Needed' },
    { key: 'assigned', label: 'Assigned to Me' },
    { key: 'closed', label: 'Closed' },
];

function timeAgo(dateStr: string | null): string {
    if (!dateStr) return '';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'now';
    if (mins < 60) return `${mins}m`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h`;
    return `${Math.floor(hrs / 24)}d`;
}

export default function InboxPage({ pages, tags: initialTags }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const [conversations, setConversations] = useState<ConversationSummary[]>([]);
    const [selected, setSelected] = useState<ConversationDetail | null>(null);
    const [loadingList, setLoadingList] = useState(true);
    const [loadingConv, setLoadingConv] = useState(false);
    const [filter, setFilter] = useState('all');
    const [search, setSearch] = useState('');
    const [pageId, setPageId] = useState('');
    const [replyText, setReplyText] = useState('');
    const [sending, setSending] = useState(false);
    const [newNote, setNewNote] = useState('');
    const [savingNote, setSavingNote] = useState(false);
    const [tags, setTags] = useState<ConversationTag[]>(initialTags);
    const [newTagName, setNewTagName] = useState('');
    const [showTagInput, setShowTagInput] = useState(false);
    const [handoverReason, setHandoverReason] = useState('');
    const [showHandoverInput, setShowHandoverInput] = useState(false);
    const [mobileView, setMobileView] = useState<'list' | 'thread' | 'profile'>('list');
    const messagesEndRef = useRef<HTMLDivElement>(null);

    function loadConversations() {
        setLoadingList(true);
        axios
            .get('/inbox/conversations', {
                params: { filter, search: search || undefined, page_id: pageId || undefined },
            })
            .then((r) => setConversations(r.data.data ?? r.data))
            .finally(() => setLoadingList(false));
    }

    useEffect(() => {
        loadConversations();
    }, [filter, pageId]);

    useEffect(() => {
        const t = setTimeout(loadConversations, 400);
        return () => clearTimeout(t);
    }, [search]);

    function selectConversation(conv: ConversationSummary) {
        setLoadingConv(true);
        setMobileView('thread');
        axios.get(`/inbox/conversations/${conv.id}`).then((r) => {
            setSelected(r.data);
            setReplyText('');
            if (!conv.is_read) {
                axios.post(`/inbox/conversations/${conv.id}/read`);
                setConversations((prev) => prev.map((c) => (c.id === conv.id ? { ...c, is_read: true } : c)));
            }
        }).finally(() => setLoadingConv(false));
    }

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [selected?.messages]);

    async function sendReply() {
        if (!selected || !replyText.trim()) return;
        setSending(true);
        try {
            await axios.post(`/inbox/conversations/${selected.id}/reply`, { message: replyText });
            const r = await axios.get(`/inbox/conversations/${selected.id}`);
            setSelected(r.data);
            setReplyText('');
            loadConversations();
        } finally {
            setSending(false);
        }
    }

    async function closeConversation() {
        if (!selected) return;
        await axios.post(`/inbox/conversations/${selected.id}/close`);
        setSelected({ ...selected, status: 'closed' });
        loadConversations();
    }

    async function addNote() {
        if (!selected || !newNote.trim()) return;
        setSavingNote(true);
        try {
            const r = await axios.post(`/inbox/conversations/${selected.id}/notes`, { note: newNote });
            setSelected({ ...selected, notes: [...(selected.notes ?? []), r.data] });
            setNewNote('');
        } finally {
            setSavingNote(false);
        }
    }

    async function toggleTag(tagId: number) {
        if (!selected) return;
        const current = selected.tags.map((t) => t.id);
        const next = current.includes(tagId) ? current.filter((id) => id !== tagId) : [...current, tagId];
        await axios.post(`/inbox/conversations/${selected.id}/tags`, { tag_ids: next });
        const updatedTags = tags.filter((t) => next.includes(t.id));
        setSelected({ ...selected, tags: updatedTags });
    }

    async function enableHandover() {
        if (!selected) return;
        await axios.post(`/inbox/conversations/${selected.id}/human-takeover/enable`, {
            reason: handoverReason || undefined,
        });
        const r = await axios.get(`/inbox/conversations/${selected.id}`);
        setSelected(r.data);
        setShowHandoverInput(false);
        setHandoverReason('');
        loadConversations();
    }

    async function disableHandover() {
        if (!selected) return;
        await axios.post(`/inbox/conversations/${selected.id}/human-takeover/disable`);
        const r = await axios.get(`/inbox/conversations/${selected.id}`);
        setSelected(r.data);
        loadConversations();
    }

    async function createTag() {
        if (!newTagName.trim()) return;
        const r = await axios.post('/inbox/tags', { name: newTagName });
        setTags([...tags, r.data]);
        setNewTagName('');
        setShowTagInput(false);
    }

    const lastMsg = (conv: ConversationSummary) => conv.messages?.[0]?.message_text ?? '';

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Inbox" />
            <div className="flex h-[calc(100vh-4rem)] overflow-hidden">
                {/* Left: Conversation List */}
                <div className={`flex w-full flex-col border-r md:w-72 lg:w-80 ${mobileView !== 'list' ? 'hidden md:flex' : 'flex'}`}>
                    {/* Header */}
                    <div className="border-b p-3 space-y-2">
                        <div className="flex items-center gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    className="pl-8 h-9 text-sm"
                                    placeholder="Search conversations..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            {pages.length > 0 && (
                                <Select value={pageId || 'all'} onValueChange={(value) => setPageId(value === 'all' ? '' : value)}>
                                    <SelectTrigger className="w-28 h-9 text-xs">
                                        <SelectValue placeholder="All pages" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All pages</SelectItem>
                                        {pages.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>{p.page_name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </div>
                        <div className="flex gap-1 overflow-x-auto pb-1">
                            {FILTERS.map((f) => (
                                <button
                                    key={f.key}
                                    onClick={() => setFilter(f.key)}
                                    className={`shrink-0 rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                        filter === f.key
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                    }`}
                                >
                                    {f.label}
                                </button>
                            ))}
                        </div>
                    </div>
                    {/* List */}
                    <div className="flex-1 overflow-y-auto">
                        {loadingList ? (
                            <div className="space-y-1 p-2">
                                {[...Array(6)].map((_, i) => (
                                    <div key={i} className="animate-pulse rounded-lg bg-muted h-16" />
                                ))}
                            </div>
                        ) : conversations.length === 0 ? (
                            <p className="p-6 text-center text-sm text-muted-foreground">No conversations found.</p>
                        ) : (
                            conversations.map((conv) => (
                                <button
                                    key={conv.id}
                                    onClick={() => selectConversation(conv)}
                                    className={`w-full text-left px-3 py-3 border-b transition-colors hover:bg-muted/50 ${
                                        selected?.id === conv.id ? 'bg-muted' : ''
                                    } ${!conv.is_read ? 'border-l-2 border-l-primary' : ''}`}
                                >
                                    <div className="flex items-start gap-2">
                                        <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold uppercase text-primary">
                                            {(conv.customer?.name ?? conv.customer?.external_customer_id ?? '?')[0]}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center justify-between gap-1">
                                                <span className={`truncate text-sm ${!conv.is_read ? 'font-semibold' : 'font-medium'}`}>
                                                    {conv.customer?.name ?? conv.customer?.external_customer_id}
                                                </span>
                                                <span className="shrink-0 text-xs text-muted-foreground">
                                                    {timeAgo(conv.last_customer_message_at)}
                                                </span>
                                            </div>
                                            <p className="truncate text-xs text-muted-foreground">{lastMsg(conv)}</p>
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {conv.human_takeover && (
                                                    <Badge variant="destructive" className="text-[10px] px-1 py-0">Human</Badge>
                                                )}
                                                {conv.status === 'closed' && (
                                                    <Badge variant="secondary" className="text-[10px] px-1 py-0">Closed</Badge>
                                                )}
                                                {conv.tags?.slice(0, 2).map((t) => (
                                                    <span key={t.id} className="rounded-full px-1.5 py-0.5 text-[10px] text-white" style={{ backgroundColor: t.color }}>{t.name}</span>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            ))
                        )}
                    </div>
                </div>

                {/* Middle: Message Thread */}
                <div className={`flex flex-1 flex-col ${mobileView === 'thread' ? 'flex' : 'hidden md:flex'}`}>
                    {!selected ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2 text-muted-foreground">
                            <MessageSquare className="h-10 w-10 opacity-30" />
                            <p className="text-sm">Select a conversation to start</p>
                        </div>
                    ) : loadingConv ? (
                        <div className="flex flex-1 items-center justify-center">
                            <div className="animate-spin h-6 w-6 rounded-full border-2 border-primary border-t-transparent" />
                        </div>
                    ) : (
                        <>
                            {/* Thread header */}
                            <div className="flex items-center justify-between border-b px-4 py-2">
                                <div className="flex items-center gap-2">
                                    <button className="md:hidden mr-1 text-muted-foreground" onClick={() => setMobileView('list')}>
                                        ←
                                    </button>
                                    <div>
                                        <p className="text-sm font-semibold">
                                            {selected.customer?.name ?? selected.customer?.external_customer_id}
                                        </p>
                                        <p className="text-xs text-muted-foreground">{selected.facebook_page?.page_name}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    {selected.human_takeover && (
                                        <Badge variant="destructive" className="flex items-center gap-1 text-xs">
                                            <AlertTriangle className="h-3 w-3" />Automation Paused
                                        </Badge>
                                    )}
                                    <Badge variant={selected.status === 'closed' ? 'secondary' : 'outline'} className="text-xs">
                                        {selected.status}
                                    </Badge>
                                    {selected.status !== 'closed' && (
                                        selected.human_takeover ? (
                                            <Button size="sm" variant="outline" className="h-7 text-xs text-green-600 border-green-300" onClick={disableHandover}>
                                                <Play className="mr-1 h-3 w-3" />Resume Automation
                                            </Button>
                                        ) : (
                                            <Button size="sm" variant="outline" className="h-7 text-xs" onClick={() => setShowHandoverInput(!showHandoverInput)}>
                                                <UserX className="mr-1 h-3 w-3" />Take Over
                                            </Button>
                                        )
                                    )}
                                    {selected.status !== 'closed' && (
                                        <Button size="sm" variant="outline" className="h-7 text-xs" onClick={closeConversation}>
                                            <CheckCheck className="mr-1 h-3 w-3" />Close
                                        </Button>
                                    )}
                                    <button className="lg:hidden text-muted-foreground text-sm" onClick={() => setMobileView('profile')}>
                                        <User className="h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            {/* Handover reason input */}
                            {showHandoverInput && (
                                <div className="border-b bg-orange-50 dark:bg-orange-950/30 px-4 py-2 flex items-center gap-2">
                                    <Input
                                        className="h-8 text-xs flex-1"
                                        placeholder="Reason for taking over (optional)"
                                        value={handoverReason}
                                        onChange={(e) => setHandoverReason(e.target.value)}
                                        onKeyDown={(e) => { if (e.key === 'Enter') enableHandover(); }}
                                    />
                                    <Button size="sm" className="h-8 text-xs" onClick={enableHandover}>Confirm</Button>
                                    <button onClick={() => setShowHandoverInput(false)}><X className="h-4 w-4 text-muted-foreground" /></button>
                                </div>
                            )}

                            {/* Handover info banner */}
                            {selected.human_takeover && (selected as any).human_takeover_reason && (
                                <div className="border-b bg-red-50 dark:bg-red-950/30 px-4 py-2 text-xs text-red-700 dark:text-red-300">
                                    <AlertTriangle className="inline mr-1 h-3 w-3" />
                                    Reason: <strong>{(selected as any).human_takeover_reason}</strong>
                                    {(selected as any).human_takeover_at && (
                                        <span className="ml-2 text-muted-foreground">· {timeAgo((selected as any).human_takeover_at)}</span>
                                    )}
                                </div>
                            )}

                            {/* Messages */}
                            <div className="flex-1 overflow-y-auto p-4 space-y-3">
                                {selected.messages?.map((msg) => (
                                    <div key={msg.id} className={`flex ${msg.direction === 'outgoing' ? 'justify-end' : 'justify-start'}`}>
                                        <div className={`max-w-[75%] rounded-2xl px-4 py-2 text-sm ${
                                            msg.direction === 'outgoing'
                                                ? 'bg-primary text-primary-foreground rounded-tr-sm'
                                                : 'bg-muted rounded-tl-sm'
                                        }`}>
                                            <p className="whitespace-pre-wrap break-words">{msg.message_text}</p>
                                            <div className={`mt-1 flex items-center gap-1 text-[10px] ${msg.direction === 'outgoing' ? 'text-primary-foreground/70 justify-end' : 'text-muted-foreground'}`}>
                                                {msg.sender_type === 'agent' ? 'You' : msg.sender_type === 'bot' ? 'Bot' : ''}
                                                {msg.status === 'failed' && <span className="text-red-400">Failed</span>}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                                <div ref={messagesEndRef} />
                            </div>

                            {/* Reply box */}
                            {selected.status !== 'closed' && (
                                <div className="border-t p-3">
                                    <div className="flex gap-2">
                                        <textarea
                                            className="flex-1 resize-none rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-primary"
                                            rows={2}
                                            placeholder="Type a message..."
                                            value={replyText}
                                            onChange={(e) => setReplyText(e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter' && !e.shiftKey) {
                                                    e.preventDefault();
                                                    sendReply();
                                                }
                                            }}
                                        />
                                        <Button size="sm" disabled={sending || !replyText.trim()} onClick={sendReply} className="self-end">
                                            <Send className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <p className="mt-1 text-[10px] text-muted-foreground">Press Enter to send, Shift+Enter for new line</p>
                                </div>
                            )}
                        </>
                    )}
                </div>

                {/* Right: Customer Profile + Notes + Tags */}
                <div className={`flex w-72 flex-col border-l ${mobileView === 'profile' ? 'flex w-full' : 'hidden lg:flex'}`}>
                    {selected ? (
                        <div className="flex-1 overflow-y-auto">
                            {/* Mobile back */}
                            <button className="lg:hidden flex items-center gap-1 p-3 text-sm text-muted-foreground" onClick={() => setMobileView('thread')}>
                                ← Back
                            </button>

                            {/* Customer info */}
                            <div className="border-b p-4">
                                <div className="flex items-center gap-3 mb-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold uppercase text-primary">
                                        {(selected.customer?.name ?? selected.customer?.external_customer_id ?? '?')[0]}
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold">{selected.customer?.name ?? 'Unknown'}</p>
                                        <p className="text-xs text-muted-foreground">{selected.customer?.external_customer_id}</p>
                                    </div>
                                </div>
                                {selected.customer?.phone && (
                                    <p className="text-xs text-muted-foreground">📞 {selected.customer.phone}</p>
                                )}
                                {selected.customer?.email && (
                                    <p className="text-xs text-muted-foreground">✉️ {selected.customer.email}</p>
                                )}
                            </div>

                            {/* Tags */}
                            <div className="border-b p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Tags</p>
                                    <button onClick={() => setShowTagInput(!showTagInput)} className="text-muted-foreground hover:text-foreground">
                                        <Tag className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <div className="flex flex-wrap gap-1">
                                    {tags.map((tag) => {
                                        const active = selected.tags.some((t) => t.id === tag.id);
                                        return (
                                            <button
                                                key={tag.id}
                                                onClick={() => toggleTag(tag.id)}
                                                className={`rounded-full px-2 py-0.5 text-xs transition-opacity ${active ? 'opacity-100' : 'opacity-40 hover:opacity-70'}`}
                                                style={{ backgroundColor: tag.color, color: '#fff' }}
                                            >
                                                {tag.name}
                                            </button>
                                        );
                                    })}
                                </div>
                                {showTagInput && (
                                    <div className="mt-2 flex gap-1">
                                        <Input
                                            className="h-7 text-xs"
                                            placeholder="New tag name"
                                            value={newTagName}
                                            onChange={(e) => setNewTagName(e.target.value)}
                                            onKeyDown={(e) => { if (e.key === 'Enter') createTag(); }}
                                        />
                                        <Button size="sm" className="h-7 px-2 text-xs" onClick={createTag}>Add</Button>
                                        <button onClick={() => setShowTagInput(false)} className="text-muted-foreground">
                                            <X className="h-4 w-4" />
                                        </button>
                                    </div>
                                )}
                            </div>

                            {/* Internal Notes */}
                            <div className="p-4">
                                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Internal Notes</p>
                                <div className="space-y-2 mb-3">
                                    {selected.notes?.length === 0 && (
                                        <p className="text-xs text-muted-foreground">No notes yet.</p>
                                    )}
                                    {selected.notes?.map((note) => (
                                        <div key={note.id} className="rounded-lg bg-yellow-50 dark:bg-yellow-950/30 p-2 text-xs">
                                            <p className="whitespace-pre-wrap">{note.note}</p>
                                            <p className="mt-1 text-muted-foreground">
                                                {note.user?.name} · <Clock className="inline h-2.5 w-2.5" /> {timeAgo(note.created_at)}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                                <textarea
                                    className="w-full resize-none rounded-lg border bg-background px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-primary"
                                    rows={2}
                                    placeholder="Add a note (internal only)..."
                                    value={newNote}
                                    onChange={(e) => setNewNote(e.target.value)}
                                />
                                <Button size="sm" className="mt-1 h-7 w-full text-xs" disabled={savingNote || !newNote.trim()} onClick={addNote}>
                                    Save Note
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <div className="flex flex-1 items-center justify-center p-4 text-center text-xs text-muted-foreground">
                            Select a conversation to see customer details
                        </div>
                    )}
                </div>
            </div>
        </ClientDashboardLayout>
    );
}
