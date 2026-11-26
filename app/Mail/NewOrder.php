<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the people named in the shop settings that an order has been placed.
 */
class NewOrder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('New order :number', ['number' => $this->order->number]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.new-order', with: ['order' => $this->order->load('items')]);
    }
}
