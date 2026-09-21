@php
$user_role = auth()->user()->role;
$admin = 1;
$staff = 0;
$branch = 3;
$client = 4;
$driver = 5;
@endphp

@if ($user_role == $admin || auth()->user()->can('view-online-bookings'))
<li class="nav-item">
    <a href="{{ fr_route('shipments.online-bookings') }}"
        class="nav-link {{ areActiveRoutes(['shipments.online-bookings']) }}">
        <i class="fas fa-globe fa-fw"></i>
        <p>Online Bookings</p>
    </a>
</li>
@endif


@if (auth()->user()->can('manage-shipments') || in_array($user_role, [$admin, $client, $branch]))
<li style="color: #fff"
    class="nav-item {{ areActiveRoutes(['shipments','shipments.create','shipments.import','shipments.add.api','shipments.barcode.scanner','shipment-calc','shipments.index'],'menu-is-opening menu-open active') }} @foreach (Modules\Cargo\Entities\Shipment::status_info() as $item) {{ areActiveRoutes([$item['route_name']], 'menu-is-opening menu-open active') }} @endforeach ">
    {{-- <a href="#"
        class="nav-link {{ areActiveRoutes(['shipments','shipments.create','shipments.import','shipments.add.api','shipments.barcode.scanner','shipment-calc','shipments.index'],'menu-is-opening menu-open active') }} @foreach (Modules\Cargo\Entities\Shipment::status_info() as $item) {{ areActiveRoutes([$item['route_name']], 'menu-is-opening menu-open active') }} @endforeach  ">
        <i style="color: #fff" class="fas fa-box-open"></i>
        &nbsp;
        <p style="color: #fff">
            {{ __('cargo::view.shipments') }}
            <i class="right fas fa-angle-left"></i>
        </p>
    </a> --}}

    <ul class="nav nav-treeview">
        <!-- Shipment Menu -->
        @if (auth()->user()->can('manage-shipments') || in_array($user_role, [$admin, $client, $branch]))

        <!-- create shipment -->
        @if (auth()->user()->can('create-shipments') || in_array($user_role, [$admin, $client, $branch]))
        {{-- <li class="nav-item">
            <a href="{{ fr_route('shipments.create') }}" class="nav-link {{ areActiveRoutes(['shipments.create']) }}">
                <i style="color: #fff" class="fas fa-plus fa-fw"></i>
                &nbsp;
                <p style="color: #fff">
                    @if ($user_role == 4)
                    Book new shipment
                    @else
                    {{ __('cargo::view.create_new_shipment') }}
                    @endif
                </p>
            </a>
        </li> --}}
        @endif



        {{-- @if (in_array($user_role, [$admin, $branch]) || auth()->user()->can('import-shipments'))
        @if (in_array($user_role, [$admin, $client, $branch]) || auth()->user()->can('import-shipments'))
        <!-- import shipment -->
        <li class="nav-item">
            <a href="{{ fr_route('shipments.import') }}" class="nav-link {{ areActiveRoutes(['shipments.import']) }}">
                <i style="color: #fff" class="fas fa-file-import fa-fw"></i>
                <p style="color: #fff">{{ __('cargo::view.import_shipments') }}</p>
            </a>
        </li>
        @endif --}}

        @if ($user_role == $client)
        <!-- shipment api -->
        {{-- <li class="nav-item">
            <a href="{{ fr_route('shipments.add.api') }}" class="nav-link {{ areActiveRoutes(['shipments.add.api']) }}">
                <i class="fas fa-plus fa-fw"></i>
                <p>{{ __('cargo::view.shipment_apis') }}</p>
            </a>
        </li> --}}
        @endif

        <!-- shipment barcode scanner -->
        {{-- @if (auth()->user()->can('shipments-barcode-scanner') || $user_role == $admin)
        <li class="nav-item">
            <a href="{{ fr_route('shipments.barcode.scanner') }}"
                class="nav-link {{ areActiveRoutes(['shipments.barcode.scanner']) }}">
                <i style="color: #fff" class="fas fa-qrcode fa-fw"></i>
                <p style="color: #fff">{{ __('cargo::view.barcode_scanner') }}</p>
            </a>
        </li>
        @endif --}}

        <!-- shipment calc -->
        {{-- @if (Modules\Cargo\Entities\ShipmentSetting::getVal('is_shipping_calc_required') == 1)
        <li class="nav-item">
            <a href="{{ fr_route('shipments.calculator') }}"
                class="nav-link {{ areActiveRoutes(['shipments.calculator']) }}">
                <i style="color: #fff" class="fas fa-calculator fa-fw"></i>
                <p style="color: #fff">{{ __('cargo::view.shipping_calculator') }}</p>
            </a>
        </li>
        @endif --}}

        <!-- all shipments -->
        {{-- <li class="nav-item">
            <a href="{{ fr_route('shipments.index') }}" class="nav-link {{ areActiveRoutes(['shipments.index']) }}">
                <i style="color: #fff" class="far fa-circle nav-icon"></i>
                <p style="color: #fff">{{ __('cargo::view.all_Shipments') }}</p>
            </a>
        </li> --}}


        {{-- @foreach (Modules\Cargo\Entities\Shipment::status_info() as $item)
        @if (in_array($user_role, [$admin, $client, $branch]) ||
        auth()->user()->hasAnyDirectPermission($item['permissions']))
        @if ($item['status'] == Modules\Cargo\Entities\Shipment::SAVED_STATUS)
        <li class="nav-item">
            <a href="{{ route($item['route_name'], ['status' => $item['status'], 'type' => Modules\Cargo\Entities\Shipment::PICKUP]) }}"
                class="nav-link {{ active_route($item['route_name'], ['status' => $item['status'],'type' => Modules\Cargo\Entities\Shipment::PICKUP]) }}">
                <i style="color: #fff" class="far fa-circle nav-icon"></i>
                <p style="color: #fff">{{ __('cargo::view.saved_pickup') }}</p>
            </a>
        </li>

        <li class="nav-item">
            <a href="{{ route($item['route_name'], ['status' => $item['status'],'type' => Modules\Cargo\Entities\Shipment::DROPOFF]) }}"
                class="nav-link {{ active_route($item['route_name'], ['status' => $item['status'],'type' => Modules\Cargo\Entities\Shipment::DROPOFF]) }}">
                <i style="color: #fff" class="far fa-circle nav-icon"></i>
                <p style="color: #fff">{{ __('cargo::view.saved_dropoff') }}</p>
            </a>
        </li>
        @elseif($item['status'] == Modules\Cargo\Entities\Shipment::REQUESTED_STATUS)
        <li class="nav-item">
            <a href="{{ route($item['route_name'], ['status' => $item['status'], 'type' => Modules\Cargo\Entities\Shipment::PICKUP]) }}"
                class="nav-link {{ active_route($item['route_name'], ['status' => $item['status'],'type' => Modules\Cargo\Entities\Shipment::PICKUP]) }}">
                <i style="color: #fff" class="far fa-circle nav-icon"></i>
                <p style="color: #fff">{{ __('cargo::view.requested_pickup') }}</p>
            </a>
        </li>
        @else
        <li class="nav-item">
            <a href="{{ route($item['route_name'], ['status' => $item['status']]) }}"
                class="nav-link {{ active_route($item['route_name'], ['status' => $item['status']]) }}">
                <i style="color: #fff" class="far fa-circle nav-icon"></i>
                <p style="color: #fff">{{ $item['text'] }}</p>
            </a>
        </li>
        @endif
        @endif
        @endforeach --}}

        @endif
    </ul>
