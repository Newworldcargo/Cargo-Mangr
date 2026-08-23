
    <script src="https://cdn.tailwindcss.com"></script>

    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#0f4c81',
                        'primary-light': '#dbeafe',
                    }
                },
                fontFamily: {
                    'sans': ['Poppins', 'sans-serif'],
                }
            }
        }
    </script>

    @php
        $storedReceipt = $receipt ?? $shipment->nwcReceipt;
        $defaultKwacha = convert_currency($shipment->amount_to_be_collected, 'usd', 'zmw') ?? 0;
        $billKwacha = $storedReceipt?->bill_kwacha ?? $defaultKwacha;
        $billUsd = $storedReceipt?->bill_usd ?? (float) $shipment->amount_to_be_collected;
        $rate = $storedReceipt?->rate;
        if (!$rate && $billUsd > 0) {
            $rate = round($billKwacha / $billUsd, 6);
        }
        $receiptNumber = $storedReceipt?->receipt_number;
        $methodOfPayment = $storedReceipt?->method_of_payment ?? $shipment->payment_method_id;
        $paymentMethodLabel = $methodOfPayment ? ucwords(str_replace('_', ' ', $methodOfPayment)) : 'N/A';
        $generatedAt = $storedReceipt?->updated_at ?? $storedReceipt?->created_at;
        $generatedOn = $generatedAt ? $generatedAt->format('F j, Y, g:i a') : now()->format('F j, Y, g:i a');
        $processedBy = $storedReceipt?->user?->name ?? optional(auth()->user())->name ?? 'System';
    @endphp

    <!-- Modal for null consignment -->
    @if(!$shipment->consignment)
    <div id="consignmentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 max-w-md w-full mx-4 shadow-xl">
            <div class="text-center">
                <div class="text-red-500 text-6xl mb-4">⚠️</div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Invalid Shipment</h2>
                <p class="text-gray-600 mb-6">
                    This shipment does not have an associated consignment. This may indicate that consignment does not exist.
                </p>
                <div class="flex flex-col space-y-3">
                    {{-- <button onclick="deleteShipment()" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-6 rounded-lg transition duration-200">
                        Delete Shipment
                    </button> --}}
                    <p class="text-sm text-gray-500">
                        This action cannot be undone.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Prevent modal from closing
        document.addEventListener('click', function(e) {
            if (e.target.id === 'consignmentModal') {
                e.preventDefault();
                e.stopPropagation();
            }
        });

        // Prevent escape key from closing modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
            }
        });

        function deleteShipment() {
            if (confirm('Are you sure you want to delete this shipment? This action cannot be undone.')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ fr_route("admin.shipments.destroy", $shipment->id) }}';
                
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                
                const methodField = document.createElement('input');
                methodField.type = 'hidden';
                methodField.name = '_method';
                methodField.value = 'DELETE';
                
                form.appendChild(csrfToken);
                form.appendChild(methodField);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    @endif

    <div id="printable-invoice" class="hidden print:block max-w-4xl mx-auto my-5 bg-white shadow-md rounded-lg p-8 relative">
        <!-- Watermark -->
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 -rotate-45 text-8xl text-gray-900/[0.03] pointer-events-none z-0 whitespace-nowrap">
            NEWWORLD CARGO LIMITED
        </div>

        <!-- Header -->
        <div class="flex justify-between items-center pb-5 border-b-2 border-primary mb-6 relative z-10">
            <div class="flex items-center">
                <div class="w-20 h-20 rounded-full overflow-hidden bg-white mr-4 flex justify-center items-center">
                    <img src="https://app.newworldcargo.com/assets/lte/cargo-logo.svg" alt="Newworld Cargo Logo" class="object-contain h-full w-full" />
                </div>
                <div class="leading-relaxed">
                    <div class="text-xl font-bold text-primary">NEWWORLD CARGO LIMITED</div>
                    <div>Shipping Made Easy</div>
                    <div>+260 763 297 287 | info@newworldcargo.com</div>
                    <div>Shop 62/A, Carousel Shopping Centre, Lusaka, Zambia</div>
                </div>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold text-primary mb-1">SHIPMENT INVOICE</div>
            <div class="inline-block px-3 py-1 bg-gray-200 rounded text-gray-600 font-medium">
                {{ $shipment->code }}
            </div>
            @if ($receiptNumber)
                <div class="mt-2 text-sm text-gray-500">
                    Receipt #: {{ $receiptNumber }}
                </div>
            @endif
        </div>
    </div>

        <!-- Info Container -->
        <div class="flex justify-between gap-4 mb-6">
            <!-- Sender Box -->
            <div class="w-1/2 p-5 bg-gray-50 rounded-md border-l-4 border-primary">
                <div class="text-base font-semibold mb-2 text-primary uppercase tracking-wider">Sender Information</div>
                <div class="leading-8">
                    <div><span class="font-semibold inline-block w-24">Name:</span> {{ $shipment->client->name ?? 'N/A' }}</div>
                    <div><span class="font-semibold inline-block w-24">Phone:</span> {{ $shipment->client_phone ?? '--' }}</div>
                    <div><span class="font-semibold inline-block w-24">Address:</span> {{ $shipment->from_address->address ?? 'N/A' }}</div>
                </div>
            </div>

            <!-- Receiver Box -->
            <div class="w-1/2 p-5 bg-gray-50 rounded-md border-l-4 border-primary">
                <div class="text-base font-semibold mb-2 text-primary uppercase tracking-wider">Receiver Information</div>
                <div class="leading-8">
                    <div><span class="font-semibold inline-block w-24">Destination:</span> {{ $shipment->consignment?->destination }}</div>
                    <div><span class="font-semibold inline-block w-24">Status:</span>
                        <span class="inline-block px-3 py-1 rounded-full text-sm font-medium uppercase bg-blue-100 text-blue-800">
                            {{ $shipment->getStatus() }}
                        </span>
                    </div>
                    <div><span class="font-semibold inline-block w-24">Date:</span> {{ $shipment->created_at->format('F j, Y') }}</div>
                </div>
            </div>
        </div>

        <!-- Shipment Summary -->
        <div class="bg-gray-50 p-5 rounded-md mb-6">
            <div class="text-base font-semibold mb-4 text-primary uppercase tracking-wider border-b border-gray-300 pb-2">
                Shipment Summary
            </div>
            <div class="flex flex-wrap">
                <div class="w-1/3 mb-3">
                    <div><span class="font-semibold inline-block w-24">Type:</span> {{ $shipment->type }}</div>
                    <div><span class="font-semibold inline-block w-24">Cargo:</span> {{ $shipment->consignment?->cargo_type ?? 'Sea' }} Freight</div>
                    <div><span class="font-semibold inline-block w-24">Branch:</span> {{ $shipment->branch->name ?? 'N/A' }}</div>
                    <div><span class="font-semibold inline-block w-24">Ship Date:</span>
                        @if(strpos($shipment->shipping_date, '/'))
                            {{ Carbon\Carbon::createFromFormat('d/m/Y', $shipment->shipping_date)->format('F j, Y') }}
                        @else
                            {{ \Carbon\Carbon::parse($shipment->shipping_date)->format('F j, Y') }}
                        @endif
                    </div>
                </div>

                <div class="w-1/3 mb-3">
                    @if ($shipment->prev_branch)
                        <div><span class="font-semibold inline-block w-24">Prev Branch:</span> {{ Modules\Cargo\Entities\Branch::find($shipment->prev_branch)->name ?? 'N/A' }}</div>
                    @endif
                    <div><span class="font-semibold inline-block w-24">Weight:</span> {{ $shipment->total_weight }} KG</div>
                    {{-- <div><span class="font-semibold inline-block w-24">Tax:</span> {{ format_price($shipment->tax) }}</div> --}}
                    {{-- <div><span class="font-semibold inline-block w-24">Collection:</span> {{ (number_format(convert_currency($shipment->amount_to_be_collected, 'usd', 'zmw'), 2) ?? 0) }}</div> --}}
                </div>

                <div class="w-1/3 mb-3">
                    <div><span class="font-semibold inline-block w-24">Cargo Date:</span> {{ $shipment->consignment?->cargo_date }}</div>
                    <div><span class="font-semibold inline-block w-24">ETA:</span> {{ $shipment->consignment?->eta }}</div>
                    @if ($shipment->consignment?->cargo_type == 'sea')
                        <div><span class="font-semibold inline-block w-24">ETA DAR:</span> {{ $shipment->consignment?->eta_dar }}</div>
                        <div><span class="font-semibold inline-block w-24">ETA LUN:</span> {{ $shipment->consignment?->eta_lun }}</div>
                    @endif
                </div>
            </div>

            <div class="flex items-center mt-4">
                @if ($shipment->consignment?->cargo_type == 'air')
                    <div class="text-4xl text-primary">✈️</div>
                    <span class="ml-2 font-medium">Air Freight</span>
                @else
                    <div class="text-4xl text-primary">🚢</div>
                    <span class="ml-2 font-medium">Sea Freight</span>
                @endif
            </div>
        </div>

        <!-- Package Items -->
        <div class="text-base font-semibold mb-4 text-primary uppercase tracking-wider">Package Items</div>
        <div class="overflow-x-auto mb-6">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="bg-primary text-white font-medium text-left py-3 px-4 rounded-tl-md">Description</th>
                        <th class="bg-primary text-white font-medium text-center py-3 px-4">Packing (CTN)</th>
                        <th class="bg-primary text-white font-medium text-right py-3 px-4 rounded-tr-md">Type</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(Modules\Cargo\Entities\PackageShipment::where('shipment_id',$shipment->id)->get() as $package)
                        <tr class="{{ $loop->even ? 'bg-gray-50' : '' }}">
                            <td class="py-3 px-4 border-b border-gray-200">{{ $package->description }}</td>
                            <td class="py-3 px-4 border-b border-gray-200 text-center">{{ $package->qty }}</td>
                            <td class="py-3 px-4 border-b border-gray-200 text-right">
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
        </div>

        <!-- Totals -->
        <div class="w-80 ml-auto border border-gray-200 rounded-md overflow-hidden mb-6">
            @if ($receiptNumber)
                <div class="flex justify-between px-4 py-2 border-b border-gray-200">
                    <div>Receipt #:</div>
                    <div>{{ $receiptNumber }}</div>
                </div>
            @endif
            <div class="flex justify-between px-4 py-2 border-b border-gray-200">
                <div>Bill (USD):</div>
                <div>${{ number_format($billUsd, 2) }}</div>
            </div>
            <div class="flex justify-between px-4 py-2 border-b border-gray-200">
                <div>Rate (USD -> ZMW):</div>
                <div>{{ $rate ? number_format($rate, 4) : 'N/A' }}</div>
            </div>
            <div class="flex justify-between px-4 py-2 border-b border-gray-200">
                <div>Payment Method:</div>
                <div>{{ $paymentMethodLabel }}</div>
            </div>
            <div class="flex justify-between px-4 py-2 border-b border-gray-200">
                <div>Processed By:</div>
                <div>{{ $processedBy }}</div>
            </div>
            <div class="flex justify-between px-4 py-2 bg-primary text-dark font-bold">
                <div>Bill (ZMW):</div>
                <div>K{{ number_format($billKwacha, 2) }}</div>
            </div>
        </div>

        <!-- Barcode -->
        <div class="text-center mb-5">
            <div class="w-20 h-20 bg-gray-100 mx-auto flex justify-center items-center text-gray-400 text-xs text-center">
                QR Code<br/>{{ $shipment->code }}
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-5 pt-5 border-t border-gray-200 text-sm text-gray-500">
            <p>This is an official document issued by Newworld Cargo Limited.</p>
            <p>
                For inquiries, please contact Customer Care at
                <br>
                +260 763 297 287 or +260 763 313 193
                <br>
                or write to us at <a href="mailto:info@newworldcargo.com" class="text-blue-500 hover:underline">info@newworldcargo.com</a>
            </p>
            <p class="mt-2">
                Our Location: Shop 62/A, Carousel Shopping Centre, Lusaka, Zambia
            </p>
            <p class="font-semibold mt-2">
                Generated on: {{ $generatedOn }}
            </p>
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

    async function printInvoice() {
        if (window.logShipmentPrint) {
            await window.logShipmentPrint('invoice');
        }
        let printContent = document.getElementById('printable-invoice').innerHTML;
        let originalContent = document.body.innerHTML;

        document.body.innerHTML = printContent;
        window.print();
        document.body.innerHTML = originalContent;
        location.reload(); // reload the page to restore JS functionality
    }
</script>
    <style>
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
        }
    </style>
