@php
    $checkoutBranch = auth()->user()->role == 3
        ? \Modules\Cargo\Entities\Branch::where('user_id', auth()->id())->first()
        : null;
    $checkoutCurrency = strtoupper($checkoutBranch?->default_currency ?: ($shipment->branch?->default_currency ?: 'ZMW'));
    $checkoutSymbol = currency_symbol_for($checkoutCurrency);
@endphp
<div id="checkoutModal" class="checkout-modal">
    <div class="checkout-modal-content">
        <span class="close" onclick="closeCheckoutModal()">&times;</span>

        <form id="checkoutForm" action="{{ route('payment.checkout') }}" method="POST" class="wizard" data-shipment-id="{{ $shipment->id }}">
            @csrf
            <input type="hidden" name="shipment_id" value="{{$shipment->id}}">
            <!-- Step 1: Update Payment Method -->
            <!-- Step 1: Update Payment Method -->
            <div class="wizard-step active" id="step1">
                <h3>Update Payment Method</h3>
                <p>Select your preferred payment method below.</p>

                <div class="payment-methods">
                    <button type="button" class="payment-method" data-method="credit_card">
                        <i class="fa fa-credit-card"></i> Credit Card
                    </button>
                    <button type="button" class="payment-method" data-method="paypal_payment">
                        <i class="fa fa-paypal"></i> PayPal
                    </button>
                    {{-- <button type="button" class="payment-method" data-method="stripe_payment">
                        <i class="fa fa-stripe"></i> Stripe
                    </button> --}}
                    @if ($checkoutCurrency === 'ZMW')
                        <button type="button" class="payment-method" data-method="mobile">
                            <i class="fa fa-mobile"></i> Mobile Money
                        </button>
                    @endif
                </div>

                @if ($checkoutCurrency === 'ZMW')
                    <div id="mobile-money-input" style="display: none;">
                    <h4>Mobile Network:</h4>
                    <div class="mobile-networks">
                        <button type="button" class="mobile-network" data-network="AIRTEL_OAPI_ZMB">
                            Airtel
                        </button>
                        <button type="button" class="mobile-network" data-network="MTN_MOMO_ZMB">
                            MTN
                        </button>
                        <button type="button" class="mobile-network" data-network="ZAMTEL_ZMB">
                            Zamtel
                        </button>
                    </div>
                    <div id="mobile-money-input2">
                        <label>Phone Number:</label>
                        <input type="text" id="phone-number" placeholder="ex. 772147755" class="form-control" name="phone">
                    </div>
                    </div>
                @endif

                <input type="hidden" id="payment-method-selected" name="payment_method">
                <input type="hidden" id="mobile-network-selected" name="correspondant">

                <button type="button" class="btn btn-primary next-btn" onclick="updateShipment()">Next</button>
            </div>


            <!-- Step 2: Shipment Summary -->
            <div class="wizard-step" id="step2">
                <h3>Billing Information</h3>
                <p>Review your shipment details before proceeding.</p>

                <div id="shipment-summary">
                    <p><strong>Tracking ID:</strong> <span id="tracking-id"></span></p>
                    <p><strong>Recipient:</strong> <span id="recipient"></span></p>
                    <p><strong>Address:</strong> <span id="address"></span></p>
                    <p id="usd-base-row" style="display: none;"><strong>USD Price Before Conversion:</strong> <span id="usd-base-amount"></span></p>
                    <p><strong>Total Amount:</strong> <span id="amount"></span></p>
                </div>

                <button type="button" class="btn btn-secondary prev-btn" onclick="prevStep(2)">Back</button>
                <button type="button" class="btn btn-primary next-btn" onclick="nextStep(2)">Next</button>
            </div>

            <!-- Step 3: Confirm & Pay -->
            <div class="wizard-step" id="step3">
                <h3>Confirm & Pay</h3>
                <p>Click "Pay Now" to complete your payment.</p>

                <button type="button" class="btn btn-secondary prev-btn" onclick="prevStep(3)">Back</button>
                <button type="submit" class="btnclicky btn btn-success">Pay Now</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Modal styles */
    .checkout-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        overflow: auto; /* Enable scroll if content is too long */
    }

    .checkout-modal-content {
        background: #fff;
        padding: 30px;
        width: 60%; /* Wider modal */
        max-width: 700px; /* Ensure it doesn't get too wide on large screens */
        margin: 5% auto; /* Reduced top margin, centered */
        border-radius: 12px; /* More rounded corners */
        text-align: left; /* Align text to the left */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15); /* Subtle shadow */
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-20px); } /* More pronounced entrance */
        to { opacity: 1; transform: translateY(0); }
    }

    .close {
        float: right;
        font-size: 28px; /* Larger close button */
        cursor: pointer;
        color: #888; /* Gray color */
        transition: color 0.2s; /* Smooth transition */
    }

    .close:hover {
        color: #333; /* Darker on hover */
    }

    .wizard-step {
        display: none;
        padding: 20px 0; /* Add some vertical spacing */
    }

    .wizard-step.active {
        display: block;
    }

    /* Modern Button Styles */
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        cursor: pointer;
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    }

    .btn:hover {
        transform: translateY(-2px); /* Slight lift on hover */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.12);
    }

    .btn:focus {
        outline: none; /* Remove default focus outline */
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.3); /* Add a custom focus ring */
    }

    .btn-primary {
        background-color: #007bff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #0056b3;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #545b62;
    }

    .btn-success {
        background-color: #28a745;
        color: white;
    }

    .btn-success:hover {
        background-color: #1e7e34;
    }

    .next-btn,
    .prev-btn {
        margin-top: 20px;
        min-width: 120px;
    }

    /* Payment Method and Mobile Network Styles */
    .payment-methods,
    .mobile-networks {
        display: flex;
        justify-content: space-around;
        margin-bottom: 20px;
    }

    .payment-method,
    .mobile-network {
        padding: 12px 20px;
        border: 1px solid #ddd; /* Add a border */
        border-radius: 8px;
        background-color: #f9f9f9;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        text-align: center; /* Center the content */
        flex: 1; /* Distribute space evenly */
        margin: 0 5px; /* Add spacing between buttons */
    }

    .payment-method:hover,
    .mobile-network:hover {
        background-color: #f0f0f0;
        border-color: #bbb;
        transform: translateY(-1px); /* Slight lift */
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); /* Subtle shadow */
    }

    .payment-method.active,
    .mobile-network.active {
        background-color: #007bff;
        color: white;
        border-color: #007bff;
    }

    .payment-method i {
        margin-right: 5px;
        font-size: 1.2em;
    }

    #mobile-money-input {
        margin-top: 20px;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
    }

    #mobile-money-input h4 {
        margin-bottom: 10px;
        font-size: 1.2em;
        color: #333;
        text-align: center;
    }

    /* Form Control Styles */
    .form-control {
        width: 100%;
        padding: 12px;
        margin-bottom: 15px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 16px;
        box-sizing: border-box; /* Include padding and border in element's total width and height */
    }

    .form-control:focus {
        outline: none;
        border-color: #ffdd00;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
    }

    /* Shipment Summary Styles */
    #shipment-summary {
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
        margin-bottom: 20px;
    }

    #shipment-summary p {
        margin-bottom: 8px;
        font-size: 16px;
        color: #555;
    }

    #shipment-summary strong {
        font-weight: bold;
        color: #333;
        margin-right: 5px;
    }

    /* General Heading and Paragraph Styles */
    h3 {
        font-size: 24px;
        color: #333;
        margin-bottom: 15px;
        text-align: center;
    }

