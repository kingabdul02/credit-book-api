<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Contact;
use App\Models\Debt;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;
use League\Csv\Writer;
use SplTempFileObject;

class ExportService
{
    /**
     * Export all debts for a user as CSV.
     */
    public function exportCsv(string $userId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $debts = $this->getExportData($userId);

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->insertOne([
            'Contact',
            'Type',
            'Original Amount',
            'Paid Amount',
            'Outstanding',
            'Currency',
            'Status',
            'Category',
            'Due Date',
            'Created At',
            'Note',
        ]);

        foreach ($debts as $debt) {
            $csv->insertOne([
                $debt->contact?->full_name ?? 'Unknown',
                $debt->transaction_type->label(),
                $debt->original_amount,
                $debt->paid_amount,
                $debt->outstanding_amount,
                $debt->currency,
                $debt->status->label(),
                $debt->category ?? '',
                $debt->due_date?->format('Y-m-d') ?? '',
                $debt->created_at->format('Y-m-d H:i:s'),
                $debt->note ?? '',
            ]);
        }

        return Response::streamDownload(function () use ($csv) {
            echo $csv->toString();
        }, 'creditbook-export-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export all debts for a user as PDF.
     */
    public function exportPdf(string $userId): \Illuminate\Http\Response
    {
        $debts = $this->getExportData($userId);
        $user = \App\Models\User::find($userId);

        $totalOwed = $debts->where('transaction_type', TransactionType::THEY_OWE_ME)
            ->sum(fn ($d) => (float) $d->outstanding_amount);
        $totalIOwe = $debts->where('transaction_type', TransactionType::I_OWE_THEM)
            ->sum(fn ($d) => (float) $d->outstanding_amount);

        $pdf = Pdf::loadView('exports.debts', [
            'debts' => $debts,
            'user' => $user,
            'totalOwed' => number_format($totalOwed, 2),
            'totalIOwe' => number_format($totalIOwe, 2),
            'netBalance' => number_format($totalOwed - $totalIOwe, 2),
            'generatedAt' => now()->format('F j, Y g:i A'),
        ]);

        return $pdf->download('creditbook-statement-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export a single contact's debt history as CSV.
     */
    public function exportContactCsv(string $userId, string $contactId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $contact = Contact::forUser($userId)->findOrFail($contactId);
        $transactions = $this->getContactTransactions($userId, $contactId);

        $csv = Writer::createFromFileObject(new SplTempFileObject());
        $csv->insertOne([
            'Date',
            'Type',
            'Amount',
            'Note',
            'Debt Direction',
        ]);

        foreach ($transactions as $txn) {
            $csv->insertOne([
                $txn->created_at->format('Y-m-d H:i:s'),
                $txn->type->label(),
                $txn->amount,
                $txn->note ?? '',
                $txn->debt?->transaction_type->label() ?? '',
            ]);
        }

        $filename = str_replace(' ', '-', strtolower($contact->full_name));

        return Response::streamDownload(function () use ($csv) {
            echo $csv->toString();
        }, "creditbook-{$filename}-" . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export a single contact's statement as PDF.
     */
    public function exportContactPdf(string $userId, string $contactId): \Illuminate\Http\Response
    {
        $contact = Contact::forUser($userId)->with('debts')->findOrFail($contactId);
        $transactions = $this->getContactTransactions($userId, $contactId);
        $user = \App\Models\User::find($userId);

        $pdf = Pdf::loadView('exports.contact-statement', [
            'contact' => $contact,
            'transactions' => $transactions,
            'user' => $user,
            'totalOwedToUser' => $contact->total_owed_to_user,
            'totalUserOwes' => $contact->total_user_owes,
            'generatedAt' => now()->format('F j, Y g:i A'),
        ]);

        $filename = str_replace(' ', '-', strtolower($contact->full_name));

        return $pdf->download("creditbook-statement-{$filename}-" . now()->format('Y-m-d') . '.pdf');
    }

    private function getExportData(string $userId)
    {
        return Debt::forUser($userId)
            ->with('contact:id,full_name')
            ->orderByDesc('created_at')
            ->get();
    }

    private function getContactTransactions(string $userId, string $contactId)
    {
        $debtIds = Debt::forUser($userId)
            ->where('contact_id', $contactId)
            ->pluck('id');

        return Transaction::whereIn('debt_id', $debtIds)
            ->with('debt:id,transaction_type')
            ->orderByDesc('created_at')
            ->get();
    }
}
