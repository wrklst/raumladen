<?php

namespace App\Invoicing;

use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;

class InvoicePdf
{
    public function render(Invoice $invoice): string
    {
        $previous = app()->getLocale();
        app()->setLocale($invoice->snapshot['locale'] ?? $previous);

        try {
            $html = view('invoices.pdf', ['invoice' => $invoice, 's' => $invoice->snapshot])->render();
        } finally {
            app()->setLocale($previous);
        }

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
