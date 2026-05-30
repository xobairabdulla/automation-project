<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $expiresInMinutes = 5,
    ) {}

    public function build(): static
    {
        return $this
            ->subject('Your verification code')
            ->view('emails.otp')
            ->with([
                'code' => $this->code,
                'expires' => $this->expiresInMinutes,
            ]);
    }
}
