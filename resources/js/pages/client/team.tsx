import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Mail, Trash2, UserMinus, Users } from 'lucide-react';
import { useState } from 'react';

interface Role {
    slug: string;
    name: string;
}

interface Member {
    id: number;
    name: string;
    email: string;
    status: string;
    roles: Role[];
    created_at: string;
}

interface Invitation {
    id: number;
    email: string;
    role: string;
    expires_at: string;
}

interface TeamProps {
    members: Member[];
    invitations: Invitation[];
    memberLimit: number | null;
    memberCount: number;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Team', href: '/team' }];

function roleLabel(role: string): string {
    const labels: Record<string, string> = { agent: 'Agent', 'support-admin': 'Support Admin' };
    return labels[role] ?? role;
}

export default function Team({ members, invitations, memberLimit, memberCount }: TeamProps) {
    const [inviteOpen, setInviteOpen] = useState(false);

    const inviteForm = useForm({ email: '', role: 'agent' });

    function submitInvite(e: React.FormEvent) {
        e.preventDefault();
        inviteForm.post('/team/invite', {
            onSuccess: () => {
                setInviteOpen(false);
                inviteForm.reset();
            },
        });
    }

    function removeMember(id: number) {
        if (confirm('Remove this team member? They will lose access immediately.')) {
            router.delete(`/team/members/${id}`);
        }
    }

    function revokeInvitation(id: number) {
        if (confirm('Revoke this invitation?')) {
            router.delete(`/team/invitations/${id}`);
        }
    }

    const atLimit = memberLimit !== null && memberCount >= memberLimit;

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Team" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Team</h1>
                        <p className="text-muted-foreground text-sm">
                            Manage agents and support staff.{' '}
                            {memberLimit !== null && (
                                <span>
                                    {memberCount}/{memberLimit} members used.
                                </span>
                            )}
                        </p>
                    </div>
                    <Dialog open={inviteOpen} onOpenChange={setInviteOpen}>
                        <DialogTrigger asChild>
                            <Button disabled={atLimit} size="sm">
                                <Mail className="mr-2 h-4 w-4" />
                                Invite Member
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Invite Team Member</DialogTitle>
                            </DialogHeader>
                            <form onSubmit={submitInvite} className="space-y-4">
                                <div>
                                    <Label>Email address</Label>
                                    <Input
                                        type="email"
                                        value={inviteForm.data.email}
                                        onChange={(e) => inviteForm.setData('email', e.target.value)}
                                        placeholder="agent@example.com"
                                    />
                                    {inviteForm.errors.email && (
                                        <p className="mt-1 text-xs text-red-500">{inviteForm.errors.email}</p>
                                    )}
                                </div>
                                <div>
                                    <Label>Role</Label>
                                    <Select value={inviteForm.data.role} onValueChange={(v) => inviteForm.setData('role', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="agent">Agent</SelectItem>
                                            <SelectItem value="support-admin">Support Admin</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    They will receive an email with a link to accept the invitation.
                                </p>
                                <Button type="submit" disabled={inviteForm.processing} className="w-full">
                                    Send Invitation
                                </Button>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                {/* Active members */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="h-5 w-5" />
                            Team Members ({members.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-4 py-2 text-left font-medium">Name</th>
                                    <th className="px-4 py-2 text-left font-medium">Email</th>
                                    <th className="px-4 py-2 text-left font-medium">Role</th>
                                    <th className="px-4 py-2 text-left font-medium">Status</th>
                                    <th className="px-4 py-2 text-left font-medium">Joined</th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {members.length === 0 ? (
                                    <tr>
                                        <td className="text-muted-foreground px-4 py-6 text-center" colSpan={6}>
                                            No team members yet. Invite someone to get started.
                                        </td>
                                    </tr>
                                ) : (
                                    members.map((m) => (
                                        <tr key={m.id} className="border-b last:border-0">
                                            <td className="px-4 py-2 font-medium">{m.name}</td>
                                            <td className="px-4 py-2 text-muted-foreground">{m.email}</td>
                                            <td className="px-4 py-2">
                                                {m.roles.map((r) => (
                                                    <Badge key={r.slug} variant="outline" className="text-xs">
                                                        {r.name}
                                                    </Badge>
                                                ))}
                                            </td>
                                            <td className="px-4 py-2">
                                                <Badge variant={m.status === 'active' ? 'default' : 'secondary'}>{m.status}</Badge>
                                            </td>
                                            <td className="px-4 py-2 text-muted-foreground">
                                                {new Date(m.created_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-4 py-2">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => removeMember(m.id)}
                                                >
                                                    <UserMinus className="h-4 w-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                {/* Pending invitations */}
                {invitations.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Mail className="h-5 w-5" />
                                Pending Invitations ({invitations.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-medium">Email</th>
                                        <th className="px-4 py-2 text-left font-medium">Role</th>
                                        <th className="px-4 py-2 text-left font-medium">Expires</th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {invitations.map((inv) => (
                                        <tr key={inv.id} className="border-b last:border-0">
                                            <td className="px-4 py-2">{inv.email}</td>
                                            <td className="px-4 py-2">
                                                <Badge variant="secondary" className="text-xs">
                                                    {roleLabel(inv.role)}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-2 text-muted-foreground">
                                                {new Date(inv.expires_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-4 py-2">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => revokeInvitation(inv.id)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </ClientDashboardLayout>
    );
}
