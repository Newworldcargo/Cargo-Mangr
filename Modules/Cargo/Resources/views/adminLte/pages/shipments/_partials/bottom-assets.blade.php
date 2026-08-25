
{{-- Inject styles --}}
@section('styles')
    <style>
        .timeline .timeline-content {
            width: auto;
        }
        .timeline-label{
            margin-right: 6px;
            padding-right: 6px;
            border-right: solid 3px #eff2f5;
        }
        .timeline-label:before{
            width: unset;
        }
        .btn-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.8;
        }
        .btn-loading .spinner-border {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }
        .btn-loading span {
            visibility: hidden;
        }
    </style>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function copyToClipboard(element) {
            var $temp = $("<input>");
            $("body").append($temp);
            $temp.val($(element).text()).select();
            document.execCommand("copy");
            $temp.remove();
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: "{{ __('cargo::view.payment_link_copied') }}",
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        }
            
        let selectedShipmentId = window.selectedShipmentId || null;
        const refundMaxAmount = parseFloat({{ $remainingRefundAmount ?? $totalAmount }});

        const refundTypeEl = document.getElementById('refundType');
        const refundAmountEl = document.getElementById('refundAmount');
        const refundSummaryEl = document.getElementById('refundSummaryAmount');
        const refundReasonEl = document.getElementById('refundReason');
        const confirmRefundBtn = document.getElementById('confirmRefundBtn');
        const refundButtonIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-counterclockwise me-2" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 3a5 5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2v1z"/><path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466z"/></svg>';
        function normalizeRefundUrl(url) {
            if (!url) {
                return url;
            }
            try {
                const parsed = new URL(url, window.location.origin);
                return parsed.pathname + parsed.search + parsed.hash;
            } catch (error) {
                return url;
            }
        }

        let refundAction = {
            url: normalizeRefundUrl(`{{ fr_route('shipments.refund-payment', [], false) }}`),
            mode: 'direct',
            label: 'Confirm Refund',
        };

        function setRefundButtonLabel(label) {
            if (!confirmRefundBtn) {
                return;
            }
            confirmRefundBtn.innerHTML = refundButtonIcon + '<span>' + label + '</span>';
        }

        function setRefundAction(trigger) {
            if (!trigger || !trigger.dataset) {
                return;
            }
            refundAction = {
                url: normalizeRefundUrl(trigger.dataset.refundUrl || refundAction.url),
                mode: trigger.dataset.refundAction || refundAction.mode,
                label: trigger.dataset.refundLabel || refundAction.label,
            };
            setRefundButtonLabel(refundAction.label);
        }

        function updateRefundSummary() {
            if (!refundAmountEl || !refundSummaryEl || !refundTypeEl) {
                return;
            }
            let amount = parseFloat(refundAmountEl.value) || 0;
            if (refundTypeEl.value === 'full') {
                amount = refundMaxAmount;
                refundAmountEl.value = refundMaxAmount.toFixed(2);
                refundAmountEl.setAttribute('disabled', 'disabled');
            } else {
                refundAmountEl.removeAttribute('disabled');
                if (amount > refundMaxAmount) {
                    amount = refundMaxAmount;
                    refundAmountEl.value = refundMaxAmount.toFixed(2);
                }
            }
            refundSummaryEl.textContent = amount.toFixed(2);
        }

        if (refundTypeEl) {
            refundTypeEl.addEventListener('change', updateRefundSummary);
        }
        if (refundAmountEl) {
            refundAmountEl.addEventListener('input', updateRefundSummary);
        }

        // Function to open refund modal
        function openRefundModal(shipmentId, trigger) {
            selectedShipmentId = shipmentId;
            setRefundAction(trigger);
            if (refundTypeEl) {
                refundTypeEl.value = 'full';
            }
            if (refundAmountEl) {
                refundAmountEl.value = refundMaxAmount.toFixed(2);
            }
            if (refundReasonEl) {
                refundReasonEl.value = '';
            }
            updateRefundSummary();
            $('#refundModal').modal('show');
        }

        if (confirmRefundBtn) {
            confirmRefundBtn.addEventListener('click', function () {
                const btn = this;
                btn.classList.add('btn-loading');
                btn.innerHTML = '<div class="spinner-border spinner-border-sm text-white" role="status"></div>';

                const refundReason = refundReasonEl ? refundReasonEl.value : '';
                const refundType = refundTypeEl ? refundTypeEl.value : 'full';
                const refundAmount = refundAmountEl ? parseFloat(refundAmountEl.value) || 0 : 0;

                if (!refundReason.trim()) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Warning!',
                        text: 'Please provide a reason for the refund.'
                    });
                    btn.classList.remove('btn-loading');
                    setRefundButtonLabel(refundAction.label);
                    return;
                }

                if (refundType === 'partial' && (refundAmount <= 0 || refundAmount > refundMaxAmount)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Warning!',
                        text: 'Refund amount must be greater than zero and cannot exceed the paid amount.'
                    });
                    btn.classList.remove('btn-loading');
                    setRefundButtonLabel(refundAction.label);
                    return;
                }

                fetch(refundAction.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        shipment_id: selectedShipmentId,
                        reason: refundReason,
                        refund_type: refundType,
                        amount: refundAmount,
                    }),
                })
                .then(async (response) => {
                    const contentType = response.headers.get('content-type') || '';
                    if (contentType.includes('application/json')) {
                        const data = await response.json();
                        if (!response.ok) {
                            const errorValues = data.errors ? Object.values(data.errors) : [];
                            const firstError = errorValues.length && errorValues[0].length ? errorValues[0][0] : null;
                            const message = data.message || firstError || 'Failed to process refund.';
                            throw new Error(message);
                        }
                        return data;
                    }
                    const text = await response.text();
                    const error = new Error('Unexpected response from server. Please refresh and try again.');
                    error.responseText = text;
                    throw error;
                })
                .then(data => {
                    $('#refundModal').modal('hide');
                    if (data.success) {
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
                            text: data.message
                        });
                    }
                })
                .catch(error => {
                    console.error(error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: error && error.message ? error.message : 'Failed to process refund.'
                    });
                })
                .finally(() => {
                    btn.classList.remove('btn-loading');
                    setRefundButtonLabel(refundAction.label);
                });
            });
        }
    </script>
@endsection
