<?php

namespace App\Http\Controllers\Admin;

use App\Datev\DatevExporter;
use App\Http\Controllers\Controller;
use App\Models\DatevExport;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatevExportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/datev/index', [
            'exports' => DatevExport::query()->with('user:id,name')->latest('id')->limit(50)->get()->map(fn (DatevExport $export) => [
                'id' => $export->id,
                'from_date' => $export->from_date->toDateString(),
                'to_date' => $export->to_date->toDateString(),
                'invoice_count' => $export->invoice_count,
                'booking_count' => $export->booking_count,
                'sha256' => $export->sha256,
                'user' => $export->user?->name,
                'created_at' => $export->created_at?->toDateTimeString(),
            ]),
            'unexported_count' => Invoice::query()->whereNull('datev_export_id')->count(),
            'defaults' => [
                'from' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'to' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
        ]);
    }

    public function store(Request $request, DatevExporter $exporter): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        try {
            $export = $exporter->export(now()->parse($data['from']), now()->parse($data['to']), $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['from' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Exported :bookings bookings from :documents documents.', ['bookings' => $export->booking_count, 'documents' => $export->invoice_count])]);

        return to_route('admin.datev.index');
    }

    public function download(DatevExport $datevExport): StreamedResponse
    {
        return Storage::disk('local')->download($datevExport->path, basename($datevExport->path), [
            'Content-Type' => 'text/csv; charset=Windows-1252',
        ]);
    }
}
