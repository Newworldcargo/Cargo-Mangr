@php
    $user_role = auth()->user()->role;
    $admin  = 1;
    $staff  = 0;
    $auth_branch = 3;
    $auth_client = 4;
    $auth_driver = 5;

    $count = (Modules\Cargo\Entities\ShipmentSetting::getVal('latest_shipment_count') ? Modules\Cargo\Entities\ShipmentSetting::getVal('latest_shipment_count') : 10 );
    if($user_role == $admin || $user_role == $staff){
        $branchIds = app(Modules\Cargo\Services\BranchAccessService::class)->branchIdsFor(auth()->user());
        if($user_role == $admin || auth()->user()->can('manage-shipments')){
            $shipments = Modules\Cargo\Entities\Shipment::whereIn('branch_id', $branchIds)->limit($count)->orderBy('id','desc')->get();
        }
        if($user_role == $admin || auth()->user()->can('manage-drivers')){
            $captains = Modules\Cargo\Entities\Driver::whereIn('branch_id', $branchIds)->withCount(['transaction AS wallet' => function ($query) { $query->select(DB::raw("SUM(value)")); }])->get();
        }
    }elseif($user_role == $auth_branch){
        $branch_id = Modules\Cargo\Entities\Branch::where('user_id',auth()->user()->id)->pluck('id')->first();
        $shipments = Modules\Cargo\Entities\Shipment::where('branch_id', $branch_id)->limit($count)->orderBy('id','desc')->get();
        $captains  = Modules\Cargo\Entities\Driver::where('branch_id', $branch_id)->withCount(['transaction AS wallet' => function ($query) { $query->select(DB::raw("SUM(value)")); }])->get();
    }elseif($user_role == $auth_client){
        $client_id = Modules\Cargo\Entities\Client::where('user_id',auth()->user()->id)->pluck('id')->first();
        $shipments = Modules\Cargo\Entities\Shipment::limit($count)->orderBy('id','desc')->where('client_id',$client_id)->get();
    }elseif($user_role == $auth_driver){
        $driver_id = Modules\Cargo\Entities\Driver::where('user_id',auth()->user()->id)->pluck('id')->first();
    }
@endphp

