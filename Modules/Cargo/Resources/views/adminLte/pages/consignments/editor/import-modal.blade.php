<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="importModalLabel">
                    <i class="fas fa-file-excel mr-2"></i>Import Consignments
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-cloud-upload-alt fa-3x text-primary"></i>
                    </div>
                    <h6 class="font-weight-bold">Upload Consignment Data</h6>
                    <p class="text-muted small">Upload first, then review the sheet, map columns and confirm. Nothing is imported at this step.</p>
                </div>

                <form id="importForm" action="{{ route('consignment.import') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    
                    <!-- Shipment Type Selection -->
                    <div class="form-group mb-4">
                        <label class="font-weight-bold">Shipment Type</label>
                        <div class="d-flex mt-2">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="shipmentTypeSea" name="shipment_type" value="sea" class="custom-control-input" checked>
                                <label class="custom-control-label" for="shipmentTypeSea">
                                    <i class="fas fa-ship text-primary mr-1"></i> Sea Freight
                                </label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="shipmentTypeAir" name="shipment_type" value="air" class="custom-control-input">
                                <label class="custom-control-label" for="shipmentTypeAir">
                                    <i class="fas fa-plane text-primary mr-1"></i> Air Freight
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label for="consignment_status" class="font-weight-bold">Consignment Status <span class="text-danger">*</span></label>
                        <select id="consignment_status" name="consignment_status" class="form-control" required>
                            <option value="" selected disabled>Choose consignment status</option>
                            <option value="pending">Pending</option>
                            <option value="dispatched">Dispatched</option>
                            <option value="in_transit">In transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="canceled">Canceled</option>
                        </select>
                        <small class="form-text text-muted">Choose the consignment's current operational status. Delivered is not selected automatically.</small>
                    </div>
                    
                    <!-- File Upload Section -->
                    <div class="form-group mb-4">
                        <label for="excel_file" class="font-weight-bold">Select File</label>
                        <div class="input-group">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                                <label class="custom-file-label" for="excel_file">Choose file...</label>
                            </div>
                        </div>
                        <small class="form-text text-muted">Maximum file size: 10MB</small>
                    </div>
                    
                    <!-- Additional Notes -->
                    <div class="form-group mb-4">
                        <label for="import_notes" class="font-weight-bold">Additional Notes (Optional)</label>
                        <textarea class="form-control" id="import_notes" name="import_notes" rows="2" placeholder="Any special instructions for processing this import..."></textarea>
                    </div>

                    <!-- Progress Bar (initially hidden) -->
                    <div id="importStatus" class="mt-3 d-none">
                        <div class="progress" style="height: 10px;">
                            <div id="importProgressBar"
                                class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                                role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0"
                                aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted" id="importProgressText">Processing...</small>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="#" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download mr-1"></i> Download Template
                            </a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-upload mr-1"></i> Upload & Preview
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// File input label update
document.getElementById('excel_file').addEventListener('change', function(e) {
    var fileName = e.target.files[0] ? e.target.files[0].name : 'Choose file...';
    var label = document.querySelector('.custom-file-label');
    label.textContent = fileName;
});

// The upload redirects to the preview screen. Keep the progress indication honest:
// this is upload/parsing only; database records are not created yet.
document.getElementById('importForm').addEventListener('submit', function() {
    document.getElementById('importStatus').classList.remove('d-none');
    
    // Simulate progress (in production, this would be AJAX-based)
    var progress = 0;
    var progressBar = document.getElementById('importProgressBar');
    var progressText = document.getElementById('importProgressText');
    
    var interval = setInterval(function() {
        progress += 5;
        progressBar.style.width = progress + '%';
        progressBar.setAttribute('aria-valuenow', progress);
        
        if (progress < 30) {
            progressText.textContent = 'Uploading file...';
        } else if (progress < 90) {
            progressText.textContent = 'Reading spreadsheet for preview...';
        } else {
            progressText.textContent = 'Completing import...';
        }
        
        if (progress >= 100) {
            clearInterval(interval);
            progressText.textContent = 'Opening preview...';
        }
    }, 150);
});
</script>
