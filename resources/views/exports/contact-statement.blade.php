<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contact Statement — {{ $contact->full_name }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #1a56db;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #1a56db;
            margin: 0;
            font-size: 22px;
        }
        .header h2 {
            color: #444;
            margin: 5px 0 0;
            font-size: 16px;
            font-weight: normal;
        }
        .header p {
            color: #666;
            margin: 5px 0 0;
            font-size: 10px;
        }
        .summary {
            margin-bottom: 25px;
        }
        .summary table {
            width: 100%;
            border: none;
        }
        .summary td {
            border: none;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
        }
        table.transactions {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table.transactions th {
            background: #1a56db;
            color: white;
            padding: 8px 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        table.transactions td {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
            font-size: 11px;
        }
        table.transactions tr:nth-child(even) { background: #f8f9fa; }
        .type-initial { color: #1a56db; }
        .type-payment { color: #10b981; }
        .type-additional { color: #f59e0b; }
        .type-adjustment { color: #8b5cf6; }
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #999;
            font-size: 10px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CreditBook</h1>
        <h2>Statement for {{ $contact->full_name }}</h2>
        @if($contact->phone_number)
            <p>Phone: {{ $contact->phone_number }}</p>
        @endif
        <p>Generated: {{ $generatedAt }}</p>
    </div>

    <div class="summary">
        <table>
            <tr>
                <td style="background: #d1fae5;">
                    <div style="font-size: 10px; text-transform: uppercase; color: #666;">They Owe Me</div>
                    <div style="font-size: 18px; font-weight: bold; color: #10b981;">₦{{ number_format((float)$totalOwedToUser, 2) }}</div>
                </td>
                <td style="width: 10px; background: transparent;"></td>
                <td style="background: #fee2e2;">
                    <div style="font-size: 10px; text-transform: uppercase; color: #666;">I Owe Them</div>
                    <div style="font-size: 18px; font-weight: bold; color: #ef4444;">₦{{ number_format((float)$totalUserOwes, 2) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <h3 style="color: #1a56db;">Transaction History</h3>
    <table class="transactions">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Direction</th>
                <th>Amount</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $txn)
                <tr>
                    <td>{{ $txn->created_at->format('M d, Y H:i') }}</td>
                    <td>
                        <span class="type-{{ strtolower($txn->type->value) }}">
                            {{ $txn->type->label() }}
                        </span>
                    </td>
                    <td>{{ $txn->debt?->transaction_type->label() ?? '-' }}</td>
                    <td>₦{{ number_format((float)$txn->amount, 2) }}</td>
                    <td>{{ $txn->note ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #999;">No transactions recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>CreditBook &copy; {{ date('Y') }} — This is a computer-generated statement.</p>
    </div>
</body>
</html>
