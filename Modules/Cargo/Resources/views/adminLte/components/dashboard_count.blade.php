@php
    $user_role = auth()->user()->role;
    $admin  = 1;
    $staff  = 0;
    $branch = 3;
    $client = 4;
    $driver = 5;

    if($user_role == $admin || $user_role == $staff){
        $branchIds = app(Modules\Cargo\Services\BranchAccessService::class)->branchIdsFor(auth()->user());
        if($user_role == $admin || auth()->user()->can('manage-branches')){
            $all_branchs = Modules\Cargo\Entities\Branch::where('is_archived', 0)->whereIn('id', $branchIds)->count();
        }
        if($user_role == $admin || auth()->user()->can('manage-staffs')){
            $all_staff = Modules\Cargo\Entities\Staff::whereIn('branch_id', $branchIds)->count();
        }
        if($user_role == $admin || auth()->user()->can('manage-customers')){
            $all_clients = Modules\Cargo\Entities\Client::where('is_archived', 0)->whereIn('branch_id', $branchIds)->count();
        }
        if($user_role == $admin || auth()->user()->can('manage-drivers')){
            $all_captains = Modules\Cargo\Entities\Driver::where('is_archived', 0)->whereIn('branch_id', $branchIds)->count();
        }
    }elseif($user_role == $branch){
        $branch_id = Modules\Cargo\Entities\Branch::where('user_id',auth()->user()->id)->pluck('id')->first();

        $all_clients   = Modules\Cargo\Entities\Client::where('is_archived', 0)->where('branch_id',$branch_id)->count();
        $all_captains  = Modules\Cargo\Entities\Driver::where('is_archived', 0)->where('branch_id',$branch_id)->count();
        $all_staff     = Modules\Cargo\Entities\Staff::where('branch_id',$branch_id)->count();
    }
@endphp


@if($user_role == $admin || auth()->user()->can('manage-branches'))
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{$all_branchs}}</h3>
                <p>{{ __('cargo::view.all_branches') }}</p>
            </div>
            <div class="icon">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            <a href="{{ route('branches.index') }}" class="small-box-footer">{{ __('cargo::view.more_info') }} <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
@endif

@if(in_array($user_role ,[$admin,$branch]) || auth()->user()->can('manage-staffs'))
    <div class= @if($user_role == $admin)"col-xl-3 col-6" @else "col-xl-4 col-6"@endif >
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{$all_staff}}</h3>
                <p>{{ __('cargo::view.all_staffs') }}</p>
            </div>
            <div class="icon">
                <i class="fas fa-users"></i>
            </div>
            <a href="{{ route('staffs.index') }}" class="small-box-footer">{{ __('cargo::view.more_info') }} <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
@endif

@if(in_array($user_role ,[$admin,$branch]) || auth()->user()->can('manage-customers'))
    <div class= @if($user_role == $admin)"col-xl-3 col-6" @else "col-xl-4 col-6"@endif>
        <!-- small box -->
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{$all_clients}}</h3>
                <p>{{ __('cargo::view.all_clients') }}</p>
            </div>
            <div class="icon">
                <i class="fas fa-user-friends"></i>
            </div>
            <a href="{{ route('clients.index') }}" class="small-box-footer">{{ __('cargo::view.more_info') }} <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
@endif

@if(in_array($user_role ,[$admin,$branch]) || auth()->user()->can('manage-drivers'))
    <div class= @if($user_role == $admin)"col-xl-3 col-6" @else "col-xl-4 col-6"@endif>
        <!-- small box -->
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>{{$all_captains}}</h3>
                <p>{{ __('cargo::view.all_drivers') }}</p>
            </div>
            <div class="icon">
                <i class="fas fa-people-carry"></i>
            </div>
            <a href="{{ route('drivers.index') }}" class="small-box-footer">{{ __('cargo::view.more_info') }} <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
@endif

@if($user_role == $admin || auth()->user()->can('manage-missions') || auth()->user()->can('manage-shipments'))

    @php
        $all_consignments    = App\Models\Consignment::count();
        $all_shipments       = Modules\Cargo\Entities\Shipment::count();
        $pending_shipments   = Modules\Cargo\Entities\Shipment::whereIn('status_id', [Modules\Cargo\Entities\Shipment::REQUESTED_STATUS, Modules\Cargo\Entities\Shipment::CAPTAIN_ASSIGNED_STATUS, Modules\Cargo\Entities\Shipment::RECIVED_STATUS, Modules\Cargo\Entities\Shipment::RETURNED_STOCK])->count();
        $delivered_shipments = Modules\Cargo\Entities\Shipment::whereIn('status_id', [Modules\Cargo\Entities\Shipment::DELIVERED_STATUS, Modules\Cargo\Entities\Shipment::SUPPLIED_STATUS, Modules\Cargo\Entities\Shipment::RETURNED_CLIENT_GIVEN])->count();

        $all_missions        = Modules\Cargo\Entities\Mission::count();
        $pending_missions    = Modules\Cargo\Entities\Mission::whereIn('status_id',[ Modules\Cargo\Entities\Mission::REQUESTED_STATUS, Modules\Cargo\Entities\Mission::APPROVED_STATUS, Modules\Cargo\Entities\Mission::RECIVED_STATUS])->count();
        $pickup_missions     = Modules\Cargo\Entities\Mission::where('type', Modules\Cargo\Entities\Mission::PICKUP_TYPE )->count();
        $delivery_missions   = Modules\Cargo\Entities\Mission::where('type', Modules\Cargo\Entities\Mission::DELIVERY_TYPE )->count();
        $transfer_missions   = Modules\Cargo\Entities\Mission::where('type', Modules\Cargo\Entities\Mission::TRANSFER_TYPE )->count();
        $supply_missions     = Modules\Cargo\Entities\Mission::where('type', Modules\Cargo\Entities\Mission::SUPPLY_TYPE )->count();
    @endphp

    <div class="col-lg-3">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{$all_consignments}}</h3>
                <p>All Consignments</p>
            </div>
            <div class="icon">
                <i class="fas fa-box-open"></i>
            </div>
            <a href="{{ route('consignment.index') }}" class="small-box-footer">{{ __('cargo::view.more_info') }} <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{$all_shipments}}</h3>
                <p>{{ __('cargo::view.all_Shipments') }}</p>
            </div>
            <div class="icon">
                <i class="fas fa-box-open"></i>
            </div>
            <a href="{{ route('shipments.index') }}" class="small-box-footer">{{ __('cargo::view.more_info') }} <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

