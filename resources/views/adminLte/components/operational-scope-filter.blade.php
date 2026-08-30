@once
    <style>
        .operational-scope-filter { width: 100%; max-width: 100%; }
        .operational-scope-filter .scope-filter-shell { display: flex; align-items: center; justify-content: space-between; gap: 1rem; width: 100%; }
        .operational-scope-filter .scope-filter-card {
            display: flex; flex-wrap: wrap; align-items: end; justify-content: flex-end;
            gap: .65rem; padding: .65rem .8rem; border: 1px solid #e5e7eb;
            border-radius: .65rem; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .operational-scope-filter .scope-filter-title { color: #374151; font-size: .9rem; font-weight: 700; white-space: nowrap; }
        .operational-scope-filter .scope-filter-field { margin: 0; min-width: 0; }
        .operational-scope-filter .scope-filter-field span { display: block; margin-bottom: .2rem; color: #6b7280; font-size: .72rem; font-weight: 600; }
        .operational-scope-filter .scope-filter-field select { width: 13.5rem; max-width: 100%; font-size: .82rem; }
        .operational-scope-filter .scope-filter-actions { display: flex; gap: .4rem; align-items: center; }
        .operational-scope-filter .scope-filter-toggle { display: inline-flex; align-items: center; gap: .45rem; margin: 0; color: #374151; font-size: .8rem; font-weight: 600; white-space: nowrap; cursor: pointer; }
        .operational-scope-filter .scope-filter-toggle .form-check-input {
            appearance: none; -webkit-appearance: none; position: relative; width: 2.5rem; height: 1.35rem;
            margin: 0; border: 0; border-radius: 999px; background: #cbd5e1; cursor: pointer;
            transition: background .18s ease; outline: none; box-shadow: inset 0 0 0 1px rgba(15,23,42,.08);
        }
        .operational-scope-filter .scope-filter-toggle .form-check-input::after {
            content: ''; position: absolute; top: .18rem; left: .18rem; width: .99rem; height: .99rem;
            border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,.3); transition: transform .18s ease;
        }
        .operational-scope-filter .scope-filter-toggle .form-check-input:checked { background: #0d6efd; }
        .operational-scope-filter .scope-filter-toggle .form-check-input:checked::after { transform: translateX(1.15rem); }
        .operational-scope-filter .scope-filter-toggle .form-check-input:focus-visible { box-shadow: 0 0 0 3px rgba(13,110,253,.25); }
        @media (max-width: 575.98px) {
            .operational-scope-filter .scope-filter-shell, .operational-scope-filter .scope-filter-card { width: 100%; }
            .operational-scope-filter .scope-filter-shell { align-items: stretch; flex-direction: column; gap: .5rem; }
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
    <div class="scope-filter-shell">
        <div class="scope-filter-title">Show records for</div>
        <div class="scope-filter-card">
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
            <label class="scope-filter-toggle" title="Turn this on to see only records you created or processed">
                <input class="form-check-input" type="checkbox" role="switch" name="scope" value="self" @checked(request('scope') === 'self') onchange="this.form.submit()">
                <span>{{ $scopeFilterSelfLabel ?? 'Only my records' }}</span>
            </label>
            @if(request()->hasAny(['branch_id', 'user_id', 'scope']))
                <a href="{{ $scopeFilterClearUrl }}" class="btn btn-sm btn-light">Reset</a>
            @endif
        </div>
        </div>
    </div>
</form>
