@extends('cargo::adminLte.layouts.master')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 mb-0">{{ __('Branch monitoring') }}</h1>
            <a id="access-preview" class="btn btn-outline-secondary disabled" target="_blank" rel="noopener">
                {{ __('Preview selected user access') }}
            </a>
        </div>

        <form class="card card-body mb-3" method="get">
            <div class="row g-2">
                <div class="col-md-3">
                    <label for="branch_id">{{ __('Branch') }}</label>
                    <select id="branch_id" name="branch_id" class="form-control">
                        <option value="">{{ __('All branches') }}</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ ($filters['branch_id'] ?? null) == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="user_id">{{ __('User') }}</label>
                    <select id="user_id" name="user_id" class="form-control">
                        <option value="">{{ __('All users') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ ($filters['user_id'] ?? null) == $user->id ? 'selected' : '' }}>
                                {{ $user->name }} ({{ $user->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="consignment_id">{{ __('Consignment ID') }}</label>
                    <input id="consignment_id" type="number" min="1" name="consignment_id" value="{{ $filters['consignment_id'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="customer_id">{{ __('Customer ID') }}</label>
                    <input id="customer_id" type="number" min="1" name="customer_id" value="{{ $filters['customer_id'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="from">{{ __('From') }}</label>
                    <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label for="to">{{ __('To') }}</label>
                    <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="custom-control custom-checkbox">
                        <input id="include_unassigned" class="custom-control-input" type="checkbox" name="include_unassigned" value="1" {{ !empty($filters['include_unassigned']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="include_unassigned">{{ __('Include legacy unassigned audit events') }}</label>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100">{{ __('Apply filters') }}</button>
                </div>
            </div>
        </form>

        @if (!$auditScopeAvailable)
            <div class="alert alert-warning">
                {{ __('Branch-scoped audit events will appear after the audit log migration has been applied.') }}
            </div>
        @endif

        <div class="row mb-3">
            <div class="col-md-6"><div class="card card-body"><strong>{{ $summary['shipments'] }}</strong><span>{{ __('Shipments') }}</span></div></div>
            <div class="col-md-6"><div class="card card-body"><strong>{{ $summary['audit_events'] }}</strong><span>{{ __('Audit events') }}</span></div></div>
        </div>

        <div class="card mb-3">
            <div class="card-header">{{ __('Recent shipments') }}</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Consignment') }}</th><th>{{ __('Created') }}</th></tr></thead>
                    <tbody>
                        @forelse ($shipments as $shipment)
                            <tr><td>{{ $shipment->code }}</td><td>{{ $shipment->branch?->name ?? __('Unassigned') }}</td><td>{{ $shipment->client?->name ?? '-' }}</td><td>{{ $shipment->consignment_id ?? '-' }}</td><td>{{ $shipment->created_at }}</td></tr>
                        @empty
                            <tr><td colspan="5">{{ __('No matching shipments.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">{{ __('Recent audit events') }}</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>{{ __('Event') }}</th><th>{{ __('User') }}</th><th>{{ __('Branch') }}</th><th>{{ __('When') }}</th></tr></thead>
                    <tbody>
                        @forelse ($audits as $audit)
                            <tr><td>{{ $audit->event }}</td><td>{{ $audit->user?->name ?? __('System') }}</td><td>{{ $audit->branch_id ?? __('Legacy / unassigned') }}</td><td>{{ $audit->created_at }}</td></tr>
                        @empty
                            <tr><td colspan="4">{{ __('No matching audit events.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const userSelect = document.getElementById('user_id');
            const preview = document.getElementById('access-preview');
            const routeTemplate = @json(fr_route('branch-monitoring.preview', ['user' => '__user__']));

            function setPreviewLink() {
                if (!userSelect.value) {
                    preview.classList.add('disabled');
                    preview.removeAttribute('href');
                    return;
                }

                preview.href = routeTemplate.replace('__user__', userSelect.value);
                preview.classList.remove('disabled');
            }

            userSelect.addEventListener('change', setPreviewLink);
            setPreviewLink();
        }());
    </script>
@endpush