</li>
@endif


@if (auth()->user()->can('manage-shipments') || in_array($user_role, [$admin, $client, $branch]))
@can('manage-users')
{{-- {{ areActiveRoutes(['users', ['class_name' => 'show']]) }} --}}
{{-- <li class="nav-item {{ areActiveRoutes(['receivers.index', 'receivers.create'], 'menu-is-opening menu-open active') }}">
    <a href="#"
        class="nav-link {{ areActiveRoutes(['receivers.index', 'receivers.create'], 'menu-is-opening menu-open active') }}">
        <i class="fas fa-user"></i>
        <p>
            {{ __('cargo::view.receiver') }}
            <i class="right fas fa-angle-left"></i>
        </p>
    </a>

    <ul class="nav nav-treeview">

        <!-- Receiver list -->
        @if (auth()->user()->can('view-users') || $user_role == $admin)
        <li class="nav-item">
            <a href="{{ fr_route('receivers.index') }}" class="nav-link {{ areActiveRoutes(['receivers.index']) }}">
                <i class="fas fa-list fa-fw"></i>
                <p>{{ __('cargo::view.receiver_list') }}</p>
            </a>
        </li>
        @endif

        <!-- Create new Receiver -->
        @if (auth()->user()->can('create-users') || $user_role == $admin)
        <li class="nav-item">
            <a href="{{ fr_route('receivers.create') }}" class="nav-link {{ areActiveRoutes(['receivers.create']) }}">
                <i class="fas fa-plus fa-fw"></i>
                <p>{{ __('cargo::view.create_new_receiver') }}</p>
            </a>
        </li>
        @endif

    </ul>
</li> --}}
@endcan
@endif
{{--
@if (auth()->user()->can('manage-missions') ||
$user_role == $admin ||
$user_role == $branch ||
$user_role == $driver)
<li
    class="nav-item {{ active_uri('missions', ['class_name' => 'show']) }} {{ areActiveRoutes(['missions', 'missions.index'], 'menu-is-opening menu-open active') }}  @foreach (Modules\Cargo\Entities\Mission::status_info() as $item) {{ areActiveRoutes([$item['route_name']], 'menu-is-opening menu-open active') }} @endforeach">
    <a href="#"
        class="nav-link {{ active_uri('missions') }} {{ areActiveRoutes(['missions', 'missions.index'], 'menu-is-opening menu-open active') }}  @foreach (Modules\Cargo\Entities\Mission::status_info() as $item) {{ areActiveRoutes([$item['route_name']], 'menu-is-opening menu-open active') }} @endforeach">
        <i class="fas fa-shipping-fast"></i>
        <p>
            {{ __('cargo::view.missions') }}
            <i class="right fas fa-angle-left"></i>
        </p>
    </a>

    <ul class="nav nav-treeview">
        <!-- Mission Menu -->
        @if (auth()->user()->can('manage-missions') ||
        $user_role == $admin ||
        $user_role == $branch ||
        $user_role == $driver)

        <li class="nav-item">
            <a href="{{ fr_route('missions.index') }}" class="nav-link {{ areActiveRoutes(['missions.index']) }}">
                <i class="far fa-circle nav-icon"></i>
                <p>{{ __('cargo::view.all_missions') }}</p>
            </a>
        </li>

        @foreach (Modules\Cargo\Entities\Mission::status_info() as $item)
        @if (in_array($user_role, $item['user_role']) ||
        auth()->user()->hasAnyDirectPermission($item['permissions']))
        <li class="nav-item">
            <a href="{{ route($item['route_name'], ['status' => $item['status']]) }}"
                class="nav-link {{ active_route($item['route_name'], ['status' => $item['status']]) }}">
                <i class="far fa-circle nav-icon"></i>
                <p>{{ $item['text'] }}</p>
            </a>
        </li>
        @endif
        @endforeach

        @endif
    </ul>
</li>
@endif --}}