@elseif($user_role == $branch)
    @php
        $all_shipments       = Modules\Cargo\Entities\Shipment::where('branch_id', $branch_id)->count();
        $pending_shipments   = Modules\Cargo\Entities\Shipment::where('branch_id', $branch_id)->whereIn('status_id', [Modules\Cargo\Entities\Shipment::REQUESTED_STATUS, Modules\Cargo\Entities\Shipment::CAPTAIN_ASSIGNED_STATUS, Modules\Cargo\Entities\Shipment::RECIVED_STATUS, Modules\Cargo\Entities\Shipment::RETURNED_STOCK])->count();
        $delivered_shipments = Modules\Cargo\Entities\Shipment::where('branch_id', $branch_id)->whereIn('status_id', [Modules\Cargo\Entities\Shipment::DELIVERED_STATUS, Modules\Cargo\Entities\Shipment::SUPPLIED_STATUS, Modules\Cargo\Entities\Shipment::RETURNED_CLIENT_GIVEN])->count();
    @endphp

    <div class="col-lg-4 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{$all_shipments}}</h3>
                <p>{{ __('cargo::view.all_Shipments') }}</p>
            </div>
            <div class="icon">
                <i class="fas fa-box-open"></i>
            </div>
            <a href="{{ route('shipments.index',['branch_id' => $branch_id ]) }}" class="small-box-footer">{{ __('cargo::view.more_info') }} <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-4 col-6">
        <!-- small box -->
        <div class="small-box bg-danger">
            <div class="inner">
                <h3>{{$pending_shipments}}</h3>
                <p>{{ __('cargo::view.pending_shipments') }}</p>
            </div>
            <div class="icon">
                <i class="fas fa-box-open"></i>
            </div>
            <a href="{{ route('shipments.index', ['status'=>[ Modules\Cargo\Entities\Shipment::REQUESTED_STATUS,Modules\Cargo\Entities\Shipment::CAPTAIN_ASSIGNED_STATUS,Modules\Cargo\Entities\Shipment::RETURNED_STOCK,Modules\Cargo\Entities\Shipment::RECIVED_STATUS ] , 'branch_id' => $branch_id ]) }}" class="small-box-footer">{{ __('cargo::view.more_info') }} <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-4 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{$delivered_shipments}}</h3>
                <p>{{ __('cargo::view.delivered_shipments') }}</p>
            </div>
            <div class="icon">
                <i class="fas fa-box-open"></i>
            </div>
            <a href="{{ route('shipments.index', ['status'=>[ Modules\Cargo\Entities\Shipment::RETURNED_CLIENT_GIVEN,Modules\Cargo\Entities\Shipment::SUPPLIED_STATUS,Modules\Cargo\Entities\Shipment::DELIVERED_STATUS ] , 'branch_id' => $branch_id ]) }}" class="small-box-footer">{{ __('cargo::view.more_info') }} <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    @elseif($user_role == $client)
        @include('cargo::adminLte.components.client-ui.top-dash')
    @elseif($user_role == $driver)

    @php
        $driver_id    = Modules\Cargo\Entities\Driver::where('user_id',auth()->user()->id)->pluck('id')->first();
        $transactions = Modules\Cargo\Entities\Transaction::where('captain_id', $driver_id)->orderBy('created_at','desc')->sum('value');
        $transactions = abs($transactions); // Converting the transactions from negative to positive
    @endphp

    <div class="col-lg-12">
        <div class="card card-custom bgi-no-repeat card-stretch gutter-b">
            <div class="card-body">
                <a href="{{ route('transactions.index') }}" class="mb-0 font-weight-bold text-light-75 text-hover-primary font-size-h5">{{ __('cargo::view.your_wallet') }}
                    <div class="mt-0 mb-5 font-weight-bold font-size-h4 text-success mt-9">{{format_price($transactions)}}</div>
                </a>
                <p class="m-0 text-dark-75 font-weight-bolder font-size-h5">{{ __('cargo::view.driver_wallet_dashboard') }}.</p>
            </div>
        </div>
    </div>
@endif
