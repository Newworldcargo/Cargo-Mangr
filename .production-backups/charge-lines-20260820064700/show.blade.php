@php
    use \Milon\Barcode\DNS1D;
    use Carbon\Carbon;
    use Illuminate\Support\Str;
    use App\Models\GeneralSettings;
    $d = new DNS1D();
    $user_role = auth()->user()->role;
    $admin = 1;
    $auditLogs = $auditLogs ?? collect();
    $settings = app(GeneralSettings::class);
    $refundEnabled = (bool) ($settings->enable_refund_payments ?? false);
    $allowClientRefunds = (bool) ($settings->allow_client_refunds ?? false);
    $canApproveRefundRequests = auth()->user()->can('approve-refund-requests');
    $canDirectRefund = $refundEnabled && $canApproveRefundRequests;
    $canRequestRefund = $refundEnabled && (
        ($user_role == 4 && $allowClientRefunds)
        || ($user_role != 4 && (auth()->user()->can('confirm-shipment-payment') || auth()->user()->hasRole(['cashier', 'cashiers'])))
    );
    $pendingRefundRequest = $pendingRefundRequest ?? null;
@endphp

@extends('cargo::adminLte.layouts.master')
@section('pageTitle')
    {{ __('cargo::view.shipment') . '-' . $shipment->code }}
