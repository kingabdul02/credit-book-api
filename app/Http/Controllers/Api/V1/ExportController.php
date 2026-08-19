<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exportService,
    ) {}

    /**
     * Export all debts as CSV.
     */
    public function csv(Request $request)
    {
        return $this->exportService->exportCsv($request->user()->id);
    }

    /**
     * Export all debts as PDF statement.
     */
    public function pdf(Request $request)
    {
        return $this->exportService->exportPdf($request->user()->id);
    }

    /**
     * Export a single contact's history as CSV.
     */
    public function contactCsv(Request $request, string $contactId)
    {
        return $this->exportService->exportContactCsv($request->user()->id, $contactId);
    }

    /**
     * Export a single contact's statement as PDF.
     */
    public function contactPdf(Request $request, string $contactId)
    {
        return $this->exportService->exportContactPdf($request->user()->id, $contactId);
    }
}
