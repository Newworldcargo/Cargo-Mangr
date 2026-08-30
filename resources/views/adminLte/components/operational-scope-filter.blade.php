@once
    <style>
        .operational-scope-filter { max-width: 100%; }
        .operational-scope-filter .scope-filter-card {
            display: flex; flex-wrap: wrap; align-items: end; justify-content: flex-end;
            gap: .65rem; padding: .75rem 1rem; border: 1px solid #e5e7eb;
            border-radius: .65rem; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .operational-scope-filter .scope-filter-title { color: #374151; font-size: .84rem; font-weight: 700; white-space: nowrap; }
        .operational-scope-filter .scope-filter-field { margin: 0; min-width: 0; }
        .operational-scope-filter .scope-filter-field span { display: block; margin-bottom: .2rem; color: #6b7280; font-size: .72rem; font-weight: 600; }
        .operational-scope-filter .scope-filter-field select { width: 13.5rem; max-width: 100%; font-size: .82rem; }
        .operational-scope-filter .scope-filter-actions { display: flex; gap: .4rem; align-items: center; }
        @media (max-width: 575.98px) {
            .operational-scope-filter, .operational-scope-filter .scope-filter-card { width: 100%; }
            .operational-scope-filter .scope-filter-card { justify-content: stretch; }
            .operational-scope-filter .scope-filter-field, .operational-scope-filter .scope-filter-field select, .operational-scope-filter .scope-filter-actions { width: 100%; }
            .operational-scope-filter .scope-filter-actions .btn { flex: 1; }
        }
    </style>
@endonce

<form method="GET" action="{{ $scopeFilterAction }}" class="operational-scope-filter">
    @foreach($scopeFilterHidden ?? [] as $name => $value)
        @if($value !== null && $value !== '')
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endforeach
    <div class="scope-filter-card">
        <div class="scope-filter-title">Show records for</div>
        @if($scopeOptions['can_filter_branch'])
            <label class="scope-filter-field">
                <span>Branch</span>
                <select name="branch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All branches I can access</option>
                    @foreach($scopeOptions['branches'] as $scopeBranch)
                        <option value="{{ $scopeBranch->id }}" @selected($selectedScope['selectedBranchId'] === $scopeBranch->id)>{{ $scopeBranch->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @if($scopeOptions['can_filter_user'])
            <label class="scope-filter-field">
                <span>Team member</span>
                <select name="user_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All team members I can access</option>
                    @foreach($scopeOptions['users'] as $scopeUser)
                        <option value="{{ $scopeUser->id }}" @selected($selectedScope['selectedUserId'] === $scopeUser->id && request('scope') !== 'self')>{{ $scopeUser->name }} ({{ $scopeUser->email }})</option>
                    @endforeach
                </select>
            </label>
        @endif
        <div class="scope-filter-actions">
            <button name="scope" value="self" class="btn btn-sm {{ request('scope') === 'self' ? 'btn-primary' : 'btn-outline-primary' }}">{{ $scopeFilterSelfLabel ?? 'Only my work' }}</button>
            @if(request()->hasAny(['branch_id', 'user_id', 'scope']))
                <a href="{{ $scopeFilterClearUrl }}" class="btn btn-sm btn-light">Reset</a>
            @endif
        </div>
    </div>
</form>
