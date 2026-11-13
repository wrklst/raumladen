<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Order confirmation :number', ['number' => $this->order->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-confirmation',
            with: ['order' => $this->order->load('items')],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->invoice->pdf_path === null) {
            return [];
        }

        return [
            Attachment::fromStorageDisk(config('shop.invoice.disk'), $this->invoice->pdf_path)
                ->as($this->invoice->number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
