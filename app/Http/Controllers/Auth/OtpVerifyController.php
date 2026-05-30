<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OtpVerifyController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('otp_pending_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/otp-verify');
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('otp_pending_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = User::find($userId);

        if (! $user) {
            $request->session()->forget(['otp_pending_user_id', 'otp_pending_remember']);

            return redirect()->route('login')->withErrors(['code' => 'Session expired. Please log in again.']);
        }

        $result = $this->otpService->verify($user->email, $request->string('code'));

        if ($result !== true) {
            return back()->withErrors(['code' => $result]);
        }

        // Complete login
        $remember = $request->session()->pull('otp_pending_remember', false);
        $request->session()->forget('otp_pending_user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->loginLogs()->create([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'logged_in_at' => now(),
        ]);

        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->hasRole('super-admin')) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('otp_pending_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('login');
        }

        $error = $this->otpService->send($user->email);

        if ($error) {
            return back()->withErrors(['code' => $error]);
        }

        return back()->with('status', 'A new code has been sent to your email.');
    }
}
