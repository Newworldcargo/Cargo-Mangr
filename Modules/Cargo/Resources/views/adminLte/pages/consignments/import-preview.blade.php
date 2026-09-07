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
    @if($batch->status === 'completed')<div class="alert alert-success"><strong>Import completed.</strong> {{ $batch->result['imported'] ?? 0 }} shipment(s) were created.</div>@endif

    <form id="mappingForm" method="POST" action="{{ route('consignment.import.preview.update', $batch->uuid) }}">
        @csrf
        <input id="headerRow" type="hidden" name="header_row" value="{{ $batch->header_row }}">

        <div class="card mb-3">
            <div class="card-header"><strong>1. Choose where the table starts</strong><span class="text-muted ml-2">Select the row containing the column titles.</span></div>
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4 form-group"><label>Worksheet</label><select name="selected_sheet" class="form-control" onchange="this.form.submit()">@foreach($sheets as $sheet)<option value="{{ $sheet }}" @selected($batch->selected_sheet === $sheet)>{{ $sheet }}</option>@endforeach</select></div>
                    <div class="col-md-4 form-group"><label>Data begins on row</label><input id="dataStartRow" name="data_start_row" type="number" min="{{ $batch->header_row + 1 }}" value="{{ $batch->data_start_row }}" class="form-control"><small class="text-muted">Normally the row immediately below the titles.</small></div>
                    <div class="col-md-4 form-group text-md-right"><button type="button" class="btn btn-outline-primary" data-toggle="collapse" data-target="#titleRowChooser">Change title row</button></div>
                </div>
                <div id="titleRowChooser" class="collapse">
                    <div class="alert alert-info py-2">Click <strong>Use as titles</strong> on the row containing headings such as HAWB, Consignee, Weight and Pieces.</div>
                    <div class="table-responsive" style="max-height:390px"><table class="table table-sm table-bordered table-hover mb-0"><tbody>
                    @foreach($rows->take(40) as $candidate)
                        <tr class="{{ $candidate->spreadsheet_row == $batch->header_row ? 'table-primary' : '' }}">
                            <td class="text-nowrap"><button type="button" class="btn btn-sm {{ $candidate->spreadsheet_row == $batch->header_row ? 'btn-primary' : 'btn-outline-secondary' }} choose-title-row" data-row="{{ $candidate->spreadsheet_row }}">{{ $candidate->spreadsheet_row == $batch->header_row ? 'Selected' : 'Use as titles' }}</button></td>
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
                <div class="col-md-4 form-group"><label>Consignment / container code <span class="text-danger">*</span></label><input name="consignment_code" value="{{ $batch->consignment_code }}" class="form-control" placeholder="e.g. D032"></div>
                <div class="col-md-4 form-group"><label>One destination for the whole file</label><input name="default_destination" value="{{ $batch->default_destination }}" class="form-control" placeholder="Leave blank if the sheet has a destination column"><small class="text-muted">A mapped destination column takes priority.</small></div>
                <div class="col-md-4 form-group"><label>Operational branch <span class="text-danger">*</span></label><select name="branch_id" class="form-control"><option value="">Choose branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($batch->branch_id == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            </div>
            <div class="row">
                <div class="col-md-3 form-group"><label>Origin country <span class="text-danger">*</span></label><select name="from_country_id" class="form-control"><option value="">Select</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected($batch->from_country_id == $country->id)>{{ $country->name }}</option>@endforeach</select></div>
                <div class="col-md-3 form-group"><label>Origin state <span class="text-danger">*</span></label><select name="from_state_id" class="form-control"><option value="">Select</option>@foreach($states as $state)<option value="{{ $state->id }}" @selected($batch->from_state_id == $state->id)>{{ $state->name }}</option>@endforeach</select></div>
                <div class="col-md-3 form-group"><label>Destination country <span class="text-danger">*</span></label><select name="to_country_id" class="form-control"><option value="">Select</option>@foreach($countries as $country)<option value="{{ $country->id }}" @selected($batch->to_country_id == $country->id)>{{ $country->name }}</option>@endforeach</select></div>
                <div class="col-md-3 form-group"><label>Destination state <span class="text-danger">*</span></label><select name="to_state_id" class="form-control"><option value="">Select</option>@foreach($states as $state)<option value="{{ $state->id }}" @selected($batch->to_state_id == $state->id)>{{ $state->name }}</option>@endforeach</select></div>
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-header"><strong>3. Match your titles to system fields</strong><span class="text-muted ml-2">Suggestions come from your selected title row.</span></div><div class="card-body"><div class="row">
            @foreach($fields as $field => $definition)
                <div class="col-md-4 form-group"><label>{{ $definition['label'] }} @if($definition['required'])<span class="text-danger">*</span>@endif</label><select class="form-control" name="mapping[{{ $field }}]"><option value="">Not mapped</option>@foreach($header as $column => $heading)@if(trim((string) $heading) !== '')<option value="{{ $column }}" @selected(($batch->mappings[$field] ?? '') === $column)>{{ $heading }} (Column {{ $column }})</option>@endif @endforeach</select></div>
            @endforeach
        </div><div class="alert alert-warning mb-0"><strong>Phone number is required.</strong> A file without a phone-number column cannot be confirmed.</div></div></div>

        <div class="card"><div class="card-header d-flex flex-wrap justify-content-between"><strong>4. Data preview</strong><span>Detected: {{ $batch->summary['detected'] ?? 0 }} · Valid: {{ $batch->summary['valid'] ?? 0 }} · Invalid: {{ $batch->summary['invalid'] ?? 0 }} · Duplicates: {{ $batch->summary['duplicate'] ?? 0 }}</span></div><div class="card-body p-0 table-responsive"><table class="table table-sm table-bordered table-hover mb-0">
            <thead class="thead-light"><tr><th>Import</th><th>Row</th><th>Status</th>@foreach($header as $column => $heading)@if(trim((string) $heading) !== '')<th>{{ $heading }}</th>@endif @endforeach<th>Issues</th></tr></thead>
            <tbody>@forelse($rows->where('spreadsheet_row','>=',$batch->data_start_row) as $row)<tr><td><input type="checkbox" name="included[{{ $row->id }}]" value="1" @checked($row->included) @disabled($batch->status === 'completed')></td><td>{{ $row->spreadsheet_row }}</td><td><span class="badge badge-{{ $row->status === 'valid' ? 'success' : ($row->status === 'duplicate' ? 'warning' : ($row->status === 'invalid' ? 'danger' : 'secondary')) }}">{{ ucfirst($row->status) }}</span></td>@foreach($header as $column => $heading)@if(trim((string) $heading) !== '')<td>{{ $row->raw_values[$column] ?? '' }}</td>@endif @endforeach<td class="small text-danger">{{ implode(' ', $row->validation_errors ?? []) ?: implode(' ', $row->validation_warnings ?? []) }}</td></tr>@empty<tr><td colspan="99" class="text-center text-muted py-4">No data rows exist below the selected title row.</td></tr>@endforelse</tbody>
        </table></div></div>
        @if($batch->status !== 'completed')<div class="mt-3 text-right"><button class="btn btn-primary px-4">Save and refresh preview</button></div>@endif
    </form>
    @if($batch->status !== 'completed')<form method="POST" action="{{ route('consignment.import.confirm', $batch->uuid) }}" class="mt-3 text-right">@csrf<button class="btn btn-success px-4" onclick="return confirm('Create the selected valid shipments now?')" @disabled(($batch->summary['selected'] ?? 0) < 1)>Confirm import ({{ $batch->summary['selected'] ?? 0 }} selected)</button></form>@endif
</div>
<script>
document.querySelectorAll('.choose-title-row').forEach(function (button) {
    button.addEventListener('click', function () {
        var row = Number(this.dataset.row);
        document.getElementById('headerRow').value = row;
        document.getElementById('dataStartRow').value = row + 1;
        document.getElementById('mappingForm').submit();
    });
});
</script>
@endsection
