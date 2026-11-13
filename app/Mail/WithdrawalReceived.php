<?php

namespace App\Mail;

use App\Models\Withdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Acknowledgement of receipt on a durable medium: the declaration as it was
 * made, with date and time.
 */
class WithdrawalReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Withdrawal $withdrawal) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your withdrawal for order :number has been received', ['number' => $this->withdrawal->order_number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.withdrawal-received',
            with: ['withdrawal' => $this->withdrawal],
        );
    }
}