{{-- @if (auth()->user()->can('manage-manifests') || in_array($user_role, [$admin, $driver, $branch]))
<li class="nav-item   {{ areActiveRoutes(['missions.manifests'], 'menu-is-opening menu-open active') }}">
    <a href="{{ fr_route('missions.manifests') }}" class="nav-link {{ areActiveRoutes(['missions.manifests']) }}">
        <i class="fas fa-truck-moving fa-fw"></i>
        <p>{{ __('cargo::view.manifest') }}</p>
    </a>
</li>
@endif --}}


@if (
    auth()->user()->can('access-finance-transactions')
    || auth()->user()->can('approve-refund-requests')
)
<li
    class="nav-item {{ areActiveRoutes(['transxn.index', 'refund-requests.index', 'refund-requests.show'], 'menu-is-opening menu-open active') }}">
    <a href="#"
        class="nav-link {{ areActiveRoutes(['transxn.index', 'refund-requests.index', 'refund-requests.show'], 'menu-is-opening menu-open active') }}">
        <i style="color: #fff" class="fas fa-money-check-alt"></i>
        &nbsp;
        <p style="color: #fff">
            {{ __('cargo::view.transactions') }}
            <i class="right fas fa-angle-left"></i>
        </p>
    </a>

    <ul class="nav nav-treeview">
        @can('access-finance-transactions')
        <li class="nav-item">
            <a href="{{ route('transxn.index') }}"
                class="nav-link {{ areActiveRoutes(['transxn.index']) }}">
                <i style="color: #fff" class="fas fa-list fa-fw"></i>
                &nbsp;
                <p style="color: #fff">Transactions</p>
            </a>
        </li>
        @endcan

        @if (auth()->user()->can('approve-refund-requests'))
        <li class="nav-item">
            <a href="{{ fr_route('refund-requests.index') }}"
                class="nav-link {{ areActiveRoutes(['refund-requests.index', 'refund-requests.show']) }}">
                <i style="color: #fff" class="fas fa-undo fa-fw"></i>
                &nbsp;
                <p style="color: #fff">Refunds</p>
            </a>
        </li>
        @endif
    </ul>
