@php
    $user_role = auth()->user()->role;
    $admin = 1;
    $branch = 3;
    $client = 4;
@endphp
@extends('cargo::adminLte.layouts.master')
@section('pageTitle', 'Consignments')
@section('content')
    <!-- Add Tailwind CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Keep DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <!-- Breadcrumb navigation with modern styling -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="flex items-center py-2 px-4 bg-gray-50 rounded-lg shadow-sm text-sm">
            <li class="flex items-center">
                <a href="#" class="text-yellow-400 hover:text-yellow-500 transition-colors">Dashboard</a>
                <svg class="h-4 w-4 mx-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                </svg>
            </li>
            <li class="flex items-center">
                <a href="{{ route('consignment.index') }}" class="text-yellow-400 hover:text-yellow-500 transition-colors">Consignments</a>
                <svg class="h-4 w-4 mx-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                </svg>
            </li>
            <li class="text-gray-700 font-medium" aria-current="page">Consignment - Shipments</li>
        </ol>
    </nav>

    <!-- Main card with sleek design -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6 border border-gray-100">
        <!-- Card header with gradient background -->
        <div class="bg-gradient-to-r from-yellow-50 to-gray-50 border-b border-gray-100 px-6 py-4 flex flex-col md:flex-row justify-between items-center">
            <h5 class="text-lg font-bold text-gray-800 flex items-center mb-4 md:mb-0" id="consignmentModalLabel">
                <span class="text-yellow-400 mr-2">Consignment Code:</span>
                <span class="text-gray-800" id="consignmentCodeText">{{ $consignment->consignment_code }}</span>

                <span id="formConsignmentCode"
                    class="ml-2 px-2 py-1 text-xs font-semibold bg-yellow-400 hover:bg-yellow-500 text-white rounded-full flex items-center space-x-1 cursor-pointer transition-all duration-300 ease-in-out transform hover:scale-105"
                    onclick="copyConsignmentCode()">
                    <i id="copyIcon" class="fas fa-copy text-white"></i>
                    <span id="copyText">Copy</span>
                </span>
            </h5>

            <!-- Toast notification -->
            <div id="copyToast" class="fixed top-4 right-4 bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg flex items-center space-x-2 transform translate-y-[-100px] opacity-0 transition-all duration-500 z-9999999999">
                <i class="fas fa-check-circle text-green-400"></i>
                <span id="toastMessage">Copied to clipboard!</span>
            </div>

            <script>
                function copyConsignmentCode() {
                    const code = document.getElementById('consignmentCodeText').innerText;
                    const copyBtn = document.getElementById('formConsignmentCode');
                    const copyIcon = document.getElementById('copyIcon');
                    const copyText = document.getElementById('copyText');
                    const toast = document.getElementById('copyToast');

                    // Copy text to clipboard
                    navigator.clipboard.writeText(code).then(function() {
                        // Button animation
                        copyBtn.classList.add('bg-green-600');
                        copyIcon.classList.remove('fa-copy');
                        copyIcon.classList.add('fa-check');
                        copyText.innerText = 'Copied!';

                        // Show toast
                        document.getElementById('toastMessage').innerText = `Copied: ${code.substring(0, 10)}${code.length > 10 ? '...' : ''}`;
                        toast.classList.remove('translate-y-[-100px]', 'opacity-0');
                        toast.classList.add('translate-y-0', 'opacity-100');

                        // Reset button after delay
                        setTimeout(() => {
                            copyBtn.classList.remove('bg-green-600');
                            copyIcon.classList.add('fa-copy');
                            copyIcon.classList.remove('fa-check');
                            copyText.innerText = 'Copy';
                        }, 2000);

                        // Hide toast after delay
                        setTimeout(() => {
                            toast.classList.add('translate-y-[-100px]', 'opacity-0');
                            toast.classList.remove('translate-y-0', 'opacity-100');
                        }, 3000);

                    }, function(err) {
                        console.error('Failed to copy: ', err);

                        // Error animation
                        copyBtn.classList.add('bg-red-600');
                        copyIcon.classList.remove('fa-copy');
                        copyIcon.classList.add('fa-times');
                        copyText.innerText = 'Error!';

                        // Show error toast
                        document.getElementById('toastMessage').innerText = 'Failed to copy to clipboard';
                        toast.classList.remove('translate-y-[-100px]', 'opacity-0');
                        toast.classList.add('translate-y-0', 'opacity-100');

                        // Reset after delay
                        setTimeout(() => {
                            copyBtn.classList.remove('bg-red-600');
                            copyIcon.classList.add('fa-copy');
                            copyIcon.classList.remove('fa-times');
                            copyText.innerText = 'Copy';
                            toast.classList.add('translate-y-[-100px]', 'opacity-0');
                            toast.classList.remove('translate-y-0', 'opacity-100');
                        }, 3000);
                    });

                    // Add ripple effect
                    const ripple = document.createElement('span');
                    ripple.classList.add('absolute', 'inset-0', 'bg-white', 'rounded-full', 'opacity-30', 'transform', 'scale-0');
                    copyBtn.appendChild(ripple);

                    // Trigger animation
                    setTimeout(() => {
                        ripple.classList.add('scale-150', 'opacity-0', 'transition-all', 'duration-600');
                        setTimeout(() => {
                            copyBtn.removeChild(ripple);
                        }, 700);
                    }, 0);
                }
            </script>

            <style>
                /* Ensure relative positioning for ripple effect */
                #formConsignmentCode {
                    position: relative;
                    overflow: hidden;
                }

                /* Optional: Pulse animation for the copy button when page loads */
                @keyframes gentle-pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                    100% { transform: scale(1); }
                }

                #formConsignmentCode {
                    animation: gentle-pulse 2s ease-in-out 1;
                }
            </style>


            <!-- Button group with modern styling -->
            <div class="flex flex-col sm:flex-row gap-3">
                @can('export-shipment-invoices')
                <form method="POST" action="{{ route('consignment.export') }}" class="flex-shrink-0">
                    @csrf
                    <input type="hidden" name="consignment_id" value="{{ $consignment->id }}">
                    <button type="submit" class="w-full sm:w-auto flex items-center justify-center px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-all duration-200 transform hover:-translate-y-0.5 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="mr-2" viewBox="0 0 16 16">
                            <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2M9.5 3A1.5 1.5 0 0 0 11 4.5h2V9H3V2a1 1 0 0 1 1-1h5.5zM3 12v-2h2v2zm0 1h2v2H4a1 1 0 0 1-1-1zm3 2v-2h3v2zm4 0v-2h3v1a1 1 0 0 1-1 1zm3-3h-3v-2h3zm-7 0v-2h3v2z"/>
                        </svg>
                        <span class="text-sm font-medium">Export Shipments</span>
                    </button>
                </form>
                @endcan
                @can('import-consignments')
                <button type="button" class="w-full sm:w-auto flex items-center justify-center px-4 py-2 bg-yellow-400 hover:bg-yellow-500 text-white rounded-lg transition-all duration-200 transform hover:-translate-y-0.5 shadow-sm" data-toggle="modal" data-target="#importModal">
                    <i class="fas fa-file-excel mr-2"></i>
                    <span class="text-sm font-medium">Import Consignment</span>
                </button>
                @endcan
            </div>
        </div>

        <!-- Card body -->
        <div class="overflow-hidden">
            @include('cargo::adminLte.pages.consignments.editor.s-table')
        </div>
    </div>

    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- Custom styles to ensure proper integration -->
    <style>
        /* Ensure DataTables styling works with Tailwind */
        .dataTables_wrapper {
            padding: 1rem;
        }

        /* Fix any Bootstrap to Tailwind conflicts */
        .btn {
            @apply inline-flex items-center justify-center;
        }

        /* Tailwind default focus ring for accessibility */
        a:focus, button:focus {
            @apply outline-none ring-2 ring-offset-2 ring-blue-500;
        }
    </style>

    @include('cargo::adminLte.pages.consignments.editor.import-modal')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Initialize DataTable
            $('.table').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "lengthMenu": [10, 25, 50, 100],
                "columnDefs": [
                    { "orderable": false, "targets": 8 }
                ]
            });

        function searchShipment() {
            let query = document.getElementById('searchShipment').value;

            if (query.length < 2) {
                document.getElementById('shipmentResults').innerHTML = "<p class='text-muted'>Type at least 2 characters to search for shipments...</p>";
                return;
            }

            // Show loading indicator
            document.getElementById('shipmentResults').innerHTML = "<p class='text-center'><i class='fas fa-spinner fa-spin'></i> Searching...</p>";

            // This is a working query route for live searching through shipments
            fetch("{{ route('search.shipments') }}?query=" + query)
            .then(response => response.json())
            .then(data => {
                if (data.length === 0) {
                    document.getElementById('shipmentResults').innerHTML = "<p class='text-center'>No shipments found matching your search.</p>";
                    return;
                }

                // Custom styled results container
                let resultsHtml = `...`; // Same content as previous for search results.
                document.getElementById('shipmentResults').innerHTML = resultsHtml;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('shipmentResults').innerHTML = "<p class='text-danger'>Error searching for shipments. Please try again.</p>";
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize DataTable for better table presentation
            if (typeof $.fn.DataTable !== 'undefined') {
                $('#shipmentTable').DataTable({
                    responsive: true,
                    "pageLength": 8
                });
            }

            // Add event listeners to all Remove buttons
            document.querySelectorAll('.btn-danger').forEach(button => {
                button.addEventListener('click', function() {
                    const shipmentId = this.getAttribute('data-shipment-id');
                    const consignmentId = {{ $consignment->id }};

                    removeShipmentFromConsignment(shipmentId, consignmentId);
                });
            });
        });

        function searchShipment() {
            let query = document.getElementById('searchShipment').value;

            if (query.length < 2) {
                document.getElementById('shipmentResults').innerHTML = "<p class='text-muted'>Type at least 2 characters to search for shipments...</p>";
                return;
            }

            // Show loading indicator
            document.getElementById('shipmentResults').innerHTML = "<p class='text-center'><i class='fas fa-spinner fa-spin'></i> Searching...</p>";

            // This is a working query route for live searching through shipments
            fetch("{{ route('search.shipments') }}?query=" + query)
        .then(response => response.json())
        .then(data => {
            if (data.length === 0) {
                document.getElementById('shipmentResults').innerHTML = "<p class='text-center'>No shipments found matching your search.</p>";
                return;
            }

            // Custom styled results container
            let resultsHtml = `
                <div class="shipment-results-wrapper" style="max-height: 350px; overflow-y: auto; border-radius: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <div class="list-group list-group-flush">
            `;

            data.forEach(shipment => {
                resultsHtml += `
                    <div class="list-group-item list-group-item-action p-2 d-flex align-items-center shipment-item" style="border-left: 3px solid #3490dc; transition: all 0.2s ease;">
                        <div class="form-check me-2" style="min-width: 24px;">
                            <input class="form-check-input"
                                style="cursor: pointer; border-width: 2px;"
                                type="checkbox"
                                name="shipment_id[]"
                                value="${shipment.id}"
                                id="shipment_${shipment.id}"
                                onclick="event.stopPropagation(); toggleShipment(${shipment.id}, '${shipment.code}')">
                        </div>
                        <div class="ms-1 flex-grow-1 overflow-hidden" onclick="document.getElementById('shipment_${shipment.id}').click();" style="cursor: pointer;">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold text-truncate" style="max-width: 120px;">${shipment.code}</span>
                                <small class="text-muted ms-2">${shipment.reciver_phone || 'No phone'}</small>
                            </div>
                            <div class="text-truncate small" style="opacity: 0.8;">${shipment.reciver_name || 'Unknown'}</div>
                        </div>
                    </div>
                `;
            });

            resultsHtml += `</div></div>`;
            resultsHtml += `<div class="text-muted small mt-2 d-flex justify-content-between">
                                <span>Found ${data.length} shipment(s)</span>
                                <span id="selected-count">0 selected</span>
                            </div>`;

            document.getElementById('shipmentResults').innerHTML = resultsHtml;

            // Add hover effect for list items
            document.querySelectorAll('.shipment-item').forEach(item => {
                item.addEventListener('mouseover', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                item.addEventListener('mouseout', function() {
                    this.style.backgroundColor = '';
                });
            });
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('shipmentResults').innerHTML = "<p class='text-danger'>Error searching for shipments. Please try again.</p>";
        });

        }


    // Update the toggleShipment function to count selected items
    function toggleShipment(shipmentId, shipmentCode) {
        const checkbox = document.getElementById(`shipment_${shipmentId}`);
        console.log(`Shipment ${shipmentCode} (${shipmentId}) is now ${checkbox.checked ? 'selected' : 'unselected'}`);

        // Highlight the selected item
        const item = checkbox.closest('.shipment-item');
        if (checkbox.checked) {
            item.style.backgroundColor = '#e8f4ff';
        } else {
            item.style.backgroundColor = '';
        }

        // Update the selected count
        const selectedCount = document.querySelectorAll('input[name="shipment_id[]"]:checked').length;
        document.getElementById('selected-count').textContent = `${selectedCount} selected`;
    }

        function addSelectedShipmentsToConsignment(consignmentId) {
            const selectedShipments = document.querySelectorAll('input[name="shipment_id[]"]:checked');
            const shipmentIds = Array.from(selectedShipments).map(checkbox => checkbox.value);

            if (shipmentIds.length === 0) {
                alert('Please select at least one shipment to add');
                return;
            }

            // Send AJAX request to add shipments to consignment
            fetch(`../api/submit-shipments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ shipment_ids: shipmentIds, consignment_id: consignmentId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Failed to add shipments: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Function to remove a shipment from the consignment
        function removeShipmentFromConsignment(shipmentId, consignmentId) {
            if (!confirm('Are you sure you want to remove this shipment from the consignment?')) {
                return;
            }

            // Send AJAX request to remove shipment from consignment
            fetch(`../api/consignments/${consignmentId}/remove-shipment/${shipmentId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the row from the table
                    const row = document.querySelector(`#shipment_row_${shipmentId}`);
                    if (row) {
                        row.remove();
                    } else {
                        // If we can't find the row, just reload the page
                        window.location.reload();
                    }
                } else {
                    alert('Failed to remove shipment: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while removing the shipment');
            });
        }

        // Initialize when DOM is fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize DataTable for better table presentation
            if (typeof $.fn.DataTable !== 'undefined') {
                $('#shipmentTable').DataTable({
                    responsive: true,
                    "pageLength": 8
                });
            }

            // Add event listeners to all Remove buttons
            document.querySelectorAll('.btn-danger').forEach(button => {
                button.addEventListener('click', function() {
                    const shipmentId = this.getAttribute('data-shipment-id');
                    const consignmentId = {{ $consignment->id }};

                    removeShipmentFromConsignment(shipmentId, consignmentId);
                });
            });
        });
    </script>
@endsection