@endsection
@section('content')

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Add Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .breadcrumb a {
            color: #ffc507;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }
    </style>

    <div class="">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-light p-2 mb-0" style="font-size: 0.9rem; border-radius: 0.25rem;">
                <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('consignment.index') }}">Consignments</a></li>
                <li class="breadcrumb-item"><a href="{{ route('consignment.show', $shipment->consignment_id) }}">Consignment
                        Shipments</a></li>
                <li class="breadcrumb-item active" aria-current="page">Shipment - Invoice Details</li>
            </ol>
        </nav>
    </div>
    <div class="card shadow-lg rounded-lg overflow-hidden">
        <div class="p-0 card-body">
            @include('cargo::adminLte.pages.shipments._partials.payment-modal-message')
            <div class="bg-gradient-to-r from-yellow-400 to-yellow-300 text-white px-8 py-6">
                <div class="container mx-auto">
                    <div class="flex flex-col md:flex-row justify-between items-start">
                        <div>
                            <h1 class="text-3xl font-bold">{{ __('cargo::view.shipment') }}: {{$shipment->code}}</h1>
                            @if($shipment->order_id != null)
                                <p class="mt-1 opacity-80">{{ __('cargo::view.order_id') }}: {{$shipment->order_id}}</p>
                            @endif
                        </div>
                        <div class="mt-4 md:mt-0 text-right">
                            @if($shipment->barcode != null)
                                <div class="mb-2 bg-white py-2 px-4 rounded-md inline-block">
                                    <?=$d->getBarcodeHTML($shipment->code, "C128");?>
                                </div>
                            @endif
                            <p class="text-sm font-medium"><span class="opacity-80">{{ __('cargo::view.from') }}:</span>
                                {{$shipment->consignment?->source}}</p>
                            <p class="text-sm font-medium"><span class="opacity-80">{{ __('cargo::view.to') }}:</span>
                                {{$shipment->consignment?->destination}}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="container mx-auto px-6 py-8">


                @include('cargo::adminLte.pages.shipments._partials.shipment-client-details')
                @include('cargo::adminLte.pages.shipments._partials.shipment-details')
                @include('cargo::adminLte.pages.shipments._partials.shipment-packages')
                <!-- Total Cost -->
                <div class="mt-8 bg-gradient-to-r from-yellow-50 rounded-lg shadow-sm p-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-700">{{ __('cargo::view.total_cost') }}</h2>
                            {{-- <p class="text-sm text-gray-500">{{ __('cargo::view.included_tax_insurance') }}</p> --}}
                        </div>
                        <div class="text-right">
                            <span class="text-3xl font-bold text-blue-600">
                                K{{ number_format(convert_currency($shipment->amount_to_be_collected, 'usd', 'zmw'), 2) }}
                            </span>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Sticky Bottom Toolbar -->
            <div class="fixed-bottom bg-white border-t border-gray-200 shadow-lg px-6 py-4" style="z-index: 1050;">
                <div class="container mx-auto">
                    <div class="flex flex-wrap justify-between items-center">
                        <div>
                            @php
                                $INVOICE_PAYMENT = 'invoice_payment';
                                $cash_payment = 'cash_payment';
                            @endphp

                            @if ($user_role != $admin)
                                @if($shipment->paid == 0 && $shipment->payment_method_id != $cash_payment && $shipment->payment_method_id != $INVOICE_PAYMENT)
                                    {{-- <button type="button"
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                                        onclick="openCheckoutModal()">
                                        {{ __('cargo::view.pay_now') }}
                                        <i class="ml-1 fas fa-credit-card"></i>
                                    </button> --}}
                                @endif
                            @endif
                        </div>

                        <div class="flex items-center space-x-3">

                            <button id="auditTrailBtn"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-black hover:bg-black focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <img width="20" style="background-color:#fff; padding:2px; border-radius:100%"
                                    src="{{ asset('feet.png') }}" />
                                &nbsp;
                                <span>Audit Trails</span>
                            </button>
                            @if ($canApproveRefundRequests)
                                <a href="{{ fr_route('refund-requests.index') }}"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <i class="fas fa-list mr-1"></i>
                                    <span>Refund Requests</span>
                                </a>
                            @endif
                            @can('print-shipment-invoice')
                                <button id="printBtn2" onclick="printInvoice()"
                                    class="btnclicky inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-dark bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                    <i class="fas fa-print mr-1"></i>
                                    <span id="printBtnText2">Print Invoice</span>
                                    <span id="printSpinner2" class="spinner-border spinner-border-sm d-none" role="status"
                                        aria-hidden="true"></span>
                                </button>
                            @endcan
                            @include('cargo::adminLte.pages.shipments._partials.print-invoice')


                            @if ($shipment->paid)
                                @can('print-shipment-receipt')
                                    @include('cargo::adminLte.pages.shipments._partials.print-receipt')
                                @endcan
                                @if ($pendingRefundRequest)
                                    @if ($canDirectRefund)
                                        <a href="{{ fr_route('refund-requests.show', $pendingRefundRequest->id) }}"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <i class="fas fa-clipboard-check mr-1"></i> Review Refund Request
                                        </a>
                                    @else
                                        <span class="text-sm text-yellow-700 bg-yellow-100 px-3 py-2 rounded-md">
                                            Refund request pending
                                        </span>
                                    @endif
                                @else
                                    @if ($canDirectRefund)
                                        <button
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 refund-action-btn"
                                            data-refund-action="direct"
                                            data-refund-url="{{ fr_route('shipments.refund-payment', [], false) }}"
                                            data-refund-label="Refund Payment"
                                            onclick="openRefundModal({{ $shipment->id }}, this)">
                                            <i class="fas fa-undo mr-1"></i> Refund Payment
                                        </button>
                                    @elseif ($canRequestRefund)
                                        <button
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 refund-action-btn"
                                            data-refund-action="request"
                                            data-refund-url="{{ fr_route('refund-requests.store', [], false) }}"
                                            data-refund-label="Request Refund"
                                            onclick="openRefundModal({{ $shipment->id }}, this)">
                                            <i class="fas fa-paper-plane mr-1"></i> Request Refund
                                        </button>
                                    @endif
                                @endif
                            @else
                                @php
                                    $user = auth()->user();
                                    $hasCashierRole = $user->hasRole(['cashier', 'cashiers']);
                                    $hasPermission = $user->can('confirm-shipment-payment');
                                @endphp

                                @if($hasPermission)
                                    {{-- @if($hasCashierRole || $hasPermission) --}}
                                    <button
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-gray-700 bg-yellow-400 hover:bg-yellow-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-400"
                                        onclick="openMarkPaidModal({{ $shipment->id }})">
                                        <i class="fas fa-check-circle mr-1"></i> Mark as Paid
                                    </button>
                                @else
                                    <small>Only Cashier confirms payment.</small>
                                @endif
                            @endif

                            @if($user_role == $admin || auth()->user()->can('edit-shipments'))
                                {{-- <a href="{{ route('shipments.edit', $shipment->id) }}"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    <i class="fas fa-pen mr-1"></i> {{ __('cargo::view.edit_shipment') }}
                                </a> --}}
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal for Confirm Payment -->
            @php
                $totalAmount = convert_currency($shipment->amount_to_be_collected, 'usd', 'zmw');
                $receipt = $shipment->nwcReceipt;
                $paymentMethodOptions = [
                    'cash_payment' => 'Cash Payment',
                    'invoice_payment' => 'Invoice Payment',
                    'bank_transfer' => 'Bank Transfer',
                    'card_payment' => 'Card Payment',
                    'airtel' => 'Airtel Mobile Money',
                    'mtn' => 'MTN Mobile Money',
                    'zamtel' => 'Zamtel Mobile Money',
                    'other' => 'Other',
                ];
                $selectedPaymentMethod = $receipt?->method_of_payment ?? $shipment->payment_method_id ?? '';
                if ($selectedPaymentMethod && !array_key_exists($selectedPaymentMethod, $paymentMethodOptions)) {
                    $paymentMethodOptions[$selectedPaymentMethod] = ucwords(str_replace('_', ' ', $selectedPaymentMethod));
                }
            @endphp
            <div class="modal fade" id="markPaidModal" tabindex="-1" role="dialog" aria-labelledby="markPaidLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow-lg rounded-lg overflow-hidden"
                        style="background-color: #f8f9fa;">
                        <!-- Header with dark blue gradient -->
                        <div class="modal-header py-4"
                            style="background: linear-gradient(45deg, #0a2463 0%, #1e3a8a 100%); border: none;">
                            <div class="d-flex align-items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#FFD700"
                                    class="bi bi-credit-card-fill me-3" viewBox="0 0 16 16">
                                    <path
                                        d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1H0zm0 3v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7zm3 2h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-1a1 1 0 0 1 1-1" />
                                </svg>
                                <h5 class="modal-title fw-bold mb-0 text-white" id="markPaidLabel">Confirm Payment</h5>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-dismiss="modal"
                                aria-label="Close"></button>
                        </div>

                        <div class="modal-body p-4">
                            <form id="markPaidForm">
                                <!-- Alert with yellow accent -->
                                <div class="alert mb-4 border-0 shadow-sm"
                                    style="background-color: #fffbeb; border-left: 4px solid #FFD700;">
                                    <div class="d-flex align-items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#FFD700"
                                            class="bi bi-info-circle-fill me-3" viewBox="0 0 16 16">
                                            <path
                                                d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2" />
                                        </svg>
                                        <p class="mb-0 fw-medium">Are you sure you want to mark this shipment as paid?</p>
                                    </div>
                                </div>
                                <div class="card border-0 shadow-sm rounded-3 p-4 mb-4" style="background-color: white;">
                                    <h6 class="mb-3 text-uppercase"
                                        style="color: #0a2463; font-size: 0.85rem; letter-spacing: 0.5px;">Discount Details
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="discountType" class="form-label fw-medium mb-2"
                                                    style="color: #475569; font-size: 0.9rem;">
                                                    Discount Type
                                                </label>
                                                <div class="input-group w-full flex-1">
                                                    <span class="input-group-text border-0"
                                                        style="background-color: #f8fafc; border-radius: 8px 0 0 8px;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                            fill="#FFD700" viewBox="0 0 16 16">
                                                            <path
                                                                d="M9.5 0a.5.5 0 0 1 .5.5.5.5 0 0 0 .5.5.5.5 0 0 1 .5.5V2h6.5a.5.5 0 0 1 0 1H18v6.5a.5.5 0 0 1 .5.5.5.5 0 0 0 .5.5.5.5 0 0 1 0 1H17v6.5a.5.5 0 0 1-.5.5.5.5 0 0 0-.5.5.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5.5.5 0 0 0-.5-.5.5.5 0 0 1-.5-.5V16H.5a.5.5 0 0 1 0-1H1v-6.5a.5.5 0 0 1 .5-.5.5.5 0 0 0 .5-.5.5.5 0 0 1 0-1H1V.5a.5.5 0 0 1 .5-.5H8v.5a.5.5 0 0 0 .5.5.5.5 0 0 1 .5.5z" />
                                                        </svg>
                                                    </span>
                                                    <select class="form-select flex-1 w-full form-control-lg border-0"
                                                        id="discountType"
                                                        style="background-color: #f8fafc; border-radius: 0 8px 8px 0; height: 48px; font-size: 0.95rem;">
                                                        <option value="">None</option>
                                                        <option value="fixed">Fixed Amount</option>
                                                        <option value="percent">Percentage</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="discountValue" class="form-label fw-medium mb-2"
                                                    style="color: #475569; font-size: 0.9rem;">
                                                    Discount Value
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text border-0"
                                                        style="background-color: #f8fafc; border-radius: 8px 0 0 8px;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                            fill="#FFD700" viewBox="0 0 16 16">
                                                            <path
                                                                d="M1.5 2.5A1.5 1.5 0 0 1 3 1h10a1.5 1.5 0 0 1 1.5 1.5v1A1.5 1.5 0 0 1 13 5h-1a1.5 1.5 0 0 1 0 3h1a1.5 1.5 0 0 1 1.5 1.5v1A1.5 1.5 0 0 1 13 12H3a1.5 1.5 0 0 1-1.5-1.5v-1A1.5 1.5 0 0 1 3 8h1a1.5 1.5 0 0 1 0-3H3a1.5 1.5 0 0 1-1.5-1.5z" />
                                                        </svg>
                                                    </span>
                                                    <input type="number" class="form-control form-control-lg border-0"
                                                        id="discountValue" value="0" min="0"
                                                        style="background-color: #f8fafc; border-radius: 0 8px 8px 0; height: 48px; font-size: 0.95rem;">
                                                    <span class="input-group-text border-0 d-none" id="percentSymbol"
                                                        style="background-color: #f8fafc; border-radius: 0 8px 8px 0;">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card border-0 shadow-sm rounded-3 p-4 mb-4 bg-white">
                                    <h6 class="mb-3 text-uppercase text-[#0a2463] text-[0.85rem] tracking-[0.5px]">Payment
                                        Method</h6>

                                    <div id="payment-rows" class="flex flex-col gap-4">
                                        <!-- initial payment row -->
                                        <div class="payment-row flex items-start gap-3">
                                            <div class="flex flex-col flex-1 gap-2">
                                                <!-- Method of Payment -->
                                                <div>
                                                    <label for="methodOfPayment"
                                                        class="form-label fw-medium mb-2 text-slate-600 text-[0.9rem]">
                                                        Method of Payment
                                                    </label>
                                                    <div class="flex w-full items-center">
                                                        <span
                                                            class="flex items-center justify-center px-3 py-2 bg-slate-50 rounded-l-md border border-slate-200">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                                fill="#0a2463" viewBox="0 0 16 16">
                                                                <path
                                                                    d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1H0zm0 3v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z" />
                                                                <path
                                                                    d="M3 10a1 1 0 0 1 1-1h1v2H4a1 1 0 0 1-1-1m5 0h3a1 1 0 0 1 0 2H8z" />
                                                            </svg>
                                                        </span>
                                                        <select
                                                            class="form-select w-full border border-slate-200 bg-slate-50 rounded-r-md h-12 text-[0.95rem] flex-1"
                                                            name="method_of_payment[]"
                                                            data-default="{{ $selectedPaymentMethod }}" required>
                                                            <option value="" {{ $selectedPaymentMethod === '' ? 'selected' : '' }}>Select payment method</option>
                                                            @foreach ($paymentMethodOptions as $value => $label)
                                                                <option value="{{ $value }}" {{ $selectedPaymentMethod === $value ? 'selected' : '' }}>
                                                                    {{ $label }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Amount -->
                                                <div>
                                                    <input type="number" name="payment_amount[]"
                                                        class="form-control border border-slate-200 bg-slate-50 rounded-md h-12 text-[0.95rem] w-full"
                                                        placeholder="Enter amount" min="0" step="0.01" required />
                                                </div>
                                            </div>

                                            <!-- buttons -->
                                            <div class="flex flex-col gap-2 pt-7">
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary add-payment w-8 h-14 flex items-center justify-center rounded-xl border border-blue-500 text-blue-600 font-bold hover:bg-blue-50">+</button>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-danger remove-payment w-8 h-14 flex items-center justify-center rounded-xl border border-red-500 text-red-600 font-bold hover:bg-red-50">−</button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- template for cloned rows -->
                                    <template id="payment-row-tpl">
                                        <div class="payment-row flex items-start gap-3">
                                            <div class="flex flex-col flex-1 gap-2">
                                                <div>
                                                    <label
                                                        class="form-label fw-medium mb-2 text-slate-600 text-[0.9rem]">Method
                                                        of Payment</label>
                                                    <div class="flex items-center">
                                                        <span
                                                            class="flex items-center justify-center px-3 py-2 bg-slate-50 rounded-l-md border border-slate-200">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                                fill="#0a2463" viewBox="0 0 16 16">
                                                                <path
                                                                    d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1H0zm0 3v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z" />
                                                                <path
                                                                    d="M3 10a1 1 0 0 1 1-1h1v2H4a1 1 0 0 1-1-1m5 0h3a1 1 0 0 1 0 2H8z" />
                                                            </svg>
                                                        </span>
                                                        <select
                                                            class="form-select border border-slate-200 bg-slate-50 rounded-r-md h-12 text-[0.95rem] flex-1"
                                                            name="method_of_payment[]" required>
                                                            <option value="">Select payment method</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <div>

                                                    <input type="number" name="payment_amount[]"
                                                        class="form-control border border-slate-200 bg-slate-50 rounded-md h-12 text-[0.95rem] w-full"
                                                        placeholder="Enter amount" min="0" step="0.01" required />
                                                </div>
                                            </div>

                                            <div class="flex flex-col gap-2 pt-7">
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary add-payment w-8 h-8 flex items-center justify-center rounded-md border border-blue-500 text-blue-600 font-bold hover:bg-blue-50">+</button>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-danger remove-payment w-8 h-8 flex items-center justify-center rounded-md border border-red-500 text-red-600 font-bold hover:bg-red-50">−</button>
                                            </div>
                                        </div>
                                    </template>
                                </div>



                                <!-- Summary card with yellow accent -->
                                <div class="card border-0 shadow-sm rounded-3 p-4 mt-4" style="background-color: white;">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted">Original Total:</span>
                                        <span id="originalTotal"
                                            class="fw-medium">{{ number_format($totalAmount, 2) }}</span>
                                    </div>
                                    <hr style="opacity: 0.1;">
                                    <div class="d-flex justify-content-between mt-2">
                                        <span class="fw-bold" style="color: #0a2463;">Final Total:</span>
                                        <span id="finalTotal" class="fw-bold fs-5"
                                            style="color: #0a2463;">{{ number_format($totalAmount, 2) }}</span>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="modal-footer py-4"
                            style="background-color: #f8f9fa; border-top: 1px solid rgba(0,0,0,0.05);">
                            <button type="button" class="btn px-4 py-2" data-dismiss="modal"
                                style="background-color: #e2e8f0; color: #64748b; border: none; border-radius: 8px; font-weight: 600;">
                                Cancel
                            </button>
                            <button type="button" class="btn px-4 py-2 d-flex align-items-center btnclicky"
                                id="confirmMarkPaidBtn"
                                style="background-color: #FFD700; color: #0a2463; border: none; border-radius: 8px; font-weight: 600; box-shadow: 0 2px 5px rgba(255, 215, 0, 0.3);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                    class="bi bi-check-circle-fill me-2" viewBox="0 0 16 16">
                                    <path
                                        d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z" />
                                </svg>
                                <span>Confirm Payment</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Refund Modal -->
            <div class="modal fade" id="refundModal" tabindex="-1" role="dialog" aria-labelledby="refundLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow-lg rounded-lg overflow-hidden"
                        style="background-color: #f8f9fa;">
                        <!-- Header with red gradient -->
                        <div class="modal-header py-4"
                            style="background: linear-gradient(45deg, #dc2626 0%, #b91c1c 100%); border: none;">
                            <div class="d-flex align-items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#ffffff"
                                    class="bi bi-arrow-counterclockwise me-3" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd"
                                        d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2v1z" />
                                    <path
                                        d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466z" />
                                </svg>
                                <h5 class="modal-title fw-bold mb-0 text-white" id="refundLabel">Confirm Refund</h5>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-dismiss="modal"
                                aria-label="Close"></button>
                        </div>

                        <div class="modal-body p-4">
                            <form id="refundForm">
                                <!-- Alert with red accent -->
                                <div class="alert mb-4 border-0 shadow-sm"
                                    style="background-color: #fef2f2; border-left: 4px solid #dc2626;">
                                    <div class="d-flex align-items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#dc2626"
                                            class="bi bi-exclamation-triangle-fill me-3" viewBox="0 0 16 16">
                                            <path
                                                d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z" />
                                        </svg>
                                        <p class="mb-0 fw-medium">Are you sure you want to refund this payment? This action
                                            cannot be undone.</p>
                                    </div>
                                </div>

                                <div class="card border-0 shadow-sm rounded-3 p-4 mb-4" style="background-color: white;">
                                    <h6 class="mb-3 text-uppercase"
                                        style="color: #dc2626; font-size: 0.85rem; letter-spacing: 0.5px;">Refund Details
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="refundReason" class="form-label fw-medium mb-2"
                                                    style="color: #475569; font-size: 0.9rem;">
                                                    Reason for Refund
                                                </label>
                                                <div class="input-group">
                                                    <span class="input-group-text border-0"
                                                        style="background-color: #f8fafc; border-radius: 8px 0 0 8px;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                                            fill="#dc2626" viewBox="0 0 16 16">
                                                            <path
                                                                d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z" />
                                                            <path
                                                                d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286zm1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94z" />
                                                        </svg>
                                                    </span>
                                                    <textarea class="form-control form-control-lg border-0"
                                                        id="refundReason" rows="3"
                                                        style="background-color: #f8fafc; border-radius: 0 8px 8px 0; font-size: 0.95rem;"
                                                        placeholder="Enter reason for refund..."></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="refundType" class="form-label fw-medium mb-2"
                                                    style="color: #475569; font-size: 0.9rem;">
                                                    Refund Type
                                                </label>
                                                <select class="form-select form-control-lg border-0"
                                                    id="refundType"
                                                    style="background-color: #f8fafc; border-radius: 8px; height: 48px; font-size: 0.95rem;">
                                                    <option value="full">Full Refund</option>
                                                    <option value="partial">Partial Refund</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="refundAmount" class="form-label fw-medium mb-2"
                                                    style="color: #475569; font-size: 0.9rem;">
                                                    Refund Amount
                                                </label>
                                                <input type="number" class="form-control form-control-lg border-0"
                                                    id="refundAmount" min="0" step="0.01"
                                                    value="{{ $remainingRefundAmount ?? $totalAmount }}"
                                                    style="background-color: #f8fafc; border-radius: 8px; height: 48px; font-size: 0.95rem;">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Summary card with red accent -->
                                <div class="card border-0 shadow-sm rounded-3 p-4 mt-4" style="background-color: white;">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="text-muted">Original Payment Amount:</span>
                                        <span class="fw-medium">{{ number_format($totalAmount, 2) }}</span>
                                    </div>
                                    <hr style="opacity: 0.1;">
                                    <div class="d-flex justify-content-between mt-2">
                                        <span class="fw-bold" style="color: #dc2626;">Refund Amount:</span>
                                        <span class="fw-bold fs-5" id="refundSummaryAmount"
                                            style="color: #dc2626;">{{ number_format($remainingRefundAmount ?? $totalAmount, 2) }}</span>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="modal-footer py-4"
                            style="background-color: #f8f9fa; border-top: 1px solid rgba(0,0,0,0.05);">
                            <button type="button" class="btn px-4 py-2" data-dismiss="modal"
                                style="background-color: #e2e8f0; color: #64748b; border: none; border-radius: 8px; font-weight: 600;">
                                Cancel
                            </button>
                            <button type="button" class="btn px-4 py-2 d-flex align-items-center btnclicky"
                                id="confirmRefundBtn"
                                style="background-color: #dc2626; color: white; border: none; border-radius: 8px; font-weight: 600; box-shadow: 0 2px 5px rgba(220, 38, 38, 0.3);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                    class="bi bi-arrow-counterclockwise me-2" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd"
                                        d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2v1z" />
                                    <path
                                        d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466z" />
                                </svg>
                                <span>Confirm Refund</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="auditTrailModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 transition-opacity bg-gray-800 bg-opacity-50"></div>

                    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-auto p-6 animate-fade-in">
                        <!-- Modal Header -->
                        <div class="flex justify-between items-center border-b pb-3">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                                    <i class="fas fa-clipboard-list text-yellow-500"></i>
                                    Audit Trail
                                </h2>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $auditLogs->count() }} {{ Str::plural('entry', $auditLogs->count()) }} recorded for
                                    this shipment
                                </p>
                            </div>
                            <button onclick="closeAuditModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <!-- Modal Content -->
                        <div class="mt-4 max-h-80 overflow-y-auto">
                            <ul class="divide-y divide-gray-200 text-sm">
                                @forelse($auditLogs as $log)
                                    @php
                                        $event = $log->event ? Str::headline($log->event) : 'Activity';
                                        $eventTone = match (strtolower($log->event)) {
                                            'created', 'create', 'recorded' => 'bg-green-100 text-green-700',
                                            'updated', 'update', 'modified' => 'bg-blue-100 text-blue-700',
                                            'deleted', 'delete', 'removed' => 'bg-red-100 text-red-700',
                                            'restored' => 'bg-purple-100 text-purple-700',
                                            default => 'bg-gray-100 text-gray-700',
                                        };
                                        $changes = collect($log->new_values ?? [])
                                            ->keys()
                                            ->merge(array_keys($log->old_values ?? []))
                                            ->unique()
                                            ->filter();
                                        $issuedBy = $log->user?->name ?? 'System';
                                    @endphp
                                    <li class="py-3">
                                        <div class="flex flex-col gap-1">
                                            <div class="flex justify-between items-start gap-3">
                                                <div class="flex flex-col gap-1">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $eventTone }}">
                                                            {{ $event }}
                                                        </span>
                                                        <span class="text-sm font-semibold text-gray-800">
                                                            {{ $issuedBy }}
                                                        </span>
                                                    </div>
                                                    @if($log->description)
                                                        <p class="text-xs text-gray-600 leading-snug">{{ $log->description }}</p>
                                                    @endif
                                                </div>
                                                <span class="text-xs text-gray-500 whitespace-nowrap">
                                                    {{ optional($log->created_at)->format('Y-m-d H:i') }}
                                                </span>
                                            </div>

                                            @if($changes->isNotEmpty())
                                                <div class="mt-2 space-y-1">
                                                    @foreach($changes as $field)
                                                        @php
                                                            $oldValue = data_get($log->old_values, $field);
                                                            $newValue = data_get($log->new_values, $field);
                                                            $formattedField = Str::headline($field);
                                                            $formattedOld = is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : (is_null($oldValue) ? 'null' : (string) $oldValue);
                                                            $formattedNew = is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : (is_null($newValue) ? 'null' : (string) $newValue);
                                                        @endphp
                                                        <div class="flex flex-wrap items-center gap-2 text-xs">
                                                            <span class="font-semibold text-gray-600">{{ $formattedField }}:</span>
                                                            @if($formattedOld !== $formattedNew && $formattedOld !== 'null')
                                                                <span class="text-red-500 line-through">{{ $formattedOld }}</span>
                                                                <i class="fas fa-arrow-right text-gray-300 text-[10px]"></i>
                                                            @endif
                                                            <span class="text-green-600 font-semibold">{{ $formattedNew }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                            <div class="flex flex-wrap items-center gap-2 text-[11px] text-gray-400 mt-2">
                                                @if($log->auditable_type)
                                                    <span class="inline-flex items-center gap-1">
                                                        <i class="fas fa-cube"></i>
                                                        {{ class_basename($log->auditable_type) }}#{{ $log->auditable_id }}
                                                    </span>
                                                @endif
                                                @if($log->ip_address)
                                                    <span class="inline-flex items-center gap-1">
                                                        <i class="fas fa-network-wired"></i>
                                                        {{ $log->ip_address }}
                                                    </span>
                                                @endif
                                                @if($log->user_agent)
                                                    <span class="inline-flex items-center gap-1">
                                                        <i class="fas fa-desktop"></i>
                                                        {{ Str::limit($log->user_agent, 60) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @empty
                                    <li class="py-6 text-center text-sm text-gray-500">
                                        No audit activity has been recorded for this shipment yet.
                                    </li>
                                @endforelse
                            </ul>
                        </div>

                        <!-- Modal Footer -->
                        <div class="mt-4 flex justify-end">
                            <button onclick="closeAuditModal()"
                                class="px-4 py-2 bg-gray-200 rounded-md text-gray-700 hover:bg-gray-300">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                // Define necessary variables for the mark as paid functionality
                if (typeof window.selectedShipmentId === 'undefined') {
                    window.selectedShipmentId = null;
                }
                
                // Always define originalTotal using window to make it globally accessible
                if (typeof window.originalTotal === 'undefined') {
                    window.originalTotal = parseFloat({{ $totalAmount }});
                } else {
                    // Update if it already exists (in case of page re-rendering)
                    window.originalTotal = parseFloat({{ $totalAmount }});
                }
                
                function openMarkPaidModal(shipmentId) {
                    window.selectedShipmentId = shipmentId;
                    // Reset discount fields when modal opens
                    const discountTypeEl = document.getElementById('discountType');
                    const discountValueEl = document.getElementById('discountValue');
                    const finalTotalEl = document.getElementById('finalTotal');
                    
                    if (discountTypeEl) {
                        discountTypeEl.value = '';
                    }
                    if (discountValueEl) {
                        discountValueEl.value = 0;
                    }
                    if (finalTotalEl) {
                        finalTotalEl.textContent = window.originalTotal.toFixed(2);
                    }
                    $('#markPaidModal').modal('show');
                }
                
                function computeFinalTotal() {
                    const discountTypeEl = document.getElementById('discountType');
                    const discountValueEl = document.getElementById('discountValue');
                    const finalTotalEl = document.getElementById('finalTotal');
                    
                    if (!discountTypeEl || !discountValueEl || !finalTotalEl) {
                        return;
                    }
                    const type = discountTypeEl.value;
                    const discountVal = parseFloat(discountValueEl.value) || 0;
                    let finalTotal = window.originalTotal;

                    if (type === 'fixed') {
                        finalTotal = window.originalTotal - discountVal;
                    } else if (type === 'percent') {
                        finalTotal = window.originalTotal - (window.originalTotal * (discountVal / 100));
                    }

                    if (finalTotal < 0) finalTotal = 0;

                    finalTotalEl.textContent = finalTotal.toFixed(2);
                }
                
                const auditBtn = document.getElementById("auditTrailBtn");
                const auditModal = document.getElementById("auditTrailModal");

                auditBtn.addEventListener("click", () => {
                    auditModal.classList.remove("hidden");
                });

                function closeAuditModal() {
                    auditModal.classList.add("hidden");
                }
            </script>

            <style>
                .animate-fade-in {
                    animation: fadeIn 0.25s ease-in-out;
                }

                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: scale(0.95);
                    }

                    to {
                        opacity: 1;
                        transform: scale(1);
                    }
                }

                .select2-container {

                    display: flex;
                    flex: 1;

                }

                .selection {
                    width: 100%;
                }

                .select2-container--default .select2-selection--single {
                    border: 1px solid #ced4da;
                    padding: 0.76875rem 1.75rem;
                    height: calc(2.9rem + 2px);
                }
            </style>

            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    // ---------- Elements ----------
                    const discountTypeEl = document.getElementById('discountType');
                    const discountValueEl = document.getElementById('discountValue');
                    const percentSymbolEl = document.getElementById('percentSymbol'); // may exist (d-none) in markup
                    const originalTotalEl = document.getElementById('originalTotal');
                    const finalTotalEl = document.getElementById('finalTotal');

                    const paymentsContainer = document.getElementById('payment-rows');
                    const paymentTpl = document.getElementById('payment-row-tpl') ? document.getElementById('payment-row-tpl').content : null;
                    const confirmBtn = document.getElementById('confirmMarkPaidBtn');
                    const form = document.getElementById('markPaidForm');

                    // Guard: required elements
                    if (!originalTotalEl || !finalTotalEl || !paymentsContainer || !paymentTpl || !confirmBtn || !form) {
                        // missing expected elements — abort silently
                        return;
                    }

                    // Parse numeric original total safe from Blade-rendered text (strip commas)
                    const rawOriginal = originalTotalEl.textContent.trim().replace(/,/g, '');
                    const originalTotal = parseFloat(rawOriginal) || 0;

                    // Create/insert paymentsTotal + remaining + status elements into the summary card near finalTotal
                    // If the page already has paymentsTotal/remaining, we'll reuse them.
                    let paymentsTotalEl = document.getElementById('paymentsTotal');
                    let remainingEl = document.getElementById('remainingTotal');
                    let remainingHintEl = document.getElementById('remainingHint');
                    let fillRemainingBtnEl = document.getElementById('fillRemainingBtn');
                    let statusEl = document.getElementById('paymentsStatus');

                    // Try to place them under the finalTotal if not present
                    if (!paymentsTotalEl || !remainingEl || !statusEl) {
                        const summaryCard = finalTotalEl.closest('.card') || finalTotalEl.parentElement;
                        if (summaryCard) {
                            // payments total row
                            const paymentsRow = document.createElement('div');
                            paymentsRow.className = 'd-flex justify-content-between mt-2';
                            paymentsRow.innerHTML = `<span class="fw-medium text-muted">Payments Total:</span>
                                   <span id="paymentsTotal" class="fw-medium">0.00</span>`;
                            summaryCard.appendChild(paymentsRow);

                            // remaining row
                            const remainingRow = document.createElement('div');
                            remainingRow.className = 'd-flex justify-content-between mt-1';
                            remainingRow.innerHTML = `<span class="fw-medium text-muted">Remaining:</span>
                                    <span id="remainingTotal" class="fw-bold">0.00</span>`;
                            summaryCard.appendChild(remainingRow);

                            // status message (for validation)
                            const statusRow = document.createElement('div');
                            statusRow.className = 'mt-2';
                            statusRow.innerHTML = `<small id="paymentsStatus" class="text-muted"></small>`;
                            summaryCard.appendChild(statusRow);

                            const helperRow = document.createElement('div');
                            helperRow.className = 'd-flex justify-content-between align-items-center mt-2 gap-2 flex-wrap';
                            helperRow.innerHTML = `
                                <small class="text-muted">Outstanding due: <strong id="remainingHint">0.00</strong></small>
                                <button type="button" id="fillRemainingBtn" class="btn btn-sm btn-outline-primary px-3 py-1">
                                    Auto-fill Remaining
                                </button>
                            `;
                            summaryCard.appendChild(helperRow);

                            // reassign handles
                            paymentsTotalEl = document.getElementById('paymentsTotal');
                            remainingEl = document.getElementById('remainingTotal');
                            remainingHintEl = document.getElementById('remainingHint');
                            fillRemainingBtnEl = document.getElementById('fillRemainingBtn');
                            statusEl = document.getElementById('paymentsStatus');
                        }
                    }

                    // Grab HTML options from first server-rendered select to clone later
                    const firstSelect = paymentsContainer.querySelector('select[name="method_of_payment[]"]');
                    const optionsHtml = firstSelect ? firstSelect.innerHTML : '';

                    // Utility: format currency to 2 decimals
                    function fmt(v) {
                        return Number(v || 0).toFixed(2);
                    }

                    // Update final total based on discount inputs
                    function updateFinalTotal() {
                        const discountType = discountTypeEl ? discountTypeEl.value : '';
                        const discountValue = discountValueEl ? (parseFloat(discountValueEl.value) || 0) : 0;
                        let final = originalTotal;

                        if (discountType === 'fixed') {
                            final = Math.max(0, originalTotal - discountValue);
                        } else if (discountType === 'percent') {
                            final = originalTotal * (1 - (discountValue / 100));
                        }

                        // Update percent symbol visibility if present
                        if (percentSymbolEl) {
                            if (discountType === 'percent') percentSymbolEl.classList.remove('d-none');
                            else percentSymbolEl.classList.add('d-none');
                        }

                        finalTotalEl.textContent = fmt(final);
                        return final;
                    }

                    // Update payments total from all amount inputs
                    function updatePaymentsTotal() {
                        const amountInputs = paymentsContainer.querySelectorAll('input[name="payment_amount[]"]');
                        let total = 0;
                        amountInputs.forEach((inp) => {
                            const v = parseFloat(inp.value);
                            if (!isNaN(v) && isFinite(v)) total += v;
                        });
                        if (paymentsTotalEl) paymentsTotalEl.textContent = fmt(total);
                        return total;
                    }

                    // Update remaining (final - payments), color it red when under- or over-paid, green when exact
                    function updateRemainingAndValidation() {
                        const final = updateFinalTotal();
                        const paymentsTotal = updatePaymentsTotal();
                        const remaining = final - paymentsTotal;
                        if (remainingEl) {
                            remainingEl.textContent = fmt(remaining);
                            // Color logic: red when not zero (under or overpaid), green when exactly zero
                            const isExact = Math.abs(remaining) < 0.005; // tolerance for floating point
                            if (isExact) {
                                remainingEl.style.color = '#16a34a'; // green
                            } else {
                                remainingEl.style.color = 'crimson';
                            }
                        }
                        if (remainingHintEl) {
                            remainingHintEl.textContent = fmt(Math.max(remaining, 0));
                        }

                        // Validation: payments must equal final exactly (within tolerance) to enable confirm
                        const valid = Math.abs(remaining) < 0.005;
                        if (confirmBtn) {
                            confirmBtn.disabled = !valid;
                            if (!valid) {
                                confirmBtn.setAttribute('aria-disabled', 'true');
                                confirmBtn.classList.add('disabled');
                            } else {
                                confirmBtn.removeAttribute('aria-disabled');
                                confirmBtn.classList.remove('disabled');
                            }
                        }

                        // Status message
                        if (statusEl) {
                            if (valid) {
                                statusEl.textContent = 'Payments match the final total. You can confirm the payment.';
                                statusEl.style.color = '#16a34a';
                            } else if (remaining > 0) {
                                statusEl.textContent = `Remaining ${fmt(remaining)} — please add payments to fully cover the final total.`;
                                statusEl.style.color = 'crimson';
                            } else {
                                // remaining < 0 => overpaid
                                statusEl.textContent = `Overpaid by ${fmt(Math.abs(remaining))} — adjust payments.`;
                                statusEl.style.color = 'crimson';
                            }
                        }
                        return { final, paymentsTotal, remaining, valid };
                    }

                    // Ensure at least one row exists
                    function ensureAtLeastOneRow() {
                        const rows = paymentsContainer.querySelectorAll('.payment-row');
                        if (rows.length === 0 && paymentTpl) {
                            const clone = document.importNode(paymentTpl, true);
                            const sel = clone.querySelector('select[name="method_of_payment[]"]');
                            if (sel && optionsHtml) sel.innerHTML = optionsHtml;
                            paymentsContainer.appendChild(clone);
                        }
                    }

                    // Add/remove row handlers (delegated)
                    paymentsContainer.addEventListener('click', function (ev) {
                        const addBtn = ev.target.closest('.add-payment');
                        const removeBtn = ev.target.closest('.remove-payment');

                        if (addBtn) {
                            const clone = document.importNode(paymentTpl, true);
                            const sel = clone.querySelector('select[name="method_of_payment[]"]');
                            if (sel && optionsHtml) sel.innerHTML = optionsHtml;
                            paymentsContainer.appendChild(clone);
                            // focus last amount
                            const lastAmt = paymentsContainer.querySelector('.payment-row:last-of-type input[name="payment_amount[]"]');
                            if (lastAmt) lastAmt.focus();
                            updateRemainingAndValidation();
                        } else if (removeBtn) {
                            const row = removeBtn.closest('.payment-row');
                            const rows = paymentsContainer.querySelectorAll('.payment-row');
                            if (!row) return;
                            if (rows.length > 1) {
                                row.remove();
                            } else {
                                // clear fields if only one row left
                                const s = row.querySelector('select[name="method_of_payment[]"]');
                                const a = row.querySelector('input[name="payment_amount[]"]');
                                if (s) s.value = '';
                                if (a) a.value = '';
                            }
                            updateRemainingAndValidation();
                        }
                    });

                    // Recalculate when amount inputs change or when discount changes
                    paymentsContainer.addEventListener('input', function (ev) {
                        if (ev.target && ev.target.matches('input[name="payment_amount[]"]')) {
                            updateRemainingAndValidation();
                        }
                    });

                    // Recalculate on select change (in case you later add behavior)
                    paymentsContainer.addEventListener('change', function (ev) {
                        if (ev.target && ev.target.matches('select[name="method_of_payment[]"]')) {
                            // placeholder — no numeric effect now, but keep stable
                        }
                    });

                    // Discount inputs change handlers
                    if (discountTypeEl) discountTypeEl.addEventListener('change', updateRemainingAndValidation);
                    if (discountValueEl) discountValueEl.addEventListener('input', updateRemainingAndValidation);

                    // Prevent form submission unless payments exactly match final total
                    form.addEventListener('submit', function (ev) {
                        const { valid } = updateRemainingAndValidation();
                        if (!valid) {
                            ev.preventDefault();
                            ev.stopPropagation();
                            // optional: focus on the paymentsStatus so user sees message
                            if (statusEl) statusEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            return false;
                        }
                        // else allow submission
                        return true;
                    });

                    // Also handle Confirm button click (it may be not a submit button in your markup)
                    confirmBtn.addEventListener('click', function (ev) {
                        const { valid } = updateRemainingAndValidation();
                        if (!valid) {
                            // show a short shaking feedback or flash (simple approach: focus status)
                            if (statusEl) {
                                statusEl.focus?.();
                                statusEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                            return;
                        }
                        
                        // Prepare the data for AJAX call
                        const discountType = discountTypeEl ? discountTypeEl.value : '';
                        const discountValue = discountValueEl ? parseFloat(discountValueEl.value) || 0 : 0;
                        const finalTotal = finalTotalEl ? parseFloat(finalTotalEl.textContent) : window.originalTotal;
                        
                        // Get multiple payment methods and amounts
                        const paymentMethods = [];
                        const paymentAmounts = [];
                        
                        const methodSelects = paymentsContainer.querySelectorAll('select[name="method_of_payment[]"]');
                        const amountInputs = paymentsContainer.querySelectorAll('input[name="payment_amount[]"]');
                        
                        methodSelects.forEach((select, index) => {
                            const method = select.value;
                            if (method && amountInputs[index]) {
                                const amount = parseFloat(amountInputs[index].value);
                                if (amount && amount > 0) {
                                    paymentMethods.push(method);
                                    paymentAmounts.push(amount);
                                }
                            }
                        });
                        
                        // Validate that we have at least one payment
                        if (paymentMethods.length === 0) {
                            if (statusEl) {
                                statusEl.textContent = 'No valid payments to process. Please add at least one payment method and amount.';
                                statusEl.style.color = 'crimson';
                                statusEl.focus?.();
                                statusEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                            return;
                        }
                        
                        // Disable the button and show loading state
                        confirmBtn.disabled = true;
                        confirmBtn.classList.add('btn-loading');
                        const originalButtonHtml = confirmBtn.innerHTML;
                        confirmBtn.innerHTML = '<div class="spinner-border spinner-border-sm text-white" role="status"></div>';
                        
                        // The selectedShipmentId variable is set when the modal is opened
                        // If it's not available, we can try to get it from a data attribute or the URL
                        if (!window.selectedShipmentId) {
                            // Try to get shipment ID from the URL or other source
                            // Extract from URL: /shipments/123 -> shipment ID is 123
                            const path = window.location.pathname;
                            const pathParts = path.split('/');
                            const shipmentIdFromUrl = pathParts[pathParts.indexOf('shipments') + 2]; // Get ID after 'shipments'
                            
                            if (shipmentIdFromUrl) {
                                window.selectedShipmentId = parseInt(shipmentIdFromUrl);
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: 'Shipment ID not available. Please close and reopen the modal.'
                                });
                                return;
                            }
                        }
                        
                        // Make AJAX call
                        fetch('{{ route('api.mark-as-paid') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                shipment_id: window.selectedShipmentId,
                                discount_type: discountType,
                                discount_value: discountValue,
                                final_total: finalTotal,
                                method_of_payment: paymentMethods,
                                payment_amount: paymentAmounts,
                                current_user: '{{ auth()->user()->name ?? 'System' }}',
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.transaction) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: data.message,
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: data.message || data.error
                                }).then(() => {
                                    window.location.reload();
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Failed to mark as paid. Please try again.'
                            });
                        })
                        .finally(() => {
                            // Restore button state
                            confirmBtn.disabled = false;
                            confirmBtn.classList.remove('btn-loading');
                            confirmBtn.innerHTML = originalButtonHtml;
                        });
                    });

                    if (fillRemainingBtnEl) {
                        fillRemainingBtnEl.addEventListener('click', function () {
                            const { remaining } = updateRemainingAndValidation();
                            if (remaining <= 0) {
                                return;
                            }

                            const amountInputs = Array.from(paymentsContainer.querySelectorAll('input[name="payment_amount[]"]'));
                            if (amountInputs.length === 0) {
                                return;
                            }

                            let target = amountInputs.find((input) => {
                                const value = parseFloat(input.value);
                                return !input.value || isNaN(value) || value === 0;
                            }) || amountInputs[0];

                            const current = parseFloat(target.value) || 0;
                            target.value = (current + remaining).toFixed(2);
                            target.dispatchEvent(new Event('input', { bubbles: true }));
                            target.focus();
                        });
                    }

                    // Initialize
                    ensureAtLeastOneRow();
                    // Small debounce guard to allow DOM to settle (in case Blade rendered values)
                    setTimeout(updateRemainingAndValidation, 30);
                });
            </script>


            @include('cargo::adminLte.pages.shipments._partials.cargo-payment-modal')
        </div>
    </div>
@endsection
@include('cargo::adminLte.pages.shipments._partials.bottom-assets')
