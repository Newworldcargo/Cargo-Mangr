<div class="w-full">
    @php
        use Carbon\Carbon;
        use Illuminate\Support\HtmlString;

        $viewerBranch = auth()->user()->role == 3
            ? \Modules\Cargo\Entities\Branch::where('user_id', auth()->id())->first()
            : null;
        $viewerCurrency = strtoupper($viewerBranch?->default_currency ?: 'ZMW');
        $viewerSymbol = currency_symbol_for($viewerCurrency);
        $formatShipmentAmount = function ($shipment, string $primaryClass = 'text-dark text-md font-weight-bold', string $secondaryClass = 'text-warning text-sm') use ($viewerCurrency, $viewerSymbol) {
            $usdAmount = (float) ($shipment->amount_to_be_collected ?? 0);
            if ($viewerCurrency === 'USD') {
                return new HtmlString('<span class="' . e($primaryClass) . '">$' . number_format($usdAmount, 2) . '</span>');
            }

            if ($viewerCurrency === 'ZMW') {
                $displayAmount = convert_currency($usdAmount, 'usd', 'zmw');
            } else {
                $rate = \App\Models\CurrencyExchangeRate::where('from_currency', 'USD')
                    ->where('to_currency', $viewerCurrency)
                    ->value('exchange_rate');
                $displayAmount = $rate ? ($usdAmount * (float) $rate) : $usdAmount;
            }

            return new HtmlString(
                '<span class="' . e($primaryClass) . '">' . e($viewerSymbol) . number_format($displayAmount, 2) . ' ' . e($viewerCurrency) . '</span>' .
                '<span class="' . e($secondaryClass) . '"> ($' . number_format($usdAmount, 2) . ' USD)</span>'
            );
        };
    @endphp
    <div class="row">
        <div id="column3" class="col-md-12 px-6">
            @if ($consignment->Mawb_num )
            <p class="text-muted text-sm">Mawb Number: {{ $consignment->Mawb_num }}</p>
            @endif
            @if ($consignment->voyage_no )
            <p class="text-muted text-sm">Vessel / Voyage No : {{ $consignment->voyage_no }}</p>
            @endif
            @if ($consignment->container_no )
            <p class="text-muted text-sm">Container No: {{ $consignment->container_no }}</p>
            @endif
            @if ($consignment->cargo_type == 'sea')
                <div class="items-center d-flex space-x-2">
                    <p class="text-sm text-gray-500">ETA DAR</p>
                    <p class="font-medium">
                        {{ $consignment->eta_dar ?? 'Not placed' }}
                    </p>
                </div>
                <div class="items-center d-flex space-x-2">
                    <p class="text-sm text-gray-500">ETA LUN</p>
                    <p class="font-medium">
                        {{ $consignment->eta_lun ?? 'Not placed' }}
                    </p>
                </div>
                @if ($consignment->destination)
                <div class="items-center d-flex space-x-2">
                    <p class="text-sm text-gray-500">Destination Port</p>
                    <p class="font-medium">
                        {{ $consignment->destination }}
                    </p>
                </div>  
                @endif
                <div>
                    <img width="80" src="{{ asset('icon/ship.svg') }}" alt="">
                </div>
            @else
                <div class="items-center d-flex space-x-2">
                    <p class="text-sm text-gray-500">Expected time of arrival:</p>
                    <p class="font-medium">
                        {{ Carbon::parse($consignment->eta)->format('l, F j, Y') ?? 'Not placed' }}
                    </p>
                </div>
                <div>
                    <img width="80" src="{{ asset('icon/plane.svg') }}" alt="">
                </div>
            @endif

            <!-- View Toggle Toolbar and Search -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="flex-grow-1 me-3">
                    <div class="input-group" id="shipmentSearchContainer" style="max-width: 400px; display: none;">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control" id="shipmentSearch" placeholder="Search shipments...">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
                <div class="btn-group" role="group" aria-label="View Toggle">
                    <button type="button" class="btn btn-outline-primary active" id="tableViewBtn" onclick="switchView('table')">
                        <i class="bi bi-table me-1"></i>Table
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="listViewBtn" onclick="switchView('list')">
                        <i class="bi bi-list-ul me-1"></i>List
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="gridViewBtn" onclick="switchView('grid')">
                        <i class="bi bi-grid-3x3-gap me-1"></i>Grid
                    </button>
                </div>
            </div>

            <!-- Table View -->
            <div id="tableView" class="view-container">
                <table id="shipmentTable" class="table table-hover">
                    <thead class="sticky-top bg-white z-10">
                        <tr class="text-sm">
                            <th><i class="bi bi-upc-scan me-1"></i> Hawb No.</th>
                            <th><i class="bi bi-box me-1"></i>Salesman</th>
                            <th><i class="bi bi-cube me-1"></i>Volume</th>
                            <th><i class="bi bi-person me-1"></i> Client</th>
                            <th><i class="bi bi-cube me-1"></i> Package CTN</th>
                            <th><i class="bi bi-file me-1"></i> Package Information</th>
                            <th><i class="bi bi-telephone me-1"></i> Client Phone</th>
                            <th><i class="bi bi-currency-dollar me-1"></i> Cost</th>
                            <th><i class="bi bi-cash-coin me-1"></i> Payment</th>
                            <th><i class="bi bi-clock-history me-1"></i> Added On</th>
                            <th><i class="bi bi-gear me-1"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($consignment->shipments as $shipment)
                            <tr id="shipment_row_{{ $shipment->id }}" class="shipment-row" data-search-text="{{ strtolower($shipment->code . ' ' . $shipment->client->name . ' ' . $shipment->salesman . ' ' . ($shipment->client_phone ?? '')) }}">
                                <td>
                                    <span class="badge bg-info rounded-pill copy-shipment-code" style="cursor:pointer;" data-code="{{ $shipment->code }}" title="Click to copy">{{ $shipment->code }}</span>
                                </td>
                                <td>{{ $shipment->salesman ?? 'No Salesman' }}</td>
                                <td>{{ $shipment->volume ?? 'No Volume' }}</td>
                                <td>{{ $shipment->client->name  }}</td>
                                <td style="background-color: #F5A905;">
                                    <b>{{ Modules\Cargo\Entities\PackageShipment::where('shipment_id', $shipment->id)->sum('qty') }}</b>
                                </td>
                                <td>
                                    @foreach (Modules\Cargo\Entities\PackageShipment::where('shipment_id', $shipment->id)->get() as $package)
                                        {{ $package->description }}
                                    @endforeach
                                </td>
                                <td>{{ $shipment->client_phone ?? 'No phone' }}</td>
                                <td>
                                    {!! $formatShipmentAmount($shipment) !!}
                                </td>
                                <td>
                                    @if ($shipment->paid)
                                        <span class="badge bg-success rounded-pill">PAID</span>
                                    @else
                                        <span class="badge bg-secondary rounded-pill">UNPAID</span>
                                    @endif
                                </td>
                                <td>{{ $shipment->created_at->toFormattedDateString() }}</td>
                                <td class="action-buttons">
                                    @can('view-shipment-invoices')
                                    <a href="{{ url('admin/shipments/shipments/' . $shipment->id) }}"
                                        class="btn btn-icon btn-light text-info btn-lg rounded me-2"
                                        title="View Shipment Invoice">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                            fill="currentColor" class="bi bi-receipt" viewBox="0 0 16 16">
                                            <path
                                                d="M1.92.506a.5.5 0 0 1 .434.14L3 1.293l.646-.647a.5.5 0 0 1 .708 0L5 1.293l.646-.647a.5.5 0 0 1 .708 0L7 1.293l.646-.647a.5.5 0 0 1 .708 0L9 1.293l.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .801.13l.5 1A.5.5 0 0 1 15 2v12a.5.5 0 0 1-.053.224l-.5 1a.5.5 0 0 1-.8.13L13 14.707l-.646.647a.5.5 0 0 1-.708 0L11 14.707l-.646.647a.5.5 0 0 1-.708 0L9 14.707l-.646.647a.5.5 0 0 1-.708 0L7 14.707l-.646.647a.5.5 0 0 1-.708 0L5 14.707l-.646.647a.5.5 0 0 1-.708 0L3 14.707l-.646.647a.5.5 0 0 1-.801-.13l-.5-1A.5.5 0 0 1 1 14V2a.5.5 0 0 1 .053-.224l.5-1a.5.5 0 0 1 .367-.27m.217 1.338L2 2.118v11.764l.137.274.51-.51a.5.5 0 0 1 .707 0l.646.647.646-.646a.5.5 0 0 1 .708 0l.646.646.646-.646a.5.5 0 0 1 .708 0l.646.646.646-.646a.5.5 0 0 1 .708 0l.646.646.646-.646a.5.5 0 0 1 .708 0l.509.509.137-.274V2.118l-.137-.274-.51.51a.5.5 0 0 1-.707 0L12 1.707l-.646.647a.5.5 0 0 1-.708 0L10 1.707l-.646.647a.5.5 0 0 1-.708 0L8 1.707l-.646.647a.5.5 0 0 1-.708 0L6 1.707l-.646.647a.5.5 0 0 1-.708 0L4 1.707l-.646.647a.5.5 0 0 1-.708 0z" />
                                            <path
                                                d="M3 4.5a.5.5 0 0 1 .5-.5h6a.5.5 0 1 1 0 1h-6a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 1 1 0 1h-6a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 1 1 0 1h-6a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5m8-6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5" />
                                        </svg>
                                    </a>
                                    @endcan
                                    <a target="_blank" href="{{ url('en/shipments/tracking?code=' . $shipment->code) }}" class="btn btn-icon btn-light text-primary btn-lg rounded me-2" title="Track Shipment">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt" viewBox="0 0 16 16">
                                            <path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10zm0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/>
                                        </svg>
                                    </a>
                                    @can('delete-shipment-invoices')
                                    <button class="btn btn-icon btn-light text-danger rounded"
                                        data-shipment-id="{{ $shipment->id }}" data-bs-toggle="tooltip"
                                        title="Remove Shipment">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                            fill="currentColor" class="bi bi-folder-minus" viewBox="0 0 16 16">
                                            <path
                                                d="m.5 3 .04.87a2 2 0 0 0-.342 1.311l.637 7A2 2 0 0 0 2.826 14H9v-1H2.826a1 1 0 0 1-.995-.91l-.637-7A1 1 0 0 1 2.19 4h11.62a1 1 0 0 1 .996 1.09L14.54 8h1.005l.256-2.819A2 2 0 0 0 13.81 3H9.828a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 6.172 1H2.5a2 2 0 0 0-2 2m5.672-1a1 1 0 0 1 .707.293L7.586 3H2.19q-.362.002-.683.12L1.5 2.98a1 1 0 0 1 1-.98z" />
                                            <path d="M11 11.5a.5.5 0 0 1 .5-.5h4a.5.5 0 1 1 0 1h-4a.5.5 0 0 1-.5-.5" />
                                        </svg>
                                    </button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- List View -->
            <div id="listView" class="view-container" style="display: none;">
                <div class="list-group">
                    @foreach ($consignment->shipments as $shipment)
                        <div class="modern-list-row d-flex align-items-center mb-3 shipment-item" id="shipment_list_{{ $shipment->id }}" data-search-text="{{ strtolower($shipment->code . ' ' . $shipment->client->name . ' ' . $shipment->salesman . ' ' . ($shipment->client_phone ?? '')) }}">
                            <div class="accent-bar {{ $shipment->paid ? 'bg-success' : 'bg-secondary' }}"></div>
                            <div class="flex-grow-1 d-flex flex-wrap align-items-center gap-3 px-3 py-2">
                                <div class="shipment-main-info flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="shipment-code badge bg-info text-dark fs-5 px-3 py-2">{{ $shipment->code }}</span>
                                        <span class="fw-semibold fs-6">{{ $shipment->client->name }}</span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-3 text-muted small mb-1">
                                        <span><i class="bi bi-person me-1"></i>{{ $shipment->salesman }}</span>
                                        <span><i class="bi bi-telephone me-1"></i>{{ $shipment->client_phone ?? 'No phone' }}</span>
                                    </div>
                                    <div class="text-muted small mb-1">
                                        <i class="bi bi-file me-1"></i>
                                        @foreach (Modules\Cargo\Entities\PackageShipment::where('shipment_id', $shipment->id)->get() as $package)
                                            {{ $package->description }}
                                        @endforeach
                                    </div>
                                </div>
                                <div class="shipment-amount text-end pe-3">
                                    <div>{!! $formatShipmentAmount($shipment, 'fw-bold text-dark fs-6', 'text-warning small d-block') !!}</div>
                                    <span class="badge {{ $shipment->paid ? 'bg-success' : 'bg-secondary' }} rounded-pill px-2 py-1 mt-1">{{ $shipment->paid ? 'PAID' : 'UNPAID' }}</span>
                                    <div class="text-muted small mt-1">{{ $shipment->created_at->toFormattedDateString() }}</div>
                                </div>
                                <div class="shipment-actions d-flex flex-column gap-1 align-items-end">
                                    @can('view-shipment-invoices')
                                    <a href="{{ url('admin/shipments/shipments/' . $shipment->id) }}" class="btn btn-outline-info btn-sm p-1" title="View Invoice"><i class="bi bi-receipt"></i></a>
                                    @endcan
                                    <a target="_blank" href="{{ url('en/shipments/tracking?code=' . $shipment->code) }}" class="btn btn-outline-primary btn-sm p-1" title="Track"><i class="bi bi-geo-alt"></i></a>
                                    @can('delete-shipment-invoices')
                                    <button class="btn btn-outline-danger btn-sm p-1" data-shipment-id="{{ $shipment->id }}" data-bs-toggle="tooltip" title="Remove"><i class="bi bi-folder-minus"></i></button>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Grid View -->
            <div id="gridView" class="view-container" style="display: none;">
                <div class="row">
                    @foreach ($consignment->shipments as $shipment)
                        <div class="col-lg-4 col-md-6 mb-4 shipment-card">
                            <div class="card h-100 border-0 shadow modern-grid-card">
                                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center pb-1 pt-3 px-3">
                                    <span class="badge bg-info text-dark rounded-pill px-3 py-2 fs-6">{{ $shipment->code }}</span>
                                    <span class="badge {{ $shipment->paid ? 'bg-success' : 'bg-secondary' }} rounded-pill px-2 py-1">{{ $shipment->paid ? 'PAID' : 'UNPAID' }}</span>
                                </div>
                                <div class="card-body pt-2 pb-2 px-3">
                                    <div class="fw-semibold fs-6 mb-1">{{ $shipment->client->name }}</div>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <span class="text-muted small"><i class="bi bi-person me-1"></i>{{ $shipment->salesman }}</span>
                                        <span class="text-muted small"><i class="bi bi-building me-1"></i>Lusaka</span>
                                        <span class="text-muted small"><i class="bi bi-telephone me-1"></i>{{ $shipment->client_phone ?? 'No phone' }}</span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-file me-1"></i>
                                            @foreach (Modules\Cargo\Entities\PackageShipment::where('shipment_id', $shipment->id)->get() as $package)
                                                {{ $package->description }}
                                            @endforeach
                                        </small>
                                    </div>
                                    <div>{!! $formatShipmentAmount($shipment, 'fw-bold text-dark fs-6 mb-1', 'text-warning small d-block') !!}</div>
                                </div>
                                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">{{ $shipment->created_at->toFormattedDateString() }}</small>
                                        <div class="btn-group btn-group-sm gap-1">
                                            @can('view-shipment-invoices')
                                            <a href="{{ url('admin/shipments/shipments/' . $shipment->id) }}" class="btn btn-outline-info btn-sm p-1" title="View Invoice"><i class="bi bi-receipt"></i></a>
                                            @endcan
                                            <a target="_blank" href="{{ url('en/shipments/tracking?code=' . $shipment->code) }}" class="btn btn-outline-primary btn-sm p-1" title="Track"><i class="bi bi-geo-alt"></i></a>
                                            @can('delete-shipment-invoices')
                                            <button class="btn btn-outline-danger btn-sm p-1" data-shipment-id="{{ $shipment->id }}" data-bs-toggle="tooltip" title="Remove"><i class="bi bi-folder-minus"></i></button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div id="copyAlert" class="alert alert-success text-center" style="display:none; position:fixed; top:20px; left:50%; transform:translateX(-50%); z-index:9999; min-width:200px; max-width:90%;">
        <span id="copyAlertText">Copied to clipboard!</span>
    </div>

    <script>
        function switchView(viewType) {
            // Hide all views
            document.getElementById('tableView').style.display = 'none';
            document.getElementById('listView').style.display = 'none';
            document.getElementById('gridView').style.display = 'none';
            // Remove active class from all buttons
            document.getElementById('tableViewBtn').classList.remove('active');
            document.getElementById('listViewBtn').classList.remove('active');
            document.getElementById('gridViewBtn').classList.remove('active');
            // Hide search by default
            document.getElementById('shipmentSearchContainer').style.display = 'none';
            // Show selected view and activate button
            switch(viewType) {
                case 'table':
                    document.getElementById('tableView').style.display = 'block';
                    document.getElementById('tableViewBtn').classList.add('active');
                    break;
                case 'list':
                    document.getElementById('listView').style.display = 'block';
                    document.getElementById('listViewBtn').classList.add('active');
                    document.getElementById('shipmentSearchContainer').style.display = 'flex';
                    break;
                case 'grid':
                    document.getElementById('gridView').style.display = 'block';
                    document.getElementById('gridViewBtn').classList.add('active');
                    document.getElementById('shipmentSearchContainer').style.display = 'flex';
                    break;
            }
            // Clear search if switching to table view
            if(viewType === 'table') {
                document.getElementById('shipmentSearch').value = '';
                filterShipments();
            } else {
                // Apply current search filter to new view
                const searchTerm = document.getElementById('shipmentSearch').value;
                if (searchTerm.trim() !== '') {
                    filterShipments();
                }
            }
        }
        function filterShipments() {
            const searchTerm = document.getElementById('shipmentSearch').value.toLowerCase();
            // Only filter in List or Grid view
            const listViewVisible = document.getElementById('listView').style.display !== 'none';
            const gridViewVisible = document.getElementById('gridView').style.display !== 'none';
            // Filter list items
            if(listViewVisible) {
                const listItems = document.querySelectorAll('.shipment-item');
                listItems.forEach(item => {
                    const searchText = item.getAttribute('data-search-text');
                    if (searchText.includes(searchTerm)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }
            // Filter grid cards
            if(gridViewVisible) {
                const gridCards = document.querySelectorAll('.shipment-card');
                gridCards.forEach(card => {
                    const searchText = card.getAttribute('data-search-text');
                    if (searchText.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
            // Show/hide no results message
            updateNoResultsMessage();
        }
        function clearSearch() {
            document.getElementById('shipmentSearch').value = '';
            filterShipments();
        }

        function updateNoResultsMessage() {
            const searchTerm = document.getElementById('shipmentSearch').value.toLowerCase();
            if (searchTerm.trim() === '') {
                // Remove any existing no results messages
                const existingMessages = document.querySelectorAll('.no-results-message');
                existingMessages.forEach(msg => msg.remove());
                return;
            }

            // Check if any items are visible
            const visibleRows = document.querySelectorAll('.shipment-row:not([style*="display: none"])');
            const visibleItems = document.querySelectorAll('.shipment-item:not([style*="display: none"])');
            const visibleCards = document.querySelectorAll('.shipment-card:not([style*="display: none"])');
            
            const hasVisibleResults = visibleRows.length > 0 || visibleItems.length > 0 || visibleCards.length > 0;
            
            // Remove existing no results messages
            const existingMessages = document.querySelectorAll('.no-results-message');
            existingMessages.forEach(msg => msg.remove());
            
            if (!hasVisibleResults) {
                // Add no results message to the active view
                const activeView = document.querySelector('.view-container:not([style*="display: none"])');
                if (activeView) {
                    const noResultsDiv = document.createElement('div');
                    noResultsDiv.className = 'no-results-message text-center py-5';
                    noResultsDiv.innerHTML = `
                        <div class="text-muted">
                            <i class="bi bi-search fs-1 mb-3"></i>
                            <h5>No shipments found</h5>
                            <p>Try adjusting your search terms or clear the search to see all shipments.</p>
                        </div>
                    `;
                    activeView.appendChild(noResultsDiv);
                }
            }
        }

        // Initialize search functionality when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('shipmentSearch');
            searchInput.addEventListener('input', filterShipments);

            // Copy shipment code to clipboard on badge click
            document.querySelectorAll('.copy-shipment-code').forEach(function(badge) {
                badge.addEventListener('click', function() {
                    const code = this.getAttribute('data-code');
                    navigator.clipboard.writeText(code).then(() => {
                        showCopyAlert('Shipment code copied: ' + code);
                    });
                });
            });

            function showCopyAlert(message) {
                const alertDiv = document.getElementById('copyAlert');
                const alertText = document.getElementById('copyAlertText');
                alertText.textContent = message;
                alertDiv.style.display = 'block';
                setTimeout(() => {
                    alertDiv.style.display = 'none';
                }, 1500);
            }
        });
    </script>

    <style>
        .view-container {
            transition: all 0.3s ease-in-out;
        }
        
        .btn-group .btn {
            transition: all 0.2s ease;
        }
        
        .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        }
        
        .list-group-item {
            transition: all 0.2s ease;
        }
        
        .list-group-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 1rem;
            }
            
            .btn-group {
                width: 100%;
            }
            
            .btn-group .btn {
                flex: 1;
            }
            
            .input-group {
                max-width: 100% !important;
            }
        }
        .modern-list-item {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #f0f0f0;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .modern-list-item:hover {
            box-shadow: 0 6px 24px rgba(0,0,0,0.10);
            transform: translateY(-2px) scale(1.01);
        }
        .modern-list-item .btn {
            border-radius: 50%;
            min-width: 32px;
            min-height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modern-grid-card {
            border-radius: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #f0f0f0;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .modern-grid-card:hover {
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            transform: translateY(-4px) scale(1.015);
        }
        .modern-grid-card .btn {
            border-radius: 50%;
            min-width: 32px;
            min-height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .fw-semibold { font-weight: 600; }
        .min-w-120 { min-width: 120px; }
        @media (max-width: 768px) {
            .modern-list-item, .modern-grid-card { padding: 0.75rem 0.5rem; }
            .modern-list-item .d-flex, .modern-grid-card .d-flex { flex-direction: column !important; gap: 0.5rem; }
            .modern-list-item .btn, .modern-grid-card .btn { min-width: 28px; min-height: 28px; }
        }
        .modern-list-row {
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #f0f0f0;
            transition: box-shadow 0.2s, transform 0.2s;
            position: relative;
            min-height: 80px;
        }
        .modern-list-row:hover {
            box-shadow: 0 8px 32px rgba(0,0,0,0.10);
            transform: translateY(-2px) scale(1.01);
        }
        .modern-list-row .accent-bar {
            width: 6px;
            height: 100%;
            border-radius: 1rem 0 0 1rem;
            min-height: 60px;
        }
        .modern-list-row .shipment-main-info {
            min-width: 180px;
        }
        .modern-list-row .shipment-code {
            font-size: 1.15rem;
            font-weight: 700;
        }
        .modern-list-row .shipment-amount {
            min-width: 120px;
        }
        .modern-list-row .shipment-actions .btn {
            border-radius: 50%;
            min-width: 32px;
            min-height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 900px) {
            .modern-list-row .shipment-amount, .modern-list-row .shipment-actions { min-width: 90px; }
        }
        @media (max-width: 768px) {
            .modern-list-row { flex-direction: column !important; align-items: stretch !important; padding: 0.5rem 0.5rem; }
            .modern-list-row .shipment-main-info, .modern-list-row .shipment-amount, .modern-list-row .shipment-actions { min-width: 0; }
            .modern-list-row .shipment-actions { flex-direction: row !important; justify-content: flex-start !important; gap: 0.5rem; margin-top: 0.5rem; }
        }
    </style>
</div>