</style>

<script>
const shipmentId = document.querySelector('.wizard').dataset.shipmentId;

// Open Modal
function openCheckoutModal() {
    document.getElementById('checkoutModal').style.display = 'block';
}

// Close Modal
function closeCheckoutModal() {
    document.getElementById('checkoutModal').style.display = 'none';
}

// Step Navigation
function nextStep(step) {
    document.getElementById('step' + step).classList.remove('active');
    document.getElementById('step' + (step + 1)).classList.add('active');
}

function prevStep(step) {
    document.getElementById('step' + step).classList.remove('active');
    document.getElementById('step' + (step - 1)).classList.add('active');
}

// Update Shipment via AJAX (Step 1)
function updateShipment() {
    const paymentMethod = document.getElementById('payment-method-selected').value;

    fetch("{{ route('shipments.payment.update') }}", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            // "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ 
            payment_method: paymentMethod, 
            shipment_id: shipmentId 
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log(data);
            // Populate shipment details in Step 2
            document.getElementById('tracking-id').textContent = data.shipment.code; // Use correct field
            document.getElementById('recipient').textContent = data.shipment.reciver_name; // Use correct field
            document.getElementById('address').textContent = data.shipment.reciver_address;
            const amount = Number(data.display_amount ?? data.shipment.amount_to_be_collected ?? 0);
            const symbol = data.display_symbol ?? @json($checkoutSymbol);
            const currency = data.display_currency ?? @json($checkoutCurrency);
            document.getElementById('amount').textContent = `${symbol}${amount.toFixed(2)} ${currency}`;
            const usdBaseRow = document.getElementById('usd-base-row');
            const usdBaseAmount = document.getElementById('usd-base-amount');
            const baseAmount = Number(data.base_amount ?? data.shipment.amount_to_be_collected ?? 0);
            if (usdBaseRow && usdBaseAmount && currency !== 'USD') {
                usdBaseAmount.textContent = `$${baseAmount.toFixed(2)} USD`;
                usdBaseRow.style.display = '';
            } else if (usdBaseRow) {
                usdBaseRow.style.display = 'none';
            }

            nextStep(1);
        } else {
            alert("Failed to update shipment. Try again.");
        }
    })
    .catch(error => console.error("Error updating shipment:", error));
}


