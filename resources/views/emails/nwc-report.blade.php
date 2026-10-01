@component('emails.layouts.brand', ['title' => 'Your New World Cargo report', 'preheader' => 'Your requested report is attached.'])
<p>Hello,</p>

<p>Please find attached the NWC report for the period
    <strong>{{ optional($summary['period_start'] ?? null)->toFormattedDateString() }}</strong>
    to
    <strong>{{ optional($summary['period_end'] ?? null)->toFormattedDateString() }}</strong>.
</p>

<p>Key totals:</p>
<ul>
    <li>Total Transactions: <strong>{{ number_format($summary['total_rows'] ?? 0) }}</strong></li>
    <li>Total Bill (USD): <strong>{{ number_format($summary['total_bill_usd'] ?? 0, 2) }}</strong></li>
    <li>Total Bill (ZMW): <strong>{{ number_format($summary['total_bill_kwacha'] ?? 0, 2) }}</strong></li>
    <li>Total Airtel: <strong>{{ number_format($summary['total_airtel'] ?? 0, 2) }}</strong></li>
    <li>Total MTN: <strong>{{ number_format($summary['total_mtn'] ?? 0, 2) }}</strong></li>
    <li>Total Cash Payments: <strong>{{ number_format($summary['total_cash_payments'] ?? 0, 2) }}</strong></li>
    <li>Average Rate: <strong>{{ number_format($summary['average_rate'] ?? 0, 4) }}</strong></li>
</ul>

@endcomponent
