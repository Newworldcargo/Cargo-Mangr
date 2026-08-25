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
        $currentReceiptNumber = $receipt?->receipt_number ?? $nwcReceipt?->receipt_number;
        $paymentReceipts = ($shipment->paymentReceipts ?? collect())->filter(function ($paymentReceipt) use ($currentReceiptNumber) {
            return empty($currentReceiptNumber)
                || empty($paymentReceipt->receipt_number)
                || str_starts_with($paymentReceipt->receipt_number, $currentReceiptNumber . '-');
        });
        $chargeLines = ($shipment->chargeLines ?? \App\Models\ShipmentChargeLine::where('shipment_id', $shipment->id)->orderBy('sort_order')->get())
            ->filter(function ($chargeLine) use ($receipt) {
                if (!$receipt?->created_at || !$chargeLine->created_at) {
                    return true;
                }

                return $chargeLine->created_at->greaterThanOrEqualTo($receipt->created_at->copy()->subSeconds(5));
            });
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
                'amount' => strtoupper($nwcReceipt->payment_currency ?? '') === 'USD'
                    ? ($nwcReceipt->bill_usd ?? $receipt?->total)
                    : ($receipt?->total ?? $nwcReceipt->bill_kwacha),
                'currency' => $nwcReceipt->payment_currency ?? $receipt?->currency,
                'receipt_number' => $nwcReceipt->receipt_number,
            ]] : []);

        $paymentCurrency = strtoupper($receipt?->currency ?? $latestPaymentReceipt?->currency ?? $nwcReceipt?->payment_currency ?? 'ZMW');
        $paymentSymbol = currency_symbol_for($paymentCurrency);
        $convertUsdToPaymentCurrency = function ($amount) use ($paymentCurrency) {
            $amount = (float) $amount;
            if ($paymentCurrency === 'USD') {
                return round($amount, 2);
            }

            if ($paymentCurrency === 'ZMW') {
                $converted = convert_currency($amount, 'usd', 'zmw');
            } else {
                $rate = \App\Models\CurrencyExchangeRate::where('from_currency', 'USD')
                    ->where('to_currency', $paymentCurrency)
                    ->value('exchange_rate');
                $converted = $rate ? $amount * (float) $rate : $amount;
            }

            if (!is_numeric($converted)) {
                $converted = preg_replace('/[^0-9.\-]/', '', (string) $converted);
            }

            return round((float) $converted, 2);
        };
        $initialTotal = $convertUsdToPaymentCurrency($shipment->amount_to_be_collected ?? 0);
        $extraChargesTotal = round((float) $chargeLines->sum('amount'), 2);
        $subtotalBeforeDiscount = round($initialTotal + $extraChargesTotal, 2);
        $discountType = $receipt?->discount_type ?? $nwcReceipt?->discount_type ?? null;
        if ($discountType === 'percentage') {
            $discountType = 'percent';
        }
        $discountValue = (float) ($receipt?->discount_value ?? $nwcReceipt?->discount_value ?? 0);
        $discountAmount = 0.0;
        if ($discountType && $discountValue > 0) {
            $discountAmount = $discountType === 'percent'
                ? ($subtotalBeforeDiscount * $discountValue) / 100
                : $discountValue;
            $discountAmount = min(round($discountAmount, 2), $subtotalBeforeDiscount);
        }
        $finalBillTotal = round((float) ($receipt?->total ?? max(0, $subtotalBeforeDiscount - $discountAmount)), 2);
        $totalPaid = round((float) $paymentRows->sum(function ($paymentRow) {
            return (float) (is_array($paymentRow) ? ($paymentRow['amount'] ?? 0) : ($paymentRow->amount ?? 0));
        }), 2);
        if ($finalBillTotal <= 0 && $totalPaid > 0) {
            $finalBillTotal = $totalPaid;
        }
        $remainingBill = max(0, round($finalBillTotal - $totalPaid, 2));
        $isPartialPayment = $totalPaid > 0 && $remainingBill > 0.01;
        $showPaymentDetails = $shipment->paid || $paymentRows->isNotEmpty();

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
            } elseif ($receipt->status === 'partially_paid') {
                $paymentStatusLabel = 'Partially Paid';
                $paymentStatusTone = 'bg-yellow-100 text-yellow-800';
            } elseif ($receipt->status === 'completed') {
                $paymentStatusLabel = __('cargo::view.paid');
                $paymentStatusTone = 'bg-green-100 text-green-800';
            }
        }
        if ($isPartialPayment && (!$receipt || in_array($receipt->status, ['completed', 'partially_paid'], true))) {
            $paymentStatusLabel = 'Partially Paid';
            $paymentStatusTone = 'bg-yellow-100 text-yellow-800';
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
            @if ($showPaymentDetails)
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
                                        $rowCurrency = is_array($paymentRow) ? ($paymentRow['currency'] ?? null) : ($paymentRow->currency ?? null);
                                        $rowCurrency = $rowCurrency ?: 'ZMW';
                                        $rowSymbol = currency_symbol_for($rowCurrency);
                                        $receiptNumber = is_array($paymentRow) ? ($paymentRow['receipt_number'] ?? null) : $paymentRow->receipt_number;
                                        $paymentDate = is_array($paymentRow) ? ($paymentRow['created_at'] ?? null) : $paymentRow->created_at;
                                    @endphp
                                    <div class="flex justify-between gap-4">
                                        <span>{{ $method ? ucwords(str_replace('_', ' ', $method)) : 'Payment' }}</span>
                                        <span class="font-semibold text-gray-900 text-right">
                                            {{ $rowSymbol }}{{ number_format((float) $amount, 2) }} {{ $rowCurrency }}
                                        </span>
                                    </div>
                                    @if ($receiptNumber || $paymentDate)
                                        <div class="text-xs text-gray-500 text-right">
                                            @if ($receiptNumber)
                                                {{ $receiptNumber }}
                                            @endif
                                            @if ($paymentDate)
                                                <span>{{ $receiptNumber ? ' - ' : '' }}{{ $paymentDate instanceof \Carbon\Carbon ? $paymentDate->format('Y-m-d H:i') : $paymentDate }}</span>
                                            @endif
                                        </div>
                                    @endif
	                                @endforeach
                                    @if ($chargeLines->isNotEmpty())
                                        <div class="pt-2 mt-2 border-t border-gray-100">
                                            <div class="text-gray-500 mb-1">Extra Charges</div>
                                            @foreach ($chargeLines as $chargeLine)
                                                @php
                                                    $chargeCurrency = strtoupper($chargeLine->currency ?? $receipt?->currency ?? 'ZMW');
                                                    $chargeSymbol = currency_symbol_for($chargeCurrency);
                                                @endphp
                                                <div class="flex justify-between gap-4">
                                                    <span>{{ $chargeLine->description }}</span>
                                                    <span class="font-semibold text-gray-900 text-right">
                                                        {{ $chargeSymbol }}{{ number_format((float) $chargeLine->amount, 2) }} {{ $chargeCurrency }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="pt-2 mt-2 border-t border-gray-100">
                                        <div class="text-gray-500 mb-1">Billing Breakdown</div>
                                        <div class="flex justify-between gap-4">
                                            <span>Initial Total</span>
                                            <span class="font-semibold text-gray-900 text-right">
                                                {{ $paymentSymbol }}{{ number_format($initialTotal, 2) }} {{ $paymentCurrency }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <span>Extra Charges</span>
                                            <span class="font-semibold text-gray-900 text-right">
                                                {{ $paymentSymbol }}{{ number_format($extraChargesTotal, 2) }} {{ $paymentCurrency }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <span>
                                                Discount
                                                @if ($discountType === 'percent' && $discountValue > 0)
                                                    ({{ number_format($discountValue, 2) }}%)
                                                @endif
                                            </span>
                                            <span class="font-semibold text-gray-900 text-right">
                                                -{{ $paymentSymbol }}{{ number_format($discountAmount, 2) }} {{ $paymentCurrency }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <span>Final Bill</span>
                                            <span class="font-semibold text-gray-900 text-right">
                                                {{ $paymentSymbol }}{{ number_format($finalBillTotal, 2) }} {{ $paymentCurrency }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between gap-4">
                                            <span>Total Paid</span>
                                            <span class="font-semibold text-gray-900 text-right">
                                                {{ $paymentSymbol }}{{ number_format($totalPaid, 2) }} {{ $paymentCurrency }}
                                            </span>
                                        </div>
                                        @if ($isPartialPayment)
                                            <div class="flex justify-between gap-4">
                                                <span>Remaining Bill</span>
                                                <span class="font-semibold text-red-700 text-right">
                                                    {{ $paymentSymbol }}{{ number_format($remainingBill, 2) }} {{ $paymentCurrency }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>
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
            <div class="text-2xl font-bold text-blue-600">{!! $formatViewerAmount($shipment->amount_to_be_collected) !!}</div>
        </div>
    </div>
    @endif
</div>
