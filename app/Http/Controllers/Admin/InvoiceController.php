<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        $invoices = Invoice::query()
            ->with('order:id,uuid,number')
            ->when($search !== '', fn ($query) => $query->where('number', 'like', "%{$search}%"))
            ->latest('issued_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'type' => $invoice->type->value,
                'issued_at' => $invoice->issued_at->toDateString(),
                'buyer' => $invoice->snapshot['buyer']['company'] ?: $invoice->snapshot['buyer']['name'],
                'country' => $invoice->snapshot['buyer']['country'],
                'tax_treatment' => $invoice->snapshot['tax_treatment'],
                'total_net_cents' => $invoice->total_net_cents,
                'total_tax_cents' => $invoice->total_tax_cents,
                'total_gross_cents' => $invoice->total_gross_cents,
                'order_uuid' => $invoice->order->uuid,
                'order_number' => $invoice->order->number,
                'exported' => $invoice->datev_export_id !== null,
            ]);

        return Inertia::render('admin/invoices/index', [
            'invoices' => $invoices,
            'filters' => ['q' => $search],
        ]);
    }

    public function download(Invoice $invoice): StreamedResponse
    {
        abort_if($invoice->pdf_path === null, 404);

        return Storage::disk(config('shop.invoice.disk'))->download($invoice->pdf_path, $invoice->number.'.pdf');
    }
}
