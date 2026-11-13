<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your login code: :code', ['code' => $this->code]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.login-code',
            with: ['code' => $this->code, 'minutes' => config('shop.login_code.ttl_minutes')],
        );
    }
}