// Handle payment method selection
document.querySelectorAll('.payment-method').forEach(button => {
    button.addEventListener('click', function() {
        // Hide all other buttons' active state
        document.querySelectorAll('.payment-method').forEach(otherButton => {
            otherButton.classList.remove('active');
        });

        // Add active state to the selected button
        this.classList.add('active');

        // Update the hidden input with the selected method
        document.getElementById('payment-method-selected').value = this.getAttribute('data-method');

        // Show/hide mobile money input based on selection
        if (this.getAttribute('data-method') === 'mobile') {
            const mobileMoneyInput = document.getElementById('mobile-money-input');
            const mobileMoneyInput2 = document.getElementById('mobile-money-input2');
            if (mobileMoneyInput) {
                mobileMoneyInput.style.display = 'block';
            }
            if (mobileMoneyInput2) {
                mobileMoneyInput2.style.display = 'none';
            }
        } else {
            const mobileMoneyInput = document.getElementById('mobile-money-input');
            if (mobileMoneyInput) {
                mobileMoneyInput.style.display = 'none';
            }
            document.getElementById('mobile-network-selected').value = '';
        }
    });
});

// Handle mobile network selection
document.querySelectorAll('.mobile-network').forEach(button => {
    button.addEventListener('click', function() {
        // Hide all other buttons' active state
        document.querySelectorAll('.mobile-network').forEach(otherButton => {
            otherButton.classList.remove('active');
        });

        // Add active state to the selected button
        this.classList.add('active');

        // Update the hidden input with the selected network
        document.getElementById('mobile-network-selected').value = this.getAttribute('data-network');
        document.getElementById('mobile-money-input2').style.display = 'block';
    });
});

const phoneNumberInput = document.getElementById('phone-number');
if (phoneNumberInput) {
phoneNumberInput.addEventListener('input', function(e) {
    let inputValue = e.target.value;

    // Remove all non-numeric characters
    inputValue = inputValue.replace(/\D/g, '');

    // Limit the length to 12 digits
    if (inputValue.length > 12) {
        inputValue = inputValue.substring(0, 12);
    }

    // Prefix with '260' if not already present
    if (inputValue.length <= 10 && !inputValue.startsWith('260')) {
        inputValue = '260' + inputValue;
    }

    // Update the value in the input field
    e.target.value = inputValue;
});
}

</script>
