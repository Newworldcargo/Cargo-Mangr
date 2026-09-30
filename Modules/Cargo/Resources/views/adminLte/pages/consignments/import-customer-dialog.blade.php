<style>
    #importCustomerDialog { border: 1px solid #dce1e7; border-radius: 8px; padding: 0; width: min(680px, calc(100vw - 24px)); max-height: calc(100dvh - 32px); color: #202830; }
    #importCustomerDialog::backdrop { background: rgba(0,0,0,.45); }
    #importCustomerDialog .customer-dialog-content { padding: 24px; overflow-wrap: anywhere; }
    #importCustomerResults { max-height: 300px; overflow-y: auto; }
    #importCustomerResults button { display: block; width: 100%; text-align: left; white-space: normal; border: 1px solid #dde2e8; border-radius: 4px; margin-bottom: 8px; padding: 12px; background: #fff; color: #202830; }
    #importCustomerResults button:hover, #importCustomerResults button:focus { background: #edf4ff; border-color: #2866ae; }
    #importCustomerDialog [hidden] { display: none !important; }
</style>
<dialog id="importCustomerDialog" aria-labelledby="importCustomerTitle" data-search-url="{{ route('consignment.import.customers', $batch->uuid) }}">
    <div class="customer-dialog-content">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div><h4 id="importCustomerTitle">Choose customer</h4><p id="importCustomerSource" class="text-muted mb-0"></p></div>
            <button type="button" class="btn btn-light ml-2" data-close-customer aria-label="Close customer picker">&times;</button>
        </div>
        <div id="importCustomerError" class="alert alert-danger" role="alert" hidden></div>
        <div id="importCustomerPicker">
            <label for="importCustomerSearch">Search name, email, phone or customer ID</label>
            <input id="importCustomerSearch" type="search" class="form-control mb-3" autocomplete="off">
            <div id="importCustomerSearchStatus" role="status" class="text-muted mb-2"></div>
            <div id="importCustomerResults"></div>
            <div id="importCustomerChoice" class="alert alert-info mt-3" hidden></div>
            <div class="d-flex flex-wrap mt-3" style="gap:8px">
                <button type="button" id="importCustomerConfirm" class="btn btn-primary" disabled>Confirm customer</button>
                <button type="button" id="importCustomerReset" class="btn btn-outline-secondary">Use spreadsheet matching</button>
                @can('create-customers')<button type="button" id="importCustomerAdd" class="btn btn-outline-primary">Add new customer</button>@endcan
            </div>
        </div>
        @can('create-customers')
        <form id="importCustomerCreate" hidden>
            <p class="text-muted">The customer profile will be created and selected for this row. The shipment is only created when you confirm the import.</p>
            <div class="row">
                <div class="col-sm-6 form-group"><label for="newCustomerFirst">First name</label><input id="newCustomerFirst" name="first_name" class="form-control" required minlength="2" maxlength="80" autocomplete="given-name"></div>
                <div class="col-sm-6 form-group"><label for="newCustomerLast">Last name</label><input id="newCustomerLast" name="last_name" class="form-control" required minlength="2" maxlength="80" autocomplete="family-name"></div>
                <div class="col-12 form-group"><label for="newCustomerEmail">Email (optional)</label><input id="newCustomerEmail" name="email" type="email" class="form-control" maxlength="191" autocomplete="email"></div>
                <div class="col-sm-6 form-group"><label for="newCustomerPhone">Phone with country code</label><input id="newCustomerPhone" name="phone" type="tel" class="form-control" required maxlength="40" autocomplete="tel"></div>
                <div class="col-sm-6 form-group"><label for="newCustomerPhone2">Second phone (optional)</label><input id="newCustomerPhone2" name="phone_2" type="tel" class="form-control" maxlength="40"></div>
                <div class="col-sm-6 form-group"><label for="newCustomerContact">Contact name (optional)</label><input id="newCustomerContact" name="contact_name" class="form-control" maxlength="160"></div>
                <div class="col-sm-6 form-group"><label for="newCustomerID">National ID (optional)</label><input id="newCustomerID" name="national_id" class="form-control" maxlength="100"></div>
                <div class="col-12 form-group"><label for="newCustomerAddress">Address (optional)</label><textarea id="newCustomerAddress" name="address" class="form-control" rows="2" maxlength="500"></textarea></div>
            </div>
            <p class="small text-muted">Branch: {{ optional($branches->firstWhere('id', $batch->pickup_branch_id))->name ?? 'Choose a pickup branch in the import setup first' }}. Account access requires verification; no password is shared here.</p>
            <div class="d-flex flex-wrap" style="gap:8px"><button type="button" id="importCustomerBack" class="btn btn-outline-secondary">Back to customer search</button><button type="submit" class="btn btn-primary">Create and select customer</button></div>
        </form>
        @endcan
        <div id="importCustomerSaving" class="mt-3" role="status" hidden>Saving customer selection...</div>
    </div>
</dialog>
<div id="importCustomerLiveStatus" class="sr-only" role="status"></div>
