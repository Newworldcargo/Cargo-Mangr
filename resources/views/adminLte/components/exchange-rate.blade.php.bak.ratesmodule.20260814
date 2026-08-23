<div class="content" style="margin-left: 20%; width:80%">
@if(session('success'))
    <div class="alert alert-sm alert-success alert-dismissible fade show shadow-sm border-left-success text-white" role="alert">
        <strong>Success!</strong> {{ session('success') }}
        <button type="button" class="close text-white" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif
</div>

<div class="modal fade" id="currencyModal" tabindex="-1" role="dialog" aria-labelledby="currencyModalLabel" aria-hidden="true">
    <div style="z-index: 9999" class="modal-dialog modal-lg" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="currencyModalLabel">
                    <i class="fas fa-exchange-alt mr-2"></i>Update Currency Exchange Rates
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="currencyRatesForm" action="{{ route('currency.update_rates') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <!-- Real-time Exchange Rate Display -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="d-flex align-items-center">
                                        <div class="currency-pair">
                                            <span class="h4 mb-0">USD/ZMW</span>
                                        </div>
                                        <div class="ml-3">
                                            <div class="d-flex align-items-baseline">
                                                <span class="h3 mb-0" id="realTimeRate">Loading...</span>
                                                <small class="text-muted ml-2" id="rateStatus"></small>
                                            </div>
                                            <div class="rate-info mt-1">
                                                <small class="text-muted d-block" id="lastUpdated">Last updated: -</small>
                                                <small class="text-muted d-block" id="timeZone"></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-right">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="refreshRateBtn" onclick="fetchRealTimeRate()">
                                        <i class="fas fa-sync-alt mr-1"></i>Refresh Rate
                                        <span class="badge badge-light ml-1" id="refreshCount">3</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="fromCurrency" class="small font-weight-bold text-primary mb-1">From Currency</label>
                                <input disabled type="text" class="form-control" id="fromCurrency" name="from_currency" placeholder="e.g. USD" value="USD" style="font-size: 0.85rem; height: calc(1.5em + 0.75rem);">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="toCurrency" class="small font-weight-bold text-primary mb-1">To Currency</label>
                                <input disabled type="text" class="form-control" id="toCurrency" name="to_currency" placeholder="e.g. ZMW" value="ZMW" style="font-size: 0.85rem; height: calc(1.5em + 0.75rem);">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="exchangeRate" class="small font-weight-bold text-primary mb-1">Exchange Rate</label>
                                <input type="number" class="form-control form-control-sm" value="{{ current_x_rate() }}" id="exchangeRate" name="exchange_rate" placeholder="e.g. 27.4" step="0.0001" min="0" style="height: calc(1.5em + 0.55rem);">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    @can('reset-exchange-rates')
                    <button id="deleteItemBtn" type="button" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-times mr-1"></i>Reset
                    </button>
                    @endcan

                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Close
                    </button>
                    @can('edit-exchange-rates')
                    <button type="submit" class="btn btn-primary btn-sm" data-show-loader="true">
                        <i class="fas fa-save mr-1"></i>Save Changes
                    </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let refreshCount = 3;
    let lastRefreshTime = null;
    const REFRESH_LIMIT = 3;
    const REFRESH_RESET_TIME = 3600000; // 1 hour in milliseconds

    // Function to update refresh button state
    function updateRefreshButtonState() {
        const refreshBtn = document.getElementById('refreshRateBtn');
        const refreshCountElement = document.getElementById('refreshCount');
        
        if (refreshCount <= 0) {
            refreshBtn.disabled = true;
            refreshBtn.classList.add('disabled');
            refreshCountElement.textContent = '0';
            
            // Calculate time until reset
            const timeUntilReset = REFRESH_RESET_TIME - (Date.now() - lastRefreshTime);
            const minutesUntilReset = Math.ceil(timeUntilReset / 60000);
            
            refreshBtn.title = `Please wait ${minutesUntilReset} minutes before refreshing again`;
        } else {
            refreshBtn.disabled = false;
            refreshBtn.classList.remove('disabled');
            refreshCountElement.textContent = refreshCount;
            refreshBtn.title = `${refreshCount} refreshes remaining`;
        }
    }

    // Function to check and reset refresh count
    function checkAndResetRefreshCount() {
        if (lastRefreshTime && (Date.now() - lastRefreshTime) >= REFRESH_RESET_TIME) {
            refreshCount = REFRESH_LIMIT;
            lastRefreshTime = Date.now();
            updateRefreshButtonState();
        }
    }

    // Function to fetch real-time exchange rate
    function fetchRealTimeRate() {
        if (refreshCount <= 0) {
            return;
        }

        const rateElement = document.getElementById('realTimeRate');
        const lastUpdatedElement = document.getElementById('lastUpdated');
        const timeZoneElement = document.getElementById('timeZone');
        const rateStatusElement = document.getElementById('rateStatus');
        
        rateElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        rateStatusElement.textContent = '';
        
        // Check cache first
        const cachedData = localStorage.getItem('exchangeRateData');
        const cacheTime = localStorage.getItem('exchangeRateTime');
        const now = Date.now();
        
        // Use cache if it's less than 5 minutes old
        if (cachedData && cacheTime && (now - parseInt(cacheTime)) < 300000) {
            const data = JSON.parse(cachedData);
            updateRateDisplay(data);
            return;
        }
        
        // Using ExchangeRate-API
        fetch('https://open.er-api.com/v6/latest/USD')
        .then(response => response.json())
        .then(data => {
            if (data && data.rates && data.rates.ZMW) {
                const formattedData = {
                    convert_result: {
                        rate: data.rates.ZMW
                    },
                    time_update: {
                        time_utc: data.time_last_update_utc,
                        time_zone: 'UTC'
                    }
                };

                // Cache the response
                localStorage.setItem('exchangeRateData', JSON.stringify(formattedData));
                localStorage.setItem('exchangeRateTime', now.toString());

                updateRateDisplay(formattedData);
                
                // Update refresh count
                refreshCount--;
                lastRefreshTime = Date.now();
                updateRefreshButtonState();
            } else {
                rateElement.textContent = 'Error fetching rate';
                rateStatusElement.textContent = 'Failed to fetch rate';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            rateElement.textContent = 'Error fetching rate';
            rateStatusElement.textContent = 'Network error';
            
            // If we have cached data, use it even if expired
            if (cachedData) {
                const data = JSON.parse(cachedData);
                updateRateDisplay(data);
                rateStatusElement.textContent = 'Using cached data';
            }
        });
    }

    function updateRateDisplay(data) {
        const rateElement = document.getElementById('realTimeRate');
        const lastUpdatedElement = document.getElementById('lastUpdated');
        const timeZoneElement = document.getElementById('timeZone');
        const rateStatusElement = document.getElementById('rateStatus');

        const rate = data.convert_result.rate;
        const updateTime = new Date(data.time_update.time_utc);

        rateElement.textContent = rate.toFixed(4);
        lastUpdatedElement.textContent = `Last updated: ${updateTime.toLocaleString()}`;
        timeZoneElement.textContent = `Time Zone: ${data.time_update.time_zone}`;
        rateStatusElement.textContent = 'Live Rate';

        // Update the exchange rate input with the real-time rate
        document.getElementById('exchangeRate').value = rate.toFixed(4);
    }

    // Fetch rate when modal opens
    $('#currencyModal').on('show.bs.modal', function () {
        checkAndResetRefreshCount();
        fetchRealTimeRate();
    });

    // Check refresh count every minute
    setInterval(checkAndResetRefreshCount, 60000);

    // Existing delete button event listener
    document.getElementById('deleteItemBtn').addEventListener('click', function () {
        const deleteUrl = "{{ route('currency-reset') }}";

        fetch(deleteUrl, {
            method: 'POST',
        })
        .then(response => response.json())
        .then(data => {
            window.location.reload();
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
</script>

<style>
.currency-pair {
    background: #f8f9fc;
    padding: 0.5rem 1rem;
    border-radius: 0.35rem;
    border: 1px solid #e3e6f0;
}
#realTimeRate {
    color: #4e73df;
    font-weight: 600;
}
.rate-info {
    font-size: 0.8rem;
}
#rateStatus {
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 0.25rem;
    background-color: #e3e6f0;
}
#refreshRateBtn.disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