</li>
@endif


{{-- @if (auth()->user()->can('manage-transactions') || in_array($user_role, [$admin, $branch, $driver, $client]))
<li
    class="nav-item {{ active_uri('transactions', ['class_name' => 'show']) }}  {{ areActiveRoutes(['transactions', 'transactions.create', 'transactions.index'],'menu-is-opening menu-open active') }}">
    <a href="#"
        class="nav-link {{ active_uri('transactions') }}  {{ areActiveRoutes(['transactions', 'transactions.create', 'transactions.index'],'menu-is-opening menu-open active') }}">
        <i style="color: #fff" class="fas fa-money-check-alt"></i>
        &nbsp;
        <p style="color: #fff">
            {{ __('cargo::view.transactions') }}
            <i class="right fas fa-angle-left"></i>
        </p>
    </a>

    <ul class="nav nav-treeview">

        <!-- Transaction list -->
        @if (auth()->user()->can('manage-transactions') || in_array($user_role, [$admin, $branch, $driver, $client]))
        <li class="nav-item">
            <a href="{{ fr_route('transactions.index') }}"
                class="nav-link {{ areActiveRoutes(['transactions.index']) }}">
                <i style="color: #fff" class="fas fa-list fa-fw"></i>
                &nbsp;
                <p style="color: #fff">{{ __('cargo::view.all_transactions') }}</p>
            </a>
        </li>
        @endif

        <!-- Create new transaction -->
        @if (auth()->user()->can('create-transactions') || in_array($user_role, [$admin, $branch]))
        <li class="nav-item">
            <a href="{{ fr_route('transactions.create') }}"
                class="nav-link {{ areActiveRoutes(['transactions.create']) }}">
                <i style="color: #fff" class="fas fa-plus fa-fw"></i>
                &nbsp;
                <p style="color: #fff">{{ __('cargo::view.create_new_transaction') }}</p>
            </a>
        </li>
        @endif

    </ul>
</li>
@endif --}}



@if (app(\Modules\Cargo\Services\ShipmentDispatchService::class)->canView(auth()->user()))
<li class="nav-item">
    <a href="{{ fr_route('shipments.dispatch.index') }}" class="nav-link {{ areActiveRoutes(['shipments.dispatch.index']) }}">
        <i class="fas fa-truck-loading"></i>
        <p>Dispatch</p>
    </a>
</li>
@endif

