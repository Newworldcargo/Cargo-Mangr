@extends('cargo::adminLte.layouts.master')
@section('pageTitle', 'Import Preview')
@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
        <div><h3 class="mb-1">Import preview & column mapping</h3><p class="text-muted mb-0">{{ $batch->original_filename }} · No shipment is created until you confirm.</p></div>
        <a class="btn btn-outline-secondary" href="{{ route('consignment.index') }}">Back to consignments</a>
    </div>
    @if(isset($errors) && $errors->any())<div class="alert alert-danger"><strong>Please correct these items:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($batch->status === 'completed')
        <div class="alert alert-success"><strong>{{ $batch->mode === 'update' ? 'Update completed.' : 'Import completed.' }}</strong> {{ $batch->result['created'] ?? $batch->result['imported'] ?? 0 }} added, {{ $batch->result['updated'] ?? 0 }} updated and {{ $batch->result['unchanged'] ?? 0 }} unchanged.</div>
    @elseif($batch->mode === 'update' && $targetConsignment)
        <div class="alert alert-warning border-warning"><h5 class="alert-heading"><i class="fas fa-exclamation-triangle mr-2"></i>Updating existing consignment {{ $targetConsignment->consignment_code }}</h5><p class="mb-0">This consignment already has {{ $targetConsignment->shipments_count }} shipment(s). The preview will show what will be added, updated or left unchanged. Existing shipments missing from this file will not be deleted.</p></div>
    @else
        <div class="alert alert-info"><strong>Creating a new consignment.</strong> If the consignment code already exists, this page will automatically switch to update mode after you save the preview.</div>
    @endif

    <form id="mappingForm" method="POST" action="{{ route('consignment.import.preview.update', $batch->uuid) }}">
        @csrf
        <input id="headerRow" type="hidden" name="header_row" value="{{ $batch->header_row }}">

        <div class="card mb-3">
            <div class="card-header"><strong>1. Choose where the table starts</strong><span class="text-muted ml-2">Select the row containing the column titles.</span></div>
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4 form-group"><label>Worksheet</label><select name="selected_sheet" class="form-control">@foreach($sheets as $sheet)<option value="{{ $sheet }}" {{ $batch->selected_sheet === $sheet ? 'selected' : '' }}>{{ $sheet }}</option>@endforeach</select></div>
                    <div class="col-md-4 form-group"><label>Data begins on row</label><input id="dataStartRow" name="data_start_row" type="number" min="{{ $batch->header_row + 1 }}" value="{{ $batch->data_start_row }}" class="form-control"><small class="text-muted">Normally the row immediately below the titles.</small></div>
                    <div class="col-md-4 form-group text-md-right"><button type="button" class="btn btn-outline-primary" data-toggle="collapse" data-target="#titleRowChooser">Change title row</button></div>
                </div>
                <div id="titleRowChooser" class="collapse">
                    <div class="alert alert-info py-2">Click <strong>Use as titles</strong> on the row containing headings such as HAWB, Consignee, Weight and Pieces.</div>
                    <div class="table-responsive" style="max-height:390px"><table class="table table-sm table-bordered table-hover mb-0"><tbody>
                    @foreach($rows->take(40) as $candidate)
                        <tr class="{{ $candidate->spreadsheet_row == $batch->header_row ? 'table-primary' : '' }}">
                            <td class="text-nowrap"><button type="submit" name="selected_header_row" value="{{ $candidate->spreadsheet_row }}" class="btn btn-sm {{ $candidate->spreadsheet_row == $batch->header_row ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $candidate->spreadsheet_row == $batch->header_row ? 'Selected' : 'Use as titles' }}</button></td>
                            <td class="font-weight-bold">Row {{ $candidate->spreadsheet_row }}</td>
                            @foreach($candidate->raw_values as $value)<td>{{ $value }}</td>@endforeach
                        </tr>
                    @endforeach
                    </tbody></table></div>
                </div>
            </div>
        </div>

        <div class="card mb-3"><div class="card-header"><strong>2. Consignment details</strong><span class="text-muted ml-2">These values apply to the whole uploaded table.</span></div><div class="card-body">
            <div class="row">
                <div class="col-md-4 form-group"><label>Consignment / container code <span class="text-danger">*</span></label><input name="consignment_code" value="{{ $batch->consignment_code }}" class="form-control" placeholder="e.g. D032" required></div>
                <div class="col-md-4 form-group"><label>Consignment status <span class="text-danger">*</span></label><select name="consignment_status" class="form-control" required><option value="">Choose status</option><option value="pending" {{ $batch->consignment_status === 'pending' ? 'selected' : '' }}>Pending</option><option value="dispatched" {{ $batch->consignment_status === 'dispatched' ? 'selected' : '' }}>Dispatched</option><option value="in_transit" {{ $batch->consignment_status === 'in_transit' ? 'selected' : '' }}>In transit</option><option value="delivered" {{ $batch->consignment_status === 'delivered' ? 'selected' : '' }}>Delivered</option><option value="canceled" {{ $batch->consignment_status === 'canceled' ? 'selected' : '' }}>Canceled</option></select><small class="text-muted">The current status of the whole consignment.</small></div>
                <div class="col-md-4 form-group"><label>Consignment date <span class="text-danger">*</span></label><input type="date" name="consignment_date" value="{{ optional($batch->consignment_date)->format('Y-m-d') }}" class="form-control" required><small class="text-muted">The actual date of this cargo, including historical consignments.</small></div>
                <div class="col-md-4 form-group"><label>Pickup branch <span class="text-danger">*</span></label><select name="pickup_branch_id" class="form-control" required><option value="">Choose pickup branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" {{ $batch->pickup_branch_id == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>@endforeach</select><small class="text-muted">Where this consignment starts.</small></div>
                <div class="col-md-4 form-group"><label>Destination branch <span class="text-danger">*</span></label><select name="destination_branch_id" class="form-control" required><option value="">Choose destination branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" {{ $batch->destination_branch_id == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>@endforeach</select><small class="text-muted">Where this consignment is going.</small></div>
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-header"><strong>3. Match your titles to system fields</strong><span class="text-muted ml-2">Suggestions come from your selected title row.</span></div><div class="card-body"><div class="row">
            @foreach($fields as $field => $definition)
                <div class="col-md-4 form-group"><label>{{ $definition['label'] }} @if($definition['required'])<span class="text-danger">*</span>@endif</label><select class="form-control" name="mapping[{{ $field }}]"><option value="">Not mapped</option>@foreach($header as $column => $heading)@if(trim((string) $heading) !== '')<option value="{{ $column }}" {{ ($batch->mappings[$field] ?? '') === $column ? 'selected' : '' }}>{{ $heading }} (Column {{ $column }})</option>@endif @endforeach</select></div>
            @endforeach
        </div><div class="alert alert-warning mb-0"><strong>Phone number is required.</strong> A file without a phone-number column cannot be confirmed.</div></div></div>
        @if($batch->status !== 'completed')<div class="mb-3 text-right"><button class="btn btn-primary px-4">Save consignment details & refresh preview</button></div>@endif
    </form>

    @php($readyCount = ($batch->summary['new'] ?? 0) + ($batch->summary['update'] ?? 0) + ($batch->summary['unchanged'] ?? 0))
    @php($selectReadyByDefault = ($batch->summary['selected'] ?? 0) < 1)
    @php($setupComplete = $batch->consignment_code && $batch->consignment_status && $batch->consignment_date && $batch->pickup_branch_id && $batch->destination_branch_id && $batch->from_country_id && $batch->from_state_id && $batch->to_country_id && $batch->to_state_id)
    @php($removableCount = $batch->status === 'completed' ? $rows->filter(fn($row) => $row->status === 'imported' && $row->import_action === 'created' && $row->shipment_id)->count() : 0)
    <form method="POST" action="{{ $batch->status === 'completed' ? route('consignment.import.rows.remove', $batch->uuid) : route('consignment.import.confirm', $batch->uuid) }}" @if($batch->status === 'completed') data-confirm-message="Remove the selected shipments? Only newly created, unused shipments will be deleted. This cannot be undone." @endif>
        @csrf
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between">
                <strong>4. Data preview</strong>
                <span>New: {{ $batch->summary['new'] ?? 0 }} · Updating: {{ $batch->summary['update'] ?? 0 }} · Unchanged: {{ $batch->summary['unchanged'] ?? 0 }} · Invalid: {{ $batch->summary['invalid'] ?? 0 }} · Conflicts: {{ $batch->summary['conflict'] ?? 0 }}</span>
            </div>
            <div class="card-body border-bottom py-2">
                <div class="alert alert-info mb-0 py-2">
                    @if($batch->status === 'completed')
                        Select only wrongly imported rows. Updated or previously existing shipments cannot be removed here, and shipments with later activity are protected.
                    @else
                        All ready rows are selected by default. Untick totals, notes or any row you do not want to import. Names and other text are removed from phone fields automatically.
                    @endif
                </div>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-bordered table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th class="text-nowrap"><input id="importRowsSelectAll" type="checkbox" class="mr-1"> {{ $batch->status === 'completed' ? 'Remove' : 'Import' }}</th>
                            <th>Row</th>
                            <th>Status</th>
                            <th style="min-width: 235px">Customer phone</th>
                            @foreach($header as $column => $heading)
                                @if(trim((string) $heading) !== '')<th>{{ $heading }}</th>@endif
                            @endforeach
                            <th style="min-width: 240px">Issues</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows->where('spreadsheet_row','>=',$batch->data_start_row)->where('status','!=','excluded') as $row)
                            @php($candidates = $phoneCandidates[$row->id] ?? [])
                            @php($phoneValue = $row->phone_override ?: (count($candidates) === 1 ? $candidates[0] : ''))
                            @php($phoneValue2 = $row->phone_override_2 ?: '')
                            <tr>
                                <td>
                                    @if($batch->status === 'completed')
                                        <input class="import-row-checkbox" type="checkbox" name="rows[{{ $row->id }}]" value="1" {{ $row->status !== 'imported' || $row->import_action !== 'created' || !$row->shipment_id ? 'disabled' : '' }}>
                                    @else
                                        <input class="import-row-checkbox" type="checkbox" name="included[{{ $row->id }}]" value="1" {{ ($row->included || ($selectReadyByDefault && in_array($row->status, ['new','update','unchanged']))) ? 'checked' : '' }} {{ in_array($row->status, ['invalid','conflict']) ? 'disabled' : '' }}>
                                    @endif
                                </td>
                                <td>{{ $row->spreadsheet_row }}</td>
                                <td>
                                    @php($statusColor = ['new'=>'success','update'=>'info','unchanged'=>'secondary','conflict'=>'warning','invalid'=>'danger','imported'=>'success','removed'=>'dark'][$row->status] ?? 'secondary')
                                    <span class="badge badge-{{ $statusColor }}">{{ ucfirst($row->status) }}</span>
                                    @if($batch->status === 'completed' && $row->import_action)<small class="d-block text-muted">{{ ucfirst($row->import_action) }}</small>@endif
                                </td>
                                <td>
                                    @if($batch->status === 'completed')
                                        <div>{{ $row->mapped_values['phone'] ?? '—' }}</div>
                                        @if(!empty($row->mapped_values['phone_2']))<div class="text-muted">{{ $row->mapped_values['phone_2'] }}</div>@endif
                                    @else
                                        <label class="small mb-1">Primary phone <span class="text-danger">*</span></label>
                                        <input type="text" name="phone_override[{{ $row->id }}]" value="{{ $phoneValue }}" list="phone-options-{{ $row->id }}" class="form-control form-control-sm {{ count($candidates) > 1 && !$row->phone_override ? 'border-danger' : '' }}" inputmode="tel" autocomplete="off" placeholder="{{ count($candidates) > 1 ? 'Choose a phone number' : 'Enter phone number' }}">
                                        @if($candidates)
                                            <datalist id="phone-options-{{ $row->id }}">
                                                @foreach($candidates as $candidate)<option value="{{ $candidate }}">+{{ $candidate }}</option>@endforeach
                                            </datalist>
                                        @endif
                                        @if(count($candidates) > 1)
                                            <label class="small mb-1 mt-2">Phone number 2 <span class="text-muted">(optional)</span></label>
                                            <input type="text" name="phone_override_2[{{ $row->id }}]" value="{{ $phoneValue2 }}" list="phone-options-{{ $row->id }}" class="form-control form-control-sm" inputmode="tel" autocomplete="off" placeholder="Leave blank to use one number">
                                            <small class="{{ !$row->phone_override ? 'text-danger' : 'text-muted' }} d-block mt-1">Found: {{ implode(' or ', array_map(fn($phone) => '+'.$phone, $candidates)) }}. Choose a primary number; add the other as Phone number 2 only when both belong to this customer.</small>
                                        @elseif($phoneValue2)
                                            <label class="small mb-1 mt-2">Phone number 2 <span class="text-muted">(optional)</span></label>
                                            <input type="text" name="phone_override_2[{{ $row->id }}]" value="{{ $phoneValue2 }}" class="form-control form-control-sm" inputmode="tel" autocomplete="off">
                                        @elseif(count($candidates) === 1)
                                            <small class="text-muted d-block mt-1">Found automatically: +{{ $candidates[0] }}</small>
                                        @endif
                                    @endif
                                </td>
                                @foreach($header as $column => $heading)
                                    @if(trim((string) $heading) !== '')<td>{{ $row->raw_values[$column] ?? '' }}</td>@endif
                                @endforeach
                                <td class="small {{ !empty($row->validation_errors) ? 'text-danger' : (!empty($row->validation_warnings) ? 'text-warning' : 'text-muted') }}">{{ implode(' ', $row->validation_errors ?? []) ?: implode(' ', $row->validation_warnings ?? []) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="99" class="text-center text-muted py-4">No data rows exist below the selected title row.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($batch->status !== 'completed')
            @if(!$setupComplete)
                <div class="alert alert-warning mt-3 mb-2"><strong>Save the consignment details first.</strong> Enter the container code and choose both branches in section 2, then click “Save consignment details & refresh preview”.</div>
            @endif
            <div class="mt-3 d-flex flex-wrap justify-content-end">
                <button type="submit" name="action" value="refresh" class="btn btn-outline-primary px-4 mr-2 mb-2">Apply phone corrections</button>
                <button type="submit" class="btn btn-success px-4 mb-2" {{ $readyCount < 1 || !$setupComplete ? 'disabled' : '' }}>{{ $batch->mode === 'update' ? 'Confirm update' : 'Confirm import' }} ({{ $readyCount }} ready)</button>
            </div>
        @elseif($removableCount > 0)
            <div class="mt-3 d-flex flex-wrap justify-content-end">
                <button type="submit" class="btn btn-danger px-4 mb-2">Remove selected imported rows</button>
            </div>
        @else
            <div class="alert alert-secondary mt-3">This historical import has no rows that can be safely removed from here.</div>
        @endif
    </form>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/consignment-import-preview.js') }}" defer></script>
@endsection
