import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/react';

interface TeamAcceptProps {
    invitation: {
        email: string;
        role: string;
        expires_at: string;
    };
    invitedBy: {
        name: string;
    };
}

export default function TeamAccept({ invitation, invitedBy }: TeamAcceptProps) {
    const form = useForm({
        name: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(window.location.pathname);
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-muted/40 p-4">
            <Head title="Accept Team Invitation" />
            <div className="w-full max-w-sm rounded-lg border bg-background p-6 shadow-sm">
                <div className="mb-6 text-center">
                    <h1 className="text-xl font-semibold">Team Invitation</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        <span className="font-medium">{invitedBy.name}</span> invited you as{' '}
                        <span className="capitalize">{invitation.role.replace('-', ' ')}</span>.
                    </p>
                    <p className="text-muted-foreground mt-1 text-xs">Joining as: {invitation.email}</p>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label>Your name</Label>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Full name"
                            autoFocus
                        />
                        {form.errors.name && <p className="mt-1 text-xs text-red-500">{form.errors.name}</p>}
                    </div>
                    <div>
                        <Label>Password</Label>
                        <Input
                            type="password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            placeholder="Create a password"
                        />
                        {form.errors.password && <p className="mt-1 text-xs text-red-500">{form.errors.password}</p>}
                    </div>
                    <div>
                        <Label>Confirm password</Label>
                        <Input
                            type="password"
                            value={form.data.password_confirmation}
                            onChange={(e) => form.setData('password_confirmation', e.target.value)}
                            placeholder="Repeat password"
                        />
                    </div>
                    {form.errors.accept && <p className="text-sm text-red-500">{form.errors.accept}</p>}
                    <Button type="submit" disabled={form.processing} className="w-full">
                        Accept & Join Team
                    </Button>
                </form>
            </div>
        </div>
    );
}
