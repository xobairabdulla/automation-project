import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import { useState } from 'react';

interface CommentReply {
    id: number;
    reply_text: string;
    status: string;
}

interface Comment {
    id: number;
    commenter_name: string;
    comment_text: string;
    status: string;
    created_at: string;
    facebook_page: { id: number; page_name: string } | null;
    facebook_post: { id: number; external_post_id: string } | null;
    replies: CommentReply[];
}

interface Page {
    id: number;
    name: string;
}

interface Paginated {
    data: Comment[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Filters {
    status?: string;
    page_id?: string;
    search?: string;
}

interface Props {
    comments: Paginated;
    pages: Page[];
    filters: Filters;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Comments', href: '/comments' }];

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    replied: 'bg-green-100 text-green-800',
    ignored: 'bg-gray-100 text-gray-600',
    failed: 'bg-red-100 text-red-800',
};

export default function Comments({ comments, pages, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [pageId, setPageId] = useState(filters.page_id ?? '');

    function applyFilters() {
        router.get('/comments', {
            search: search || undefined,
            status: status || undefined,
            page_id: pageId || undefined,
        });
    }

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Comments" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Search</label>
                        <Input
                            placeholder="Comment text..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-48"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Status</label>
                        <select value={status} onChange={(e) => setStatus(e.target.value)} className="rounded border px-2 py-1.5 text-sm">
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="replied">Replied</option>
                            <option value="ignored">Ignored</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Page</label>
                        <select value={pageId} onChange={(e) => setPageId(e.target.value)} className="rounded border px-2 py-1.5 text-sm">
                            <option value="">All pages</option>
                            {pages.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button size="sm" onClick={applyFilters}>
                        Filter
                    </Button>
                </div>

                <p className="text-sm text-muted-foreground">{comments.total} total comments</p>

                {comments.data.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
                        <MessageSquare className="mb-3 size-10 opacity-30" />
                        <p>No comments found.</p>
                    </div>
                )}

                <div className="space-y-3">
                    {comments.data.map((comment) => (
                        <Card key={comment.id}>
                            <CardContent className="py-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex-1 space-y-1">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{comment.commenter_name}</span>
                                            <span
                                                className={`rounded px-1.5 py-0.5 text-xs font-medium ${statusColors[comment.status] ?? 'bg-muted text-muted-foreground'}`}
                                            >
                                                {comment.status}
                                            </span>
                                            {comment.facebook_page && (
                                                <Badge variant="outline" className="text-xs">
                                                    {comment.facebook_page.page_name}
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-sm">{comment.comment_text}</p>
                                        {comment.replies.length > 0 && (
                                            <div className="mt-2 space-y-1 border-l-2 pl-3">
                                                {comment.replies.map((r) => (
                                                    <p key={r.id} className="text-xs text-muted-foreground">
                                                        ↳ {r.reply_text}
                                                    </p>
                                                ))}
                                            </div>
                                        )}
                                        <p className="text-xs text-muted-foreground">{new Date(comment.created_at).toLocaleString()}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {comments.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {comments.current_page > 1 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.get('/comments', { ...filters, page: comments.current_page - 1 })}
                            >
                                Previous
                            </Button>
                        )}
                        <span className="flex items-center text-sm">
                            {comments.current_page} / {comments.last_page}
                        </span>
                        {comments.current_page < comments.last_page && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.get('/comments', { ...filters, page: comments.current_page + 1 })}
                            >
                                Next
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </ClientDashboardLayout>
    );
}
