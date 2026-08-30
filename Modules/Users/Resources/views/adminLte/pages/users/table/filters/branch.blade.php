@if (in_array('branch_id', $filters))
    <div class="mb-10">
        <label class="form-label fs-5 fw-bold mb-3">{{ __('Branch') }}:</label>
        <select class="form-control" id="{{ $table_id }}_branch_id">
            <option value="">{{ __('All branches') }}</option>
            @foreach (\Modules\Cargo\Entities\Branch::where('is_archived', 0)->orderBy('name')->get(['id', 'name']) as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
            @endforeach
        </select>
    </div>
    @push('js-component')
        <script>
            $(function () {
                const table = $('#{{ $table_id }}').DataTable();
                $('#{{ $table_id }}_branch_id').on('change', function () {
                    const branchId = this.value;
                    table.one('preXhr.dt', function (event, settings, data) {
                        data.filter = data.filter || {};
                        data.filter.branch_id = branchId;
                    }).ajax.reload();
                });
            });
        </script>
    @endpush
@endif
