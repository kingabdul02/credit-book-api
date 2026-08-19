<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CreditBook Statement</title>
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
            margin-bottom: 30px;
            border-bottom: 2px solid #1a56db;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #1a56db;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            color: #666;
            margin: 5px 0 0;
        }
        .summary-cards {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        .card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px 16px;
            width: 30%;
            text-align: center;
        }
        .card .label {
            font-size: 10px;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 0.5px;
        }
        .card .value {
            font-size: 18px;
            font-weight: bold;
            margin-top: 4px;
        }
        .card.green .value { color: #10b981; }
        .card.red .value { color: #ef4444; }
        .card.blue .value { color: #1a56db; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background: #1a56db;
            color: white;
            padding: 8px 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
            font-size: 11px;
        }
        tr:nth-child(even) { background: #f8f9fa; }
        .status {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        .status-unpaid { background: #fee2e2; color: #ef4444; }
        .status-partial { background: #fef3c7; color: #f59e0b; }
        .status-paid { background: #d1fae5; color: #10b981; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
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
        <p>Debt & Credit Statement — {{ $user->name ?? 'User' }}</p>
        <p style="font-size: 10px;">Generated: {{ $generatedAt }}</p>
    </div>

    <table style="width: 100%; border: none; margin-bottom: 25px;">
        <tr>
            <td style="border: none; text-align: center; background: #d1fae5; border-radius: 8px; padding: 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: #666;">People Owe Me</div>
                <div style="font-size: 18px; font-weight: bold; color: #10b981;">₦{{ $totalOwed }}</div>
            </td>
            <td style="border: none; width: 10px;"></td>
            <td style="border: none; text-align: center; background: #fee2e2; border-radius: 8px; padding: 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: #666;">I Owe People</div>
                <div style="font-size: 18px; font-weight: bold; color: #ef4444;">₦{{ $totalIOwe }}</div>
            </td>
            <td style="border: none; width: 10px;"></td>
            <td style="border: none; text-align: center; background: #dbeafe; border-radius: 8px; padding: 12px;">
                <div style="font-size: 10px; text-transform: uppercase; color: #666;">Net Balance</div>
                <div style="font-size: 18px; font-weight: bold; color: #1a56db;">₦{{ $netBalance }}</div>
            </td>
        </tr>
    </table>

    <h3 style="color: #1a56db;">All Debts</h3>
    <table>
        <thead>
            <tr>
                <th>Contact</th>
                <th>Type</th>
                <th>Original</th>
                <th>Paid</th>
                <th>Outstanding</th>
                <th>Status</th>
                <th>Due Date</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($debts as $debt)
                <tr>
                    <td>{{ $debt->contact?->full_name ?? 'Unknown' }}</td>
                    <td>{{ $debt->transaction_type->label() }}</td>
                    <td>₦{{ number_format((float)$debt->original_amount, 2) }}</td>
                    <td>₦{{ number_format((float)$debt->paid_amount, 2) }}</td>
                    <td>₦{{ number_format((float)$debt->outstanding_amount, 2) }}</td>
                    <td>
                        <span class="status status-{{ strtolower(str_replace('_', '', $debt->status->value)) }}">
                            {{ $debt->status->label() }}
                        </span>
                    </td>
                    <td>{{ $debt->due_date?->format('M d, Y') ?? '-' }}</td>
                    <td>{{ $debt->created_at->format('M d, Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; color: #999;">No debts recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>CreditBook &copy; {{ date('Y') }} — This is a computer-generated statement.</p>
    </div>
</body>
</html>
