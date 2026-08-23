<button id="printBtn" onclick="printReceipt()" class="btnclicky btn btn-sm btn-light text-dark me-2">
    <i class="bi bi-receipt-cutoff"></i>
    <span id="printBtnText">Print Receipt</span>
    <span id="printSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
</button>
@php
    $nwcReceipt = $shipment->nwcReceipt;
    $latestPaymentReceipt = ($shipment->paymentReceipts ?? collect())->sortByDesc('created_at')->first();
    $receiptUser = $latestPaymentReceipt?->cashier_name
        ?? $nwcReceipt?->cashier_name
        ?? optional($latestPaymentReceipt?->user)->name
        ?? optional($nwcReceipt?->user)->name
        ?? optional(auth()->user())->name
        ?? 'System';
@endphp
<div id="receiptContent" style="display:none;">
    <div style="font-family: 'Poppins', sans-serif; width: 58mm; padding: 10px;">
        <div style="text-align: center;">
            <img src="https://app.newworldcargo.com/assets/lte/cargo-logo.svg" alt="New World Cargo Logo" style="max-width: 100px; margin-bottom: 5px;">
            <h3 style="margin: 0;">Newworld Cargo Limited </h3>
            <p style="font-size: 11px; margin: 2px 0;">TPIN.: 2001344196</p>
            <p style="font-size: 11px; margin: 2px 0;">Shop 62/A, Carousel Shopping Centre</p>
            <p style="font-size: 11px; margin: 2px 0;">Lusaka, Zambia</p>
            <p style="font-size: 11px; margin: 2px 0;">+260 763 297 287 | +260 763 313 193</p>
            <p style="font-size: 11px; margin: 2px 0;">info@newworldcargo.com</p>
        </div>

        <hr />
        <p>Date: {{ now()->format('Y-m-d H:i') }}</p>
        <p>Shipment ID: {{ $shipment->code }}</p>
        <p>Customer: {{ $shipment->client->name ?? '-' }}</p>
        <p>Cashier: {{ $receiptUser }}</p>
        <p>Receipt No.: {{ $shipment?->receipt?->receipt_number ?? '-' }}</p>

        <hr />
        <p><strong>Description:</strong></p>
        @foreach(Modules\Cargo\Entities\PackageShipment::where('shipment_id',$shipment->id)->get() as $package)
            <p>
                {{ $package->description }}<br>
                @if(isset($package->package->name))
                    {{ json_decode($package->package->name, true)[app()->getLocale()] ?? '-' }}
                @else
                    -
                @endif
            </p>
        @endforeach
        <hr />
        <p style="display:flex; justify-content:space-between;">
            <strong>No. Pkg:</strong>
            <span>{{ Modules\Cargo\Entities\PackageShipment::where('shipment_id', $shipment->id)->sum('qty') }}</span>
        </p>
        <hr />
        <p style="display:flex; justify-content:space-between;">
            <strong>{{ $shipment->consignment?->cargo_type === 'air' ? 'Total Weight' : 'Volume' }}:</strong>
            <span>{{ $shipment->consignment?->cargo_type === 'air' ? ($shipment->total_weight ?? '-') : ($shipment->volume ?? '-') }}</span>
        </p>
        <hr />
        <br />
        <p>Total: <strong>{{ number_format($shipment?->receipt?->total ?? 0, 2) }}</strong></p>

        <p style="text-align:center;">Thank you!</p>
    </div>
</div>

<script>
    if (!window.nwcPrintLogUrl) {
        window.nwcPrintLogUrl = "{{ fr_route('shipments.print-log', ['shipment' => $shipment->id], false) }}";
    }

    if (!window.logShipmentPrint) {
        window.logShipmentPrint = async function (type) {
            try {
                const response = await fetch(window.nwcPrintLogUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ type }),
                });

                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    throw new Error('Unexpected response');
                }

                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to log print');
                }
            } catch (error) {
                console.error('Print audit log failed:', error);
            }
        };
    }

    async function printReceipt() {
        const btn = document.getElementById("printBtn");
        const btnText = document.getElementById("printBtnText");
        const spinner = document.getElementById("printSpinner");

        // Disable the button and show spinner
        btn.disabled = true;
        spinner.classList.remove("d-none");
        btnText.textContent = "Printing...";

        if (window.logShipmentPrint) {
            await window.logShipmentPrint('receipt');
        }

        const receipt = document.getElementById("receiptContent").innerHTML;
        const printWindow = window.open('', '', 'width=300,height=600');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Receipt</title>
                    <style>
                        body { font-family: 'Poppins', sans-serif; font-size: 12px; width: 58mm; padding: 0; margin: 0; }
                        h3 { margin: 0; padding: 5px 0; text-align: center; }
                        p { margin: 2px 0; line-height: 1.2em; }
                        hr { border: none; border-top: 1px dashed #000; margin: 5px 0; }
                    </style>
                </head>
                <body onload="window.print(); setTimeout(() => window.close(), 500);">
                    ${receipt}
                </body>
            </html>
        `);
        printWindow.document.close();

        // Re-enable the button after 2 seconds (or longer if needed)
        setTimeout(() => {
            btn.disabled = false;
            spinner.classList.add("d-none");
            btnText.textContent = "Print Receipt";
        }, 2000);
    }
    </script>