@if (auth()->user()->can('manage-branches') ||
auth()->user()->can('manage-customers') ||
auth()->user()->can('manage-drivers') ||
$user_role == $admin ||
$user_role == $branch)
<li
    class="nav-item {{ active_uri('shipment-team', ['class_name' => 'show']) }} {{ areActiveRoutes(['branches','branches.create','branches.index','branches','clients.index','clients.create','clients','drivers.index','drivers.create','drivers','shipment-team'],'menu-is-opening menu-open active') }}">
    <a href="#"
        class="nav-link {{ areActiveRoutes(['branches','branches.create','branches.index','branches','clients.index','clients.create','clients','drivers.index','drivers.create','drivers','shipment-team'],'menu-is-opening menu-open active') }}">
        <i class="fas fa-users"></i>
        <p>
            {{ __('cargo::view.shipment_team') }}
            <i class="right fas fa-angle-left"></i>
        </p>
    </a>

    <ul class="nav nav-treeview">

        <!-- Branch Menu -->
        @if (auth()->user()->can('manage-branches') || $user_role == $admin)
        <li
            class="nav-item {{ active_uri('branches', ['class_name' => 'show']) }}     {{ areActiveRoutes(['branches.create', 'branches.index', 'branches'], 'menu-is-opening menu-open active') }}">
            <a href="#"
                class="nav-link {{ areActiveRoutes(['branches.create', 'branches.index', 'branches'], 'menu-is-opening menu-open active') }}">
                <i class="fas fa-map-marked-alt"></i>
                <p>
                    {{ __('cargo::view.branches') }}
                    <i class="right fas fa-angle-left"></i>
                </p>
            </a>

            <ul class="nav nav-treeview">

                <!-- Branch list -->
                @if (auth()->user()->can('view-branches') || $user_role == $admin)
                <li class="nav-item">
                    <a href="{{ fr_route('branches.index') }}"
                        class="nav-link {{ areActiveRoutes(['branches.index']) }}">
                        <i class="fas fa-list fa-fw"></i>
                        <p>{{ __('cargo::view.branch_list') }}</p>
                    </a>
                </li>
                @endif

                <!-- Create new branch -->
                @if (auth()->user()->can('create-branches') || $user_role == $admin)
                <li class="nav-item">
                    <a href="{{ fr_route('branches.create') }}"
                        class="nav-link {{ areActiveRoutes(['branches.create']) }}">
                        <i class="fas fa-plus fa-fw"></i>
                        <p>{{ __('cargo::view.create_new_branch') }}</p>
                    </a>
                </li>
                @endif

            </ul>
        </li>
        @endif

        <!-- Customer Menu -->
        @if (auth()->user()->can('manage-customers') ||
        $user_role == $admin ||
        $user_role == $branch)

        <li
            class="nav-item {{ active_uri('clients', ['class_name' => 'show']) }} {{ areActiveRoutes(['clients.index', 'clients.create', 'clients'], 'menu-is-opening menu-open active') }}">
            <a href="#"
                class="nav-link {{ areActiveRoutes(['clients.index', 'clients.create', 'clients'], 'menu-is-opening menu-open active') }}">
                <i class="fas fa-user-friends"></i>
                <p>
                    {{ __('cargo::view.clients') }}
                    <i class="right fas fa-angle-left"></i>
                </p>
            </a>

            <ul class="nav nav-treeview">

                <!-- Customers list -->
                @if (auth()->user()->can('view-customers') ||
                $user_role == $admin ||
                $user_role == $branch)
                <li class="nav-item">
                    <a href="{{ fr_route('clients.index') }}" class="nav-link {{ areActiveRoutes(['clients.index']) }}">
                        <i class="fas fa-list fa-fw"></i>
                        <p>{{ __('cargo::view.client_list') }}</p>
                    </a>
                </li>
                @endif

                <!-- Create new customer -->
                @if (auth()->user()->can('create-customers') ||
                $user_role == $admin ||
                $user_role == $branch)
                <li class="nav-item">
                    <a href="{{ fr_route('clients.create') }}"
                        class="nav-link {{ areActiveRoutes(['clients.create']) }}">
                        <i class="fas fa-plus fa-fw"></i>
                        <p>{{ __('cargo::view.create_new_client') }}</p>
                    </a>
                </li>
                @endif

            </ul>
        </li>
        @endif

        <!-- Driver Menu -->
        @if (auth()->user()->can('manage-drivers') ||
        $user_role == $admin ||
        $user_role == $branch)

        <li
            class="nav-item {{ active_uri('drivers', ['class_name' => 'show']) }}  {{ areActiveRoutes(['drivers.index', 'drivers.create', 'drivers'], 'menu-is-opening menu-open active') }}">
            <a href="#"
                class="nav-link {{ areActiveRoutes(['drivers.index', 'drivers.create', 'drivers'], 'menu-is-opening menu-open active') }}">
                <i class="fas fa-people-carry"></i>
                <p>
                    {{ __('cargo::view.drivers') }}
                    <i class="right fas fa-angle-left"></i>
                </p>
            </a>
            <ul class="nav nav-treeview">

                <!-- Driver list -->
                @if (auth()->user()->can('view-drivers') ||
                $user_role == $admin ||
                $user_role == $branch)
                <li class="nav-item">
                    <a href="{{ fr_route('drivers.index') }}" class="nav-link {{ areActiveRoutes(['drivers.index']) }}">
                        <i class="fas fa-list fa-fw"></i>
                        <p>{{ __('cargo::view.driver_list') }}</p>
                    </a>
                </li>
                @endif

                <!-- Create new driver -->
                @if (auth()->user()->can('create-drivers') ||
                $user_role == $admin ||
                $user_role == $branch)
                <li class="nav-item">
                    <a href="{{ fr_route('drivers.create') }}"
                        class="nav-link {{ areActiveRoutes(['drivers.create']) }}">
                        <i class="fas fa-plus fa-fw"></i>
                        <p>{{ __('cargo::view.create_new_driver') }}</p>
                    </a>
                </li>
                @endif

            </ul>
        </li>
        @endif

    </ul>