@if( auth()->user()->can('manage-shipments') )
    <div class="col-md-12">
        <div class="card card-custom shadow-sm">
            <div class="card-header py-3 border-bottom">
                <div class="card-title">
                    <h3 class="card-label font-weight-bold text-dark">{{ __('cargo::view.latest_shipments') }}</h3>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th class="py-2">{{ __('cargo::view.table.code') }}</th>
                                <th class="py-2">{{ __('cargo::view.status') }}</th>
                                <th class="py-2">{{ __('cargo::view.table.type') }}</th>
                                <th class="py-2">{{ __('cargo::view.client') }}</th>
                                @if($user_role != $auth_branch)
                                    <th class="py-2">{{ __('cargo::view.table.branch') }}</th>
                                @endif
                                <th class="py-2">{{ __('cargo::view.shipping_cost') }}</th>
                                <th class="py-2">{{ __('cargo::view.shipping_date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($shipments as $key=>$shipment)
                            <tr>
                                <td class="py-2"><a href="{{route('shipments.show',$shipment->id)}}" class="font-weight-bold text-primary">{{$shipment->code}}</a></td>
                                <td class="py-2">
                                    @php
                                        $status = $shipment->getStatus();
                                        $statusClass = '';
                                        if (strpos(strtolower($status), 'delivered') !== false) {
                                            $statusClass = 'badge-success';
                                        } elseif (strpos(strtolower($status), 'pending') !== false) {
                                            $statusClass = 'badge-warning';
                                        } elseif (strpos(strtolower($status), 'cancelled') !== false) {
                                            $statusClass = 'badge-danger';
                                        } else {
                                            $statusClass = 'badge-info';
                                        }
                                    @endphp
                                    <span class="badge badge-pill {{$statusClass}}">{{$status}}</span>
                                </td>
                                <td class="py-2">{{$shipment->type}}</td>
                                <td class="py-2">
                                    @if(in_array($user_role ,[$admin,$auth_branch]) || auth()->user()->can('manage-customers') )
                                        <a href="{{route('clients.show',$shipment->client_id)}}" class="text-dark">{{$shipment->client->name ?? 'No Client Name'}}</a>
                                    @else
                                        {{$shipment->client->name}}
                                    @endif
                                </td>
                                @if($user_role != $auth_branch)
                                    @if( in_array($user_role ,[$admin]) || auth()->user()->can('manage-branches') )
                                        <td class="py-2"><a href="{{route('branches.show',$shipment->branch_id  ?? 0)}}" class="text-dark">{{ ($shipment->branch->name) ?? 'No Branch Name'}}</a></td>
                                    @else
                                        <td class="py-2">{{ ($shipment->branch->name) ?? 'No Branch Name'}}</td>
                                    @endif
                                @endif
                                <td class="py-2 font-weight-bold">
                                    K{{ number_format(convert_currency($shipment->amount_to_be_collected, 'usd', 'zmw'), 2) }}
                                    <br>
                                    <small class="text-muted">
                                        (${{ number_format($shipment->amount_to_be_collected, 2) }})
                                    </small>
                                </td>
                                {{-- <td class="py-2">{{$shipment->payment_method_id}}</td> --}}
                                <td class="py-2 text-nowrap">
                                    {{ \Carbon\Carbon::parse($shipment->shipping_date)->format('F j, Y') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif
@if($user_role == $auth_driver)
    <div class="col-md-12">
        <div class="card card-custom card-stretch">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">{{ __('cargo::view.current_manifest') }}</h3>
                </div>
            </div>
            <div class="card-body">
                @php
                    $missions  = Modules\Cargo\Entities\Mission::where('captain_id',$driver_id)->whereNotIn('status_id', [\Modules\Cargo\Entities\Mission::DONE_STATUS, \Modules\Cargo\Entities\Mission::CLOSED_STATUS])->where('due_date',Carbon\Carbon::today()->format('Y-m-d'))->get();
                @endphp
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="3%"></th>
                                <th>{{ __('cargo::view.table.code') }}</th>
                                <th>{{ __('cargo::view.table.type') }}</th>
                                <th>{{ __('cargo::view.amount') }}</th>
                                <th>{{ __('cargo::view.table.address') }}</th>
                                <th>{{ __('cargo::view.arrived') }}</th>
                            </tr>
                        </thead>
                        <tbody id="profile_manifest">
                            @foreach($missions as $key=>$mission)
                                <tr style="background-color:tomatom">
                                    <td></td>
                                    <td width="5%">{{$mission->code}}</td>
                                    <td>{{$mission->type}}</td>
                                    @php
                                        $helper = new Modules\Cargo\Http\Helpers\TransactionHelper();
                                        $mission_cost = $helper->calcMissionShipmentsAmount($mission->getRawOriginal('type'),$mission->id);
                                    @endphp
                                    <td>{{format_price($mission_cost)}}</td>
                                    <td>{{$mission->address}}</td>
                                    <td>
                                        <div style="width: 55%;height: 30px;border: 1px solid;border-radius: 3px;"></div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-12">
        <div class="card card-custom card-stretch">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="card-label">{{ __('cargo::view.active_missions') }}</h3>
                </div>
            </div>
            <div class="card-body">
                <table class="table mb-0 aiz-table">
                    <thead>
                        <tr>
                            <th>{{ __('cargo::view.table.code') }}</th>
                            <th>{{ __('cargo::view.status') }}</th>
                            <th>{{ __('cargo::view.table.type') }}</th>
                            <th>{{ __('cargo::view.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>

                        @php
                            $count      = (Modules\Cargo\Entities\ShipmentSetting::getVal('latest_shipment_count') ? Modules\Cargo\Entities\ShipmentSetting::getVal('latest_shipment_count') : 10 );
                            $missions = Modules\Cargo\Entities\Mission::limit($count)->orderBy('id','desc')->where('captain_id',$driver_id)->whereNotIn('status_id', [\Modules\Cargo\Entities\Mission::DONE_STATUS, \Modules\Cargo\Entities\Mission::CLOSED_STATUS])->where('due_date', \Carbon\Carbon::today()->format('Y-m-d'))->get();
                        @endphp
                        @foreach($missions as $key=>$mission)
                            <tr>
                                <td width="5%"><a href="{{route('missions.show',$mission->id)}}">{{$mission->code}}</a></td>
                                <td>{{$mission->getStatus()}}</td>
                                <td>{{$mission->type}}</td>
                                @php
                                    $helper = new Modules\Cargo\Http\Helpers\TransactionHelper();
                                    $mission_cost = $helper->calcMissionShipmentsAmount($mission->getRawOriginal('type'),$mission->id);
                                @endphp
                                <td>{{format_price($mission_cost)}}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
