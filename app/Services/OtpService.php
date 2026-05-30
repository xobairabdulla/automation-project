<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\Otp;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    private const OTP_TTL_MINUTES = 5;

    private const MAX_ATTEMPTS = 5;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const BLOCK_MINUTES = 30;

    /**
     * Generate and send OTP to an email address.
     * Returns error string or null on success.
     */
    public function send(string $email): ?string
    {
        $record = Otp::where('mobile', $email)->latest('id')->first();

        if ($record && $record->isBlocked()) {
            $seconds = now()->diffInSeconds($record->blocked_until, false);

            return "Too many attempts. Try again in {$seconds} seconds.";
        }

        // Resend cooldown
        if ($record && $record->last_sent_at && $record->last_sent_at->diffInSeconds(now()) < self::RESEND_COOLDOWN_SECONDS) {
            $wait = self::RESEND_COOLDOWN_SECONDS - $record->last_sent_at->diffInSeconds(now());

            return "Please wait {$wait} seconds before requesting a new code.";
        }

        $code = $this->generateCode();

        Otp::where('mobile', $email)->delete();

        Otp::create([
            'mobile' => $email,
            'otp' => $code,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'last_sent_at' => now(),
            'attempts' => 0,
            'blocked_until' => null,
        ]);

        Mail::to($email)->send(new OtpMail($code, self::OTP_TTL_MINUTES));

        return null;
    }

    /**
     * Verify OTP for an email address.
     * Returns true on success, error string on failure.
     */
    public function verify(string $email, string $inputCode): true|string
    {
        $record = Otp::where('mobile', $email)->latest('id')->first();

        if (! $record) {
            return 'No verification code found. Please request a new one.';
        }

        if ($record->isBlocked()) {
            $seconds = now()->diffInSeconds($record->blocked_until, false);

            return "Account temporarily blocked. Try again in {$seconds} seconds.";
        }

        if ($record->isExpired()) {
            return 'Verification code expired. Please request a new one.';
        }

        if (! $record->isValid($inputCode)) {
            $record->increment('attempts');
            $remaining = self::MAX_ATTEMPTS - $record->attempts;

            if ($remaining <= 0) {
                $record->update(['blocked_until' => now()->addMinutes(self::BLOCK_MINUTES)]);

                return 'Too many incorrect attempts. Blocked for '.self::BLOCK_MINUTES.' minutes.';
            }

            return "Incorrect code. {$remaining} attempts remaining.";
        }

        // Success — clean up
        $record->delete();

        return true;
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
}