</li>
@endif


{{-- <li
    class="nav-item {{ areActiveRoutes(['shipments.report','missions.report','clients.report','drivers.report','branches.report','transactions.report'],'menu-is-opening menu-open active') }}">
    <a href="#"
        class="nav-link {{ areActiveRoutes(['shipments.report','missions.report','clients.report','drivers.report','branches.report','transactions.report'],'menu-is-opening menu-open active') }}">
        <i class="fas fa-book nav-icon" style="color: #ffffff;"></i>
        <p style="color: #ffffff;">
            {{ __('view.reports') }}
            <i class="right fas fa-angle-left"></i>
        </p>
    </a>
    <ul class="nav nav-treeview" style="padding-left: 10px; color: #ffffff;">
        @if (app('hook')->get('aside_menu_reports'))
        @foreach (app('hook')->get('aside_menu_reports') as $componentView)
        {!! $componentView !!}
        @endforeach
        @endif
    </ul>
</li> --}}

@if ($user_role == $client)
<li class="nav-item">
    <a href="{{ fr_route('dashboard') }}" class="nav-link">
        <i class="fas fa-tachometer-alt fa-fw text-white"></i>
        <p class="text-white">&nbsp;Dashboard</p>
    </a>
</li>

<!-- Aircraft -->
<li class="nav-item">
    <a href="{{ fr_route('aircraft.index') }}" class="nav-link">
        <i class="fas fa-plane fa-fw text-white"></i>
        <p class="text-white">&nbsp;Aircraft</p>
    </a>
</li>

<!-- Shipment -->
<li class="nav-item">
    <a href="{{ route('shipments.overview') }}" class="nav-link">
        <i class="fas fa-shipping-fast fa-fw text-white"></i>
        <p class="text-white">&nbsp;Shipment</p>
    </a>
</li>
<!-- shipment api -->
{{-- <li class="nav-item">
    <a href="{{ fr_route('shipments.calculator') }}" class="nav-link">
        <i class="fas fa-calculator fa-fw text-white"></i>
        <p class="text-white">&nbsp;Shipment Calculator</p>
    </a>
</li> --}}
<li class="nav-item">
    <a href="{{ fr_route('support') }}" class="nav-link">
        <i class="fas fa-headset fa-fw text-white"></i>
        <p class="text-white">&nbsp;Support</p>
    </a>
</li>

@endif
