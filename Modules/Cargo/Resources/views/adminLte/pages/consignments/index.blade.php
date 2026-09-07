@extends('cargo::adminLte.layouts.master')
@section('pageTitle', 'Consignments')
@section('content')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css">
<div class="">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="flex items-center space-x-3">
            <div class="flex-shrink-0">
                <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-yellow-400 tracking-tight">Consignments</h2>
                <p class="text-sm text-gray-600 mt-1">Manage and track your shipment consignments</p>
            </div>
        </div>
        <div>
            @can('import-consignments')
            <button type="button" class="btn btn-info mr-2" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-file-excel"></i> Import Consignments
            </button>
            @endcan
            @can('create-consignments')
            <a href="{{ route('consignment.create') }}" class="btn btn-warning">
                <i class="fas fa-plus"></i> Add New Consignment
            </a>
            @endcan
        </div>
    </div>
    @if (!empty($consignments))
        @include('cargo::adminLte.pages.consignments.editor.table')
    @endif
</div>

<!-- Update Tracker Modal -->
<div class="modal fade" id="updateTrackerModal" tabindex="-1" role="dialog" aria-labelledby="updateTrackerModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning border-0">
                <h5 class="modal-title font-weight-bold text-white" id="updateTrackerModalLabel">
                    <i class="fas fa-shipping-fast mr-2"></i>Update Consignment Tracker
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="row no-gutters">
                    <div class="col-md-4 bg-white d-flex flex-column align-items-center justify-content-center p-4">
                        <div class="tracking-status text-center">
                            <div class="shipment-illustration mb-4">
                                <img src="{{ asset('assets/anime/earth.gif') }}" alt="">
                            </div>
                            <span class="badge badge-pill badge-primary px-3 py-2">Tracking Active</span>
                            <p class="text-muted small mt-3 mb-0">Last updated: <span id="statusUpdateTime"></span></p>
                        </div>
                        <!-- Current Stage Details with Loader -->
                        <div class="current-stage-details mt-4 text-center">
                            <h6 class="font-weight-bold text-dark mb-1">Current Stage</h6>
                            <div class="mb-2">
                                <div id="currentStageLoader" class="spinner-border spinner-border-sm text-primary d-none" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <span id="currentStageName" class="text-primary font-weight-bold"></span>
                            </div>
                            <div>
                                <span id="currentStageDescription" class="text-muted small"></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8 py-4 px-4">
                        <form id="updateTrackerForm" action="" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="consignment_id" id="consignmentId">
                            <div class="card border-0 shadow-sm mb-4" id="consignmentDetails">
                                <div class="card-header bg-light py-3">
                                    <h6 class="font-weight-bold mb-0 text-dark">
                                        <i class="fas fa-info-circle mr-2 text-primary"></i>Consignment Details
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            {{-- <div class="detail-item mb-3">
                                                <span class="text-muted small text-uppercase">Consignment Name</span>
                                                <p class="font-weight-bold mb-1" id="conName"></p>
                                            </div> --}}
                                            <div class="detail-item mb-3">
                                                <span class="text-muted small text-uppercase">Tracking No</span>
                                                <p class="font-weight-bold mb-1" id="modalTracking"></p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            {{-- <div class="detail-item mb-3">
                                                <span class="text-muted small text-uppercase">Source</span>
                                                <p class="font-weight-bold mb-1" id="sourceDestination"></p>
                                            </div>
                                            <div class="detail-item mb-3">
                                                <span class="text-muted small text-uppercase">Destination</span>
                                                <p class="font-weight-bold mb-1" id="finalDestination"></p>
                                            </div> --}}
                                            <div class="detail-item">
                                                <span class="text-muted small text-uppercase">Last Update</span>
                                                <p class="font-weight-bold mb-1" id="conLastUpdate"></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="trackerStatus" class="font-weight-bold">
                                    <i class="fas fa-map-marker-alt mr-2 text-primary"></i>Update Shipment Status
                                </label>
                                <div class="position-relative">
                                    <div id="statusSelectLoader" class="spinner-border spinner-border-sm text-primary position-absolute d-none" style="right: 10px; top: 10px;">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <select class="form-control custom-select border-0 shadow-sm" name="status" id="trackerStatus" required>
                                        <option value="">Loading status options...</option>
                                    </select>
                                </div>
                            </div>

                            <div class="modal-footer border-0 px-0 pt-4">
                                <button type="button" class="btn btn-light border shadow-sm px-4" data-dismiss="modal">
                                    <i class="fas fa-times mr-2"></i>Cancel
                                </button>
                                <button type="submit" class="btn btn-primary shadow px-4 btnclicky" id="updateTrackerBtn">
                                    <i class="fas fa-sync-alt mr-2"></i>Update Tracker
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Modal Enhancement Styles */
    #updateTrackerModal .modal-content {
        border-radius: 8px;
        overflow: hidden;
    }

    #updateTrackerModal .modal-header {
        padding: 1.2rem 1.5rem;
    }

    #updateTrackerModal .bg-gradient-primary {
        background: linear-gradient(135deg, #012642 0%, #2b5db0 100%);
    }

    #updateTrackerModal .custom-select {
        height: 50px;
        background-color: #f8f9fa;
    }

    #updateTrackerModal .custom-select:focus {
        box-shadow: 0 0 0 0.2rem rgba(58, 123, 213, 0.25);
        border-color: #012642;
    }

    #updateTrackerModal .btn-primary {
        background: linear-gradient(135deg, #012642 0%, #012642 100%);
        border: none;
        transition: all 0.3s ease;
    }

    #updateTrackerModal .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(43, 93, 176, 0.3);
    }

    #updateTrackerModal .tracking-status .badge-primary {
        background-color: #012642;
        font-weight: normal;
    }

    #updateTrackerModal .card-header {
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }

    /* Loader styles */
    #updateTrackerModal .spinner-border {
        width: 1.5rem;
        height: 1.5rem;
    }

    /* Update the status timestamp */
    #updateTrackerModal #statusUpdateTime:empty:before {
        content: "Just now";
        opacity: 0.8;
    }
