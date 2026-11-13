<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Page;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation of receipt, sent the moment an order is placed (§ 312i BGB).
 * It carries the contract on a durable medium: order, terms and withdrawal
 * information (§ 312f BGB). The invoice follows with the payment.
 */
class OrderReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public bool $payByBankTransfer = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('We have received your order :number', ['number' => $this->order->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-received',
            with: [
                'order' => $this->order->load('items'),
                'bank' => $this->payByBankTransfer ? config('shop.seller') : null,
                'legal' => Page::query()->where('is_published', true)->whereIn('slug', ['terms', 'withdrawal'])->orderBy('id')->get(),
            ],
        );
    }
}
