
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    body {
        background-color: #f3f4f6;
        color: #1f2937;
        font-family: 'Poppins', sans-serif;
    }

    #printable-invoice {
        max-width: 800px;
        margin: 20px auto;
        background-color: white;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        padding: 40px;
        position: relative;
    }

    .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 100px;
        color: rgba(0, 0, 0, 0.03);
        pointer-events: none;
        z-index: 0;
        white-space: nowrap;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 20px;
        border-bottom: 2px solid #0f4c81;
        margin-bottom: 30px;
    }

    .logo-container {
        display: flex;
        align-items: center;
    }

    .logo {
        width: 80px;
        height: 80px;
        background-color: #0f4c81;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        color: white;
        font-weight: bold;
        font-size: 24px;
        margin-right: 15px;
    }

    .company-info {
        line-height: 1.5;
        flex-grow: 1;
    }

    .company-name {
        font-size: 22px;
        font-weight: 700;
        color: #0f4c81;
    }

    .invoice-details {
        text-align: right;
    }

    .invoice-title {
        font-size: 24px;
        font-weight: 700;
        color: #0f4c81;
        margin-bottom: 5px;
    }

    .shipment-code {
        font-size: 16px;
        font-weight: 500;
        color: #4b5563;
        padding: 4px 12px;
        background-color: #e5e7eb;
        border-radius: 4px;
        display: inline-block;
    }

    .info-container {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
    }

    .info-box {
        width: 48%;
        padding: 20px;
        background-color: #f9fafb;
        border-radius: 6px;
        border-left: 4px solid #0f4c81;
    }

    .info-title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 10px;
        color: #0f4c81;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .info-content {
        line-height: 1.8;
    }

    .info-label {
        font-weight: 600;
        display: inline-block;
        width: 100px;
    }

    .shipment-summary {
        background-color: #f9fafb;
        padding: 20px;
        border-radius: 6px;
        margin-bottom: 30px;
    }

    .summary-title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 15px;
        color: #0f4c81;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #d1d5db;
        padding-bottom: 8px;
    }

    .summary-columns {
        display: flex;
        flex-wrap: wrap;
    }

    .summary-column {
        width: 33.333%;
        margin-bottom: 10px;
    }

    .cargo-icon {
        font-size: 36px;
        color: #0f4c81;
        margin-top: 10px;
    }

    .cargo-indicator {
        display: flex;
        align-items: center;
        margin-top: 15px;
    }

    .cargo-indicator span {
        margin-left: 10px;
        font-weight: 500;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
    }

    th {
        background-color: #0f4c81;
        color: white;
        font-weight: 500;
        text-align: left;
        padding: 12px 15px;
    }

    th:first-child {
        border-top-left-radius: 6px;
    }

    th:last-child {
        border-top-right-radius: 6px;
    }

    td {
        padding: 12px 15px;
        border-bottom: 1px solid #e5e7eb;
    }

    tr:nth-child(even) {
        background-color: #f9fafb;
    }

    tr:last-child td:first-child {
        border-bottom-left-radius: 6px;
    }

    tr:last-child td:last-child {
        border-bottom-right-radius: 6px;
    }

    .totals {
        width: 350px;
        margin-left: auto;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 30px;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 15px;
        border-bottom: 1px solid #e5e7eb;
    }

    .total-row:last-child {
        border-bottom: none;
        background-color: #0f4c81;
        color: white;
        font-weight: 600;
    }

    .footer {
        text-align: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
        font-size: 14px;
        color: #6b7280;
    }

    .footer p {
        margin-bottom: 5px;
    }

    .barcode {
        text-align: center;
        margin-bottom: 20px;
    }

    .barcode img {
        height: 50px;
    }

    .qr-code {
        width: 80px;
        height: 80px;
        background-color: #f3f4f6;
        display: flex;
        justify-content: center;
        align-items: center;
        color: #9ca3af;
        font-size: 10px;
        text-align: center;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 500;
        text-transform: uppercase;
    }

    .status-pending {
        background-color: #fef3c7;
        color: #92400e;
    }

    .status-delivered {
        background-color: #d1fae5;
        color: #065f46;
    }

    .status-transit {
        background-color: #dbeafe;
        color: #1e40af;
    }

    @media print {
        body {
            background-color: white;
        }

        #printable-invoice {
            box-shadow: none;
            margin: 0;
            padding: 20px;
            max-width: 100%;
        }

        .print-button {
            display: none;
        }
    }