</style>

<script>
    // Set current time for status update
    document.addEventListener('DOMContentLoaded', function() {
        const updateTimeEl = document.getElementById('statusUpdateTime');
        if(updateTimeEl) {
            const now = new Date();
            updateTimeEl.textContent = now.toLocaleString();
        }
    });
</script>

@include('cargo::adminLte.pages.consignments.editor.import-modal')
@include('cargo::adminLte.pages.consignments.editor.index')

@endsection

<!-- Scripts -->
@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>

<script>
    $(document).ready(function () {
        // Initialize DataTable with state saving
        const dataTable = $('.table').DataTable({
            "paging": true,
            "searching": false,
            "ordering": true,
            "info": true,
            "lengthMenu": [10, 25, 50, 100],
            "columnDefs": [
                { "orderable": false, "targets": 8 }
            ],
            "stateSave": true, // Enable state saving
            "stateDuration": -1 // Save state indefinitely
        });

        // Handle update tracker button click - works for dynamically loaded rows
        $(document).on('click', '.update-tracker-btn', function() {
            const consignmentId = $(this).data('id');
            const cargoType = $(this).data('cargo_type') || 'air';
            
            // Show loaders
            $('#currentStageLoader').removeClass('d-none');
            $('#statusSelectLoader').removeClass('d-none');
            $('#currentStageName').text('');
            $('#currentStageDescription').text('');
            $('#trackerStatus').html('<option value="">Loading...</option>');
            $('#updateTrackerBtn').prop('disabled', true);

            // Update form action
            $('#updateTrackerForm').attr('action', `/consignment/tracker/update/${consignmentId}`);
            
            // Fetch tracking stages from the database based on cargo type
            $.ajax({
                url: '/api/tracking-stages',
                method: 'GET',
                data: { cargo_type: cargoType },
                success: function(stages) {
                    // Populate the select dropdown
                    const $select = $('#trackerStatus');
                    $select.empty();
                    
                    if (stages.length > 0) {
                        stages.forEach((stage) => {
                            const option = new Option(stage.description, stage.id);
                            $select.append(option);
                        });
                    } else {
                        $select.append(new Option('No stages available', ''));
                    }

                    // Hide select loader
                    $('#statusSelectLoader').addClass('d-none');
                    
                    // Fetch and display current stage details
                    $.ajax({
                        url: '/api/get-current-stage',
                        method: 'GET',
                        data: { consignment_id: consignmentId },
                        success: function(data) {
                            $('#currentStageName').text(data.stage_name || 'Unknown');
                            $('#currentStageDescription').text(data.stage_description || 'No details available.');
                            
                            // Set the select dropdown to the current stage
                            if (data.stage_id) {
                                $select.val(data.stage_id);
                            } else if (data.stage_description) {
                                $select.find('option').each(function() {
                                    if ($(this).text() === data.stage_description) {
                                        $(this).prop('selected', true);
                                    }
                                });
                            }
                            
                            // Hide stage loader
                            $('#currentStageLoader').addClass('d-none');
                            $('#updateTrackerBtn').prop('disabled', false);
                        },
                        error: function(error) {
                            $('#currentStageName').text('Error loading stage');
                            $('#currentStageDescription').text('Failed to load current stage details.');
                            $('#currentStageLoader').addClass('d-none');
                            $('#updateTrackerBtn').prop('disabled', false);
                        }
                    });
                },
                error: function(error) {
                    console.error('Error fetching tracking stages:', error);
                    $('#trackerStatus').html('<option value="">Error loading stages</option>');
                    $('#statusSelectLoader').addClass('d-none');
                    $('#updateTrackerBtn').prop('disabled', false);
                }
            });

            // Update modal details
            $('#conName').text($(this).data('consignee_name') || '');
            $('#modalTracking').text($(this).data('consignment_code') || '');
            $('#sourceDestination').text($(this).data('source') || '');
            $('#finalDestination').text($(this).data('destination') || '');
            $('#conLastUpdate').text($(this).data('updated_at') || '');
            
            // Set consignment ID
            $('#consignmentId').val(consignmentId);
        });

        // Initialize Dropzone
        Dropzone.autoDiscover = false;

        let myDropzone = new Dropzone("#importForm", {
            url: "{{ route('consignment.import') }}",
            maxFilesize: 10, // MB
            maxFiles: 1,
            acceptedFiles: ".xlsx,.xls,.csv",
            autoProcessQueue: false,
            dictDefaultMessage: `<div class="text-center p-4">
                <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-muted"></i>
                <h6>Drag and drop your Excel file here</h6>
                <span class="btn btn-sm btn-light mt-2">or click to browse</span>
            </div>`,
            dictFileTooBig: "File is too big). Max filesize: 1500MB.",
            dictInvalidFileType: "Invalid file type. Please upload Excel or CSV files only.",
            addRemoveLinks: true,
            dictRemoveFile: "Remove file",
        });

        // Upload button click handler
        $("#uploadBtn").click(function() {
            if (myDropzone.getQueuedFiles().length > 0) {
                // Show progress bar and start processing
                $("#importStatus").removeClass("d-none");
                simulateProgress();
                myDropzone.processQueue();

            } else {
                alert("Please select a file to upload");
            }
        });

        // File added handler
        myDropzone.on("addedfile", function(file) {
            console.log("File added: " + file.name);
        });

        // Success handler
        myDropzone.on("success", function(file, response) {
            console.log("Upload successful");
            $("#importProgressBar").css("width", "100%").attr("aria-valuenow", 100);
            $("#importProgressText").text("Upload complete! Refreshing...");

            setTimeout(function() {
                window.location.reload();
            }, 1500);
        });

        // Error handler
        myDropzone.on("error", function(file, errorMessage) {
            console.error("Upload error:", errorMessage);
            $("#importProgressBar").addClass("bg-danger").removeClass("bg-success");
            $("#importProgressText").text("Error: " + errorMessage);
        });

        // Function to simulate progress while processing
        function simulateProgress() {
            let progress = 0;
            const interval = setInterval(function() {
                progress += Math.floor(Math.random() * 15);
                if (progress > 90) {
                    clearInterval(interval);
                    return;
                }
                $("#importProgressBar").css("width", progress + "%").attr("aria-valuenow", progress);
                $("#importProgressText").text("Processing... " + progress + "%");
            }, 600);
        }
    });
</script>
@endsection
