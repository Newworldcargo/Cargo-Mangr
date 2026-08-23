<div class="flex flex-wrap -mx-4">
    <div class="w-full md:w-1/3 px-4 mb-6">
        <div class="p-5 bg-gray-50 rounded-lg shadow-sm h-full">
            <h2 class="text-lg font-semibold text-gray-700 mb-3">Package Owner</h2>
            <div class="border-l-4 border-yellow-400 pl-3">
                @if($user_role == $admin || auth()->user()->can('show-clients') )
                    <a class="text-blue-600 font-bold text-lg hover:underline" href="{{route('clients.show',$shipment->client_id)}}">{{$shipment->client->name ?? 'Null'}}</a>
                @else
                    <span class="text-blue-900 font-bold text-lg">{{$shipment->client->name ?? 'Null'}}</span>
                @endif
                <p class="text-gray-600">{{ $shipment->client_phone }}</p>
                <p class="text-gray-600 text-sm">{{$shipment->from_address ? $shipment->from_address->address : ''}}</p>
            </div>
        </div>
    </div>

    <!-- Status Info -->
    <div class="w-full md:w-1/3 px-4 mb-6">
        <div class="p-5 bg-gray-50 rounded-lg shadow-sm h-full">
            <h2 class="text-lg font-semibold text-gray-700 mb-3">{{ __('cargo::view.status') }}</h2>
            <div class="flex items-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                @if(strpos(strtolower($shipment->getStatus()), 'delivered') !== false)
                    bg-green-100 text-green-800
                @elseif(strpos(strtolower($shipment->getStatus()), 'returned') !== false || strpos(strtolower($shipment->getStatus()), 'failed') !== false)
                    bg-red-100 text-red-800
                @elseif(strpos(strtolower($shipment->getStatus()), 'transit') !== false)
                    bg-blue-100 text-blue-800
                @else
                    bg-yellow-100 text-yellow-800
                @endif">
                    <svg class="w-4 h-4 mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $shipment->getStatus() }}
                </span>
            </div>
        </div>
    </div>

    @php
        $paymentStatusLabel = $shipment->paid == 1 ? __('cargo::view.paid') : __('cargo::view.pending');
        $paymentStatusTone = $shipment->paid == 1 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700';
        $receipt = $shipment->receipt;
        $nwcReceipt = $shipment->nwcReceipt;
        $paymentReceipts = $shipment->paymentReceipts ?? collect();
        $latestPaymentReceipt = $paymentReceipts->sortByDesc('created_at')->first();
        $paidAt = $latestPaymentReceipt?->created_at ?? $nwcReceipt?->created_at ?? $receipt?->created_at;
        $cashierName = $latestPaymentReceipt?->cashier_name
            ?? $nwcReceipt?->cashier_name
            ?? optional($latestPaymentReceipt?->user)->name
            ?? optional($nwcReceipt?->user)->name
            ?? null;
        $paymentRows = $paymentReceipts->isNotEmpty()
            ? $paymentReceipts
            : collect($nwcReceipt ? [[
                'method_of_payment' => $nwcReceipt->method_of_payment,
                'amount' => $receipt?->total ?? $nwcReceipt->bill_kwacha,
                'receipt_number' => $nwcReceipt->receipt_number,
            ]] : []);
        if ($receipt) {
            if ($receipt->isRefundRequested()) {
                $paymentStatusLabel = 'Refund Request Processing';
                $paymentStatusTone = 'bg-yellow-100 text-yellow-800';
            } elseif ($receipt->isPartiallyRefunded()) {
                $paymentStatusLabel = 'Partially Refunded';
                $paymentStatusTone = 'bg-orange-100 text-orange-800';
            } elseif ($receipt->isRefunded()) {
                $paymentStatusLabel = 'Refunded';
                $paymentStatusTone = 'bg-red-100 text-red-800';
            } elseif ($receipt->status === 'completed') {
                $paymentStatusLabel = __('cargo::view.paid');
                $paymentStatusTone = 'bg-green-100 text-green-800';
            }
        }
    @endphp

    <div class="w-full md:w-1/3 px-4 mb-6">
        <div class="p-5 bg-gray-50 rounded-lg shadow-sm h-full">
            <h2 class="text-lg font-semibold text-gray-700 mb-3">Payment Status</h2>
            <div class="flex items-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $paymentStatusTone }}">
                    <svg class="w-4 h-4 mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m5-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $paymentStatusLabel }}
                </span>
            </div>
            @if ($shipment->paid)
                <div class="mt-4 space-y-2 text-sm text-gray-700">
                    <div class="flex justify-between gap-4">
                        <span class="text-gray-500">Date Paid</span>
                        <span class="font-semibold text-gray-900 text-right">
                            {{ $paidAt ? $paidAt->format('Y-m-d H:i') : '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-gray-500">Cashier</span>
                        <span class="font-semibold text-gray-900 text-right">{{ $cashierName ?: '-' }}</span>
                    </div>
                    @if ($paymentRows->isNotEmpty())
                        <div class="pt-2 border-t border-gray-200">
                            <div class="text-gray-500 mb-1">Payment Details</div>
                            <div class="space-y-1">
                                @foreach ($paymentRows as $paymentRow)
                                    @php
                                        $method = is_array($paymentRow) ? ($paymentRow['method_of_payment'] ?? null) : $paymentRow->method_of_payment;
                                        $amount = is_array($paymentRow) ? ($paymentRow['amount'] ?? null) : $paymentRow->amount;
                                        $receiptNumber = is_array($paymentRow) ? ($paymentRow['receipt_number'] ?? null) : $paymentRow->receipt_number;
                                    @endphp
                                    <div class="flex justify-between gap-4">
                                        <span>{{ $method ? ucwords(str_replace('_', ' ', $method)) : 'Payment' }}</span>
                                        <span class="font-semibold text-gray-900 text-right">
                                            K{{ number_format((float) $amount, 2) }}
                                        </span>
                                    </div>
                                    @if ($receiptNumber)
                                        <div class="text-xs text-gray-500 text-right">{{ $receiptNumber }}</div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if (isset($shipment->amount_to_be_collected))
    <div class="w-full md:w-1/3 px-4 mb-6">
        <div class="p-5 bg-gray-50 rounded-lg shadow-sm h-full">
            <h2 class="text-lg font-semibold text-gray-700 mb-3">{{ __('cargo::view.amount_to_be_collected') }}</h2>
            <div class="text-2xl font-bold text-blue-600">K{{ number_format(convert_currency($shipment->amount_to_be_collected, 'usd', 'zmw'), 2) }}</div>
            <span class="text-muted text-sm">(${{ $shipment->amount_to_be_collected }})</span>
        </div>
    </div>
    @endif
</div>
