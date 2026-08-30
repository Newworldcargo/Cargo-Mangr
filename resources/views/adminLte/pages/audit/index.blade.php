@extends('cargo::adminLte.layouts.master')

@php
    use Illuminate\Support\Str;
    $perPage = request('per_page', $logs->perPage());
@endphp

@section('pageTitle')
    Audit Logs
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">
                                <i class="fas fa-clipboard-check text-warning me-2"></i>
                                System Audit Logs
                            </h5>
                            <small class="text-muted">
                                Tracking {{ $logs->total() }} {{ Str::plural('event', $logs->total()) }} from newest to oldest.
                            </small>
                        </div>
                        <form method="GET" class="d-flex align-items-center gap-2">
                            <label for="per_page" class="text-muted small mb-0">Rows per page</label>
                            <select id="per_page"
                                    name="per_page"
                                    class="form-select form-select-sm"
                                    onchange="this.form.submit()">
                                @foreach([10, 20, 50, 100] as $size)
                                    <option value="{{ $size }}" @selected((int) $perPage === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="text-muted text-uppercase small">
                                        <th scope="col" style="width: 5%;">#</th>
                                        <th scope="col" style="width: 12%;">When</th>
                                        <th scope="col" style="width: 15%;">User</th>
                                        <th scope="col" style="width: 15%;">Event</th>
                                        <th scope="col">Description &amp; Changes</th>
                                        <th scope="col" style="width: 18%;">Context</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($logs as $log)
                                        @php
                                            $event = $log->event ? Str::headline($log->event) : 'Activity';
                                            $badgeClass = match (strtolower($log->event)) {
                                                'created', 'create', 'recorded' => 'bg-success',
                                                'updated', 'update', 'modified' => 'bg-info',
                                                'deleted', 'delete', 'removed' => 'bg-danger',
                                                'restored' => 'bg-warning text-dark',
                                                default => 'bg-secondary',
                                            };
                                            $changes = collect($log->new_values ?? [])
                                                ->keys()
                                                ->merge(array_keys($log->old_values ?? []))
                                                ->unique()
                                                ->filter();
                                        @endphp
                                        <tr>
                                            <td class="text-muted small">{{ $log->id }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ optional($log->created_at)->format('Y-m-d H:i') }}</div>
                                                <div class="text-muted small">{{ optional($log->created_at)->diffForHumans() }}</div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">{{ $log->user?->name ?? 'System' }}</div>
                                                <div class="text-muted small">{{ $log->user?->email ?? '—' }}</div>
                                            </td>
                                            <td>
                                                <span class="badge {{ $badgeClass }} text-uppercase fw-semibold">
                                                    {{ $event }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($log->description)
                                                    <div class="text-muted small mb-1">
                                                        {{ $log->description }}
                                                    </div>
                                                @endif
                                                @if($changes->isNotEmpty())
                                                    <div class="border-start border-3 border-warning ps-3 mt-2">
                                                        @foreach($changes as $field)
                                                            @php
                                                                $oldValue = data_get($log->old_values, $field);
                                                                $newValue = data_get($log->new_values, $field);
                                                                $fieldLabel = Str::headline($field);
                                                                $displayOld = is_array($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : (is_null($oldValue) ? 'null' : (string) $oldValue);
                                                                $displayNew = is_array($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : (is_null($newValue) ? 'null' : (string) $newValue);
                                                            @endphp
                                                            <div class="small text-muted mb-1">
                                                                <span class="fw-semibold text-dark">{{ $fieldLabel }}:</span>
                                                                @if($displayOld !== $displayNew && $displayOld !== 'null')
                                                                    <span class="text-danger text-decoration-line-through">{{ $displayOld }}</span>
                                                                    <span class="mx-1 text-secondary">→</span>
                                                                @endif
                                                                <span class="text-success fw-semibold">{{ $displayNew }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="text-muted small fst-italic">No value changes recorded.</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="small text-muted">
                                                    <div>
                                                        <i class="fas fa-cube text-primary me-1"></i>
                                                        {{ class_basename($log->auditable_type ?? 'N/A') }}@if($log->auditable_id)#{{ $log->auditable_id }}@endif
                                                    </div>
                                                    @if($log->ip_address)
                                                        <div>
                                                            <i class="fas fa-network-wired text-info me-1"></i>
                                                            {{ $log->ip_address }}
                                                        </div>
                                                    @endif
                                                    @if($log->user_agent)
                                                        <div class="text-truncate" style="max-width: 240px;">
                                                            <i class="fas fa-desktop text-secondary me-1"></i>
                                                            {{ Str::limit($log->user_agent, 80) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="fas fa-inbox fa-2x mb-2 text-secondary"></i>
                                                <p class="mb-0">No audit records found.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            Showing {{ $logs->firstItem() ?? 0 }} to {{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }} entries
                        </div>
                        @if ($logs->hasPages())
                            <nav aria-label="Audit log pagination" class="d-flex gap-2">
                                @if ($logs->onFirstPage())
                                    <span class="btn btn-sm btn-outline-secondary disabled">Previous</span>
                                @else
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ $logs->previousPageUrl() }}">Previous</a>
                                @endif
                                <span class="btn btn-sm btn-light disabled">Page {{ $logs->currentPage() }} / {{ $logs->lastPage() }}</span>
                                @if ($logs->hasMorePages())
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ $logs->nextPageUrl() }}">Next</a>
                                @else
                                    <span class="btn btn-sm btn-outline-secondary disabled">Next</span>
                                @endif
                            </nav>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
