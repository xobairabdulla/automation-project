<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        // Validate credentials without fully logging in
        $request->authenticateForOtp();

        $user = $request->resolvedUser();

        // Store user ID in session for OTP step
        $request->session()->put('otp_pending_user_id', $user->id);
        $request->session()->put('otp_pending_remember', $request->boolean('remember'));

        $error = $this->otpService->send($user->email);

        if ($error) {
            $request->session()->forget(['otp_pending_user_id', 'otp_pending_remember']);

            return back()->withErrors(['email' => $error]);
        }

        return redirect()->route('login.otp');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
