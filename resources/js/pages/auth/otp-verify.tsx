import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Mail } from 'lucide-react';
import { useRef } from 'react';

interface SharedProps {
    flash?: { status?: string };
}

export default function OtpVerify() {
    const { flash } = usePage<SharedProps>().props;
    const inputRef = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, errors } = useForm({ code: '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('login.otp.verify'));
    }

    function resend() {
        router.post(route('login.otp.resend'));
    }

    return (
        <AuthLayout
            title="Check your email"
            description="We sent a 6-digit code to your email address. Enter it below."
        >
            <Head title="Verify code" />

            {flash?.status && (
                <div className="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                    {flash.status}
                </div>
            )}

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-2">
                    <Label htmlFor="code">
                        <span className="flex items-center gap-1.5">
                            <Mail size={14} />
                            Verification code
                        </span>
                    </Label>
                    <Input
                        id="code"
                        ref={inputRef}
                        type="text"
                        inputMode="numeric"
                        pattern="[0-9]{6}"
                        maxLength={6}
                        autoFocus
                        autoComplete="one-time-code"
                        placeholder="000000"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                        className="text-center text-2xl tracking-[0.5em] font-mono h-14"
                    />
                    <InputError message={errors.code} />
                </div>

                <Button type="submit" className="w-full" disabled={processing || data.code.length < 6}>
                    {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                    Verify & Sign in
                </Button>
            </form>

            <div className="mt-4 text-center text-sm text-muted-foreground">
                Didn't receive the code?{' '}
                <button
                    type="button"
                    onClick={resend}
                    className="text-primary underline-offset-4 hover:underline font-medium"
                >
                    Resend
                </button>
                {' · '}
                <a href={route('login')} className="text-primary underline-offset-4 hover:underline font-medium">
                    Back to login
                </a>
            </div>
        </AuthLayout>
    );
}
