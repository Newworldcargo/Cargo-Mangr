@php
    $salesAccess = app(\Modules\Billing\Services\SalesAccess::class);
    $showQuotations = $salesAccess->canView(auth()->user(), 'quotations');
    $showInvoices = $salesAccess->canView(auth()->user(), 'invoices');
    $showTransactions = auth()->user()?->can('access-finance-transactions');
    $showRefunds = auth()->user()?->can('approve-refund-requests');
    $showSettings = (int)auth()->user()?->role === 1 || auth()->user()?->can('manage-billing-settings');
    $salesActive = request()->routeIs('billing.*', 'transxn.index', 'refund-requests.*');
@endphp
@if ($showQuotations || $showInvoices || $showTransactions || $showRefunds || $showSettings)
<li class="nav-item {{ $salesActive ? 'menu-open' : '' }}">
    <a href="#" class="nav-link {{ $salesActive ? 'active' : '' }}" aria-label="Sales menu">
        <i class="fas fa-briefcase fa-fw" aria-hidden="true"></i>
        <p>Sales <i class="right fas fa-angle-left" aria-hidden="true"></i></p>
    </a>
    <ul class="nav nav-treeview">
        @if ($showTransactions)
        <li class="nav-item">
            <a href="{{ route('transxn.index') }}" class="nav-link {{ request()->routeIs('transxn.index') ? 'active' : '' }}">
                <i class="fas fa-money-check-alt fa-fw" aria-hidden="true"></i><p>Transactions</p>
            </a>
        </li>
        @endif
        @if ($showRefunds)
        <li class="nav-item">
            <a href="{{ fr_route('refund-requests.index') }}" class="nav-link {{ request()->routeIs('refund-requests.*') ? 'active' : '' }}">
                <i class="fas fa-undo fa-fw" aria-hidden="true"></i><p>Refunds</p>
            </a>
        </li>
        @endif
        @if ($showQuotations)
        <li class="nav-item">
            <a href="{{ route('billing.quotations.index') }}" class="nav-link {{ request()->routeIs('billing.quotations.*') ? 'active' : '' }}">
                <i class="fas fa-file-alt fa-fw" aria-hidden="true"></i><p>Quotations</p>
            </a>
        </li>
        @endif
        @if ($showInvoices)
        <li class="nav-item">
            <a href="{{ route('billing.invoices.index') }}" class="nav-link {{ request()->routeIs('billing.invoices.*') ? 'active' : '' }}">
                <i class="fas fa-file-invoice-dollar fa-fw" aria-hidden="true"></i><p>Invoices</p>
            </a>
        </li>
        @endif
        @if ((int)auth()->user()?->role === 1 || auth()->user()?->can('manage-billing-settings'))
        <li class="nav-item"><a href="{{ route('billing.settings') }}" class="nav-link {{ request()->routeIs('billing.settings*')?'active':'' }}"><i class="fas fa-cog fa-fw" aria-hidden="true"></i><p>Sales settings</p></a></li>
        @endif
    </ul>
</li>
@endif