</style>
<div id="printable-invoice" >
    <div class="watermark">NEWWORLD CARGO</div>

    <div class="header">
        <div class="logo-container">
            <div class="logo">NC</div>
            <div class="company-info">
                <div class="company-name">NEWWORLD CARGO</div>
                <div>Global Logistics Solutions</div>
                <div>+1 (555) 123-4567 | info@newworldcargo.com</div>
                <div>123 Shipping Lane, Port City, PC 12345</div>
            </div>
        </div>

        <div class="invoice-details">
            <div class="invoice-title">SHIPMENT INVOICE</div>
            <div class="shipment-code">{{ $shipment->code }}</div>
        </div>
    </div>

    <div class="info-container">
        <div class="info-box">
            <div class="info-title">Sender Information</div>
            <div class="info-content">
                <div><span class="info-label">Name:</span> {{ $shipment->client->name ?? 'N/A' }}</div>
                <div><span class="info-label">Phone:</span> {{ $shipment->client_phone }}</div>
                <div><span class="info-label">Address:</span> {{ $shipment->from_address->address ?? 'N/A' }}</div>
            </div>
        </div>

        <div class="info-box">
            <div class="info-title">Receiver Information</div>
            <div class="info-content">
                <div><span class="info-label">Destination:</span> {{ $shipment->consignment->destination }}</div>
                <div><span class="info-label">Status:</span>
                    <span class="status-badge status-transit">{{ $shipment->getStatus() }}</span>
                </div>
                <div><span class="info-label">Date:</span> {{ $shipment->created_at->format('F j, Y') }}</div>
            </div>
        </div>
    </div>

    <div class="shipment-summary">
        <div class="summary-title">Shipment Summary</div>
        <div class="summary-columns">
            <div class="summary-column">
                <div><span class="info-label">Type:</span> {{ $shipment->type }}</div>
                <div><span class="info-label">Cargo:</span> {{ $shipment->consignment->cargo_type ?? 'Sea' }} Freight</div>
                <div><span class="info-label">Branch:</span> {{ $shipment->branch->name ?? 'N/A' }}</div>
                <div><span class="info-label">Ship Date:</span>
                    @if(strpos($shipment->shipping_date, '/'))
                        {{ Carbon\Carbon::createFromFormat('d/m/Y', $shipment->shipping_date)->format('F j, Y') }}
                    @else
                        {{ \Carbon\Carbon::parse($shipment->shipping_date)->format('F j, Y') }}
                    @endif
                </div>
            </div>

            <div class="summary-column">
                @if ($shipment->prev_branch)
                    <div><span class="info-label">Prev Branch:</span> {{ Modules\Cargo\Entities\Branch::find($shipment->prev_branch)->name ?? 'N/A' }}</div>
                @endif
                <div><span class="info-label">Weight:</span> {{ $shipment->total_weight }} KG</div>
                <div><span class="info-label">Tax:</span> {{ format_shipment_price($shipment->tax, $shipment) }}</div>
                <div><span class="info-label">Collection:</span> {{ format_shipment_price($shipment->amount_to_be_collected ?? 0, $shipment) }}</div>
            </div>

            <div class="summary-column">
                <div><span class="info-label">Cargo Date:</span> {{ optional($shipment->consignment->cargo_date)->format('F j, Y') ?? 'N/A' }}</div>
                <div><span class="info-label">ETA:</span> {{ optional($shipment->consignment->eta)->format('F j, Y') ?? 'N/A' }}</div>
                @if ($shipment->consignment->cargo_type == 'sea')
                    <div><span class="info-label">ETA DAR:</span> {{ optional($shipment->consignment->eta_dar)->format('F j, Y') ?? 'N/A' }}</div>
                    <div><span class="info-label">ETA LUN:</span> {{ optional($shipment->consignment->eta_lun)->format('F j, Y') ?? 'N/A' }}</div>
                @endif
            </div>
        </div>

        <div class="cargo-indicator">
            @if ($shipment->consignment->cargo_type == 'air')
                <div class="cargo-icon">✈️</div>
                <span>Air Freight</span>
            @else
                <div class="cargo-icon">🚢</div>
                <span>Sea Freight</span>
            @endif
        </div>
    </div>

    <div class="summary-title">Package Items</div>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: center;">Packing (CTN)</th>
                <th style="text-align: right;">Type</th>
            </tr>
        </thead>
        <tbody>
            @foreach(Modules\Cargo\Entities\PackageShipment::where('shipment_id',$shipment->id)->get() as $package)
                <tr>
                    <td>{{ $package->description }}</td>
                    <td style="text-align: center;">{{ $package->qty }}</td>
                    <td style="text-align: right;">
                        @if(isset($package->package->name))
                            {{ json_decode($package->package->name, true)[app()->getLocale()] ?? '-' }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <div>Subtotal:</div>
            <div>${{ number_format(($shipment->amount_to_be_collected ?? 0) - ($shipment->tax ?? 0), 2) }}</div>
        </div>
        <div class="total-row">
            <div>Tax:</div>
            <div>{{ format_shipment_price($shipment->tax, $shipment) }}</div>
        </div>
        <div class="total-row">
            <div>TOTAL:</div>
            <div>{{ format_shipment_price($shipment->amount_to_be_collected ?? 0, $shipment) }}</div>
        </div>
    </div>

    <div class="barcode">
        <div class="qr-code">QR Code<br/>{{ $shipment->code }}</div>
    </div>

    <div class="footer">
        <p>This is an official document issued by Newworld Cargo Ltd.</p>
        <p>For inquiries, please contact customer service at +1 (555) 123-4567 or support@newworldcargo.com</p>
        <p><strong>Generated on:</strong> {{ now()->format('F j, Y, g:i a') }}</p>
    </div>
</div>
<script>
    function printInvoice() {
        let printContent = document.getElementById('printable-invoice').innerHTML;
        let originalContent = document.body.innerHTML;

        document.body.innerHTML = printContent;
        window.print();
        document.body.innerHTML = originalContent;
        location.reload(); // reload the page to restore JS functionality
    }
</script>
