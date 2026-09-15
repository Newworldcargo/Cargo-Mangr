@csrf


@php
    $user_role = auth()->user()->role;
    $admin  = 1;
    $auth_staff  = 0;
    $auth_branch = 3;
    $auth_client = 4;

    $userBranch = Modules\Cargo\Entities\Branch::where('user_id',auth()->user()->id)->first();
    $userClient = Modules\Cargo\Entities\Client::where('user_id',auth()->user()->id)->first();
    $userReciver = Modules\Cargo\Entities\Receiver::where('user_id',auth()->user()->id)->first();

    $branches = Modules\Cargo\Entities\Branch::where('is_archived', 0)->get();
    $clients = Modules\Cargo\Entities\Client::where('is_archived', 0)->get();
    $Recivers  =  Modules\Cargo\Entities\Receiver::where('is_archived', 0)->get();
    if($user_role == $auth_branch){
        $branches = Modules\Cargo\Entities\Branch::where('id', $userBranch->id)->get();
        $clients  = Modules\Cargo\Entities\Client::where('branch_id', $userBranch->id )->get();
        $Recivers  = Modules\Cargo\Entities\Receiver::where('branch_id', $userBranch->id )->get();
    }elseif(auth()->user()->can('create-shipments') && $user_role == $auth_staff){
        $branchIds = app(Modules\Cargo\Services\BranchAccessService::class)->branchIdsFor(auth()->user());
        $branches = Modules\Cargo\Entities\Branch::whereIn('id', $branchIds)->get();
        $clients  = Modules\Cargo\Entities\Client::whereIn('branch_id', $branchIds)->get();
        $Recivers  = Modules\Cargo\Entities\Receiver::whereIn('branch_id', $branchIds)->get();

    }

    $countries = Modules\Cargo\Entities\Country::where('covered',1)->get();
    $packages = Modules\Cargo\Entities\Package::all();
    $deliveryTimes = Modules\Cargo\Entities\DeliveryTime::all();

    // is_def_mile_or_fees if result 1 for mile if result 2 for fees
    $is_def_mile_or_fees = Modules\Cargo\Entities\ShipmentSetting::getVal('is_def_mile_or_fees');
    if(!$is_def_mile_or_fees){
        $is_def_mile_or_fees = 0;
    }

    $googleSettings = resolve(\app\Models\GoogleSettings::class)->toArray();
    $googleMap = json_decode($googleSettings['google_map'], true);
    $google_map_key = '';
    if($googleMap){
        $google_map_key = $googleMap['google_map_key'];
    }

    $paymentSettings = resolve(\Modules\Payments\Entities\PaymentSetting::class)->toArray();
@endphp
<div class="card-header">
    <h5 class="mb-0 h6">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle" viewBox="0 0 16 16">
            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
            <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
        </svg>
        {{ __('cargo::view.shipment_info') }}
    </h5>
</div>

<div class="row">
    <div class="col-lg-12">

        @if($typeForm == 'edit' && $model->consignment_id)
            <div class="alert alert-info d-flex align-items-start mb-6">
                <i class="fas fa-info-circle mt-1 me-3"></i>
                <div>
                    <strong>Imported shipment details</strong>
                    <div>Edit this parcel independently. Payment status is intentionally managed by the payment and receipt workflow.</div>
                </div>
            </div>
            <div class="row mb-5">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="col-form-label fw-bold fs-6 required">HAWB / shipment code</label>
                        <input type="text" name="Shipment[code]" class="form-control @error('Shipment.code') is-invalid @enderror"
                            value="{{ old('Shipment.code', $model->code) }}" maxlength="255" required>
                        @error('Shipment.code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="col-form-label fw-bold fs-6">Salesperson</label>
                        <input type="text" name="Shipment[salesman]" class="form-control"
                            value="{{ old('Shipment.salesman', $model->salesman) }}" maxlength="255">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="col-form-label fw-bold fs-6">Destination branch / location</label>
                        <input type="text" name="Shipment[next_destination]" class="form-control"
                            value="{{ old('Shipment.next_destination', $model->next_destination) }}" maxlength="255">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="col-form-label fw-bold fs-6">Destination port</label>
                        <input type="text" name="Shipment[dest_port]" class="form-control"
                            value="{{ old('Shipment.dest_port', $model->dest_port) }}" maxlength="255">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="col-form-label fw-bold fs-6">Volume (m³)</label>
                        <input type="number" min="0" step="0.001" name="Shipment[volume]" class="form-control"
                            value="{{ old('Shipment.volume', $model->volume) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="col-form-label fw-bold fs-6">Consignment</label>
                        <input type="text" class="form-control" value="{{ optional($model->consignment)->consignment_code ?: '#'.$model->consignment_id }}" disabled>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-form-label fw-bold fs-6">Sender secondary phone</label>
                        <input type="tel" name="Shipment[client_phone_2]" class="form-control"
                            value="{{ old('Shipment.client_phone_2', $model->client_phone_2) }}" maxlength="50">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-form-label fw-bold fs-6">Receiver secondary phone</label>
                        <input type="tel" name="Shipment[reciver_phone_2]" class="form-control"
                            value="{{ old('Shipment.reciver_phone_2', $model->reciver_phone_2) }}" maxlength="50">
                    </div>
                </div>
            </div>
            <hr>
        @endif

        <div class="form-group row">
            <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.shipment_type') }}</label>
            <div class="col-9 col-form-label">
                {{-- <div class="form-group">
                    <label for="consignmentCode">Consignment Code/ID</label>
                    <input type="text" id="consignmentCode" name="consignment_id" class="form-control" placeholder="Search by consignment code">
                    <div id="consignmentResults" class="dropdown-menu w-100" style="display: none;"></div>
                </div> --}}
                {{-- <div class="form-group">
                    <label for="consignmentCode">Consignment Code</label>
              <input type="text" id="consignmentCode" class="form-control" placeholder="Search by consignment code" autocomplete="off">
                    <input type="hidden" id="consignmentId" name="consignment_id">
                    <div id="consignmentResults" class="dropdown-menu w-100" style="display: none;"></div>
                </div> --}}
                <div class="radio-inline">
                    <label class="radio radio-success">
                        <input checked @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_shipment_type')=='2') checked @endif
                               type="radio" name="Shipment[type]" value="2"
                               {{ old('Shipment.type') == 1 ? 'checked' : '' }}
                               @if($typeForm == 'edit') {{ $model->type == 1 ? 'checked' : '' }} @endif
                        />
                        <span></span>
                        {{ __('cargo::view.drop_off_For_delivery_package_from_branch_directly') }}
                    </label>
                </div>
            </div>
            <script>
                document.getElementById('consignmentCode').addEventListener('keyup', function () {
                    let query = this.value;
                    let resultsDropdown = document.getElementById('consignmentResults');

                    if (query.length < 2) {
                        resultsDropdown.style.display = 'none';
                        return;
                    }

                    fetch(`/api/search-consignments?query=${query}`)
                        .then(response => response.json())
                        .then(data => {
                            resultsDropdown.innerHTML = '';
                            if (data.length > 0) {
                                data.forEach(consignment => {
                                    let option = document.createElement('a');
                                    option.classList.add('dropdown-item');
                                    option.textContent = `${consignment.code} - ${consignment.name}`;
                                    option.href = '#';
                                    option.style.cursor = 'pointer';
                                    option.onclick = function (event) {
                                        event.preventDefault();
                                        document.getElementById('consignmentCode').value = consignment.code; // Display code
                                        document.getElementById('consignmentId').value = consignment.id; // Store ID in hidden input
                                        resultsDropdown.style.display = 'none';
                                    };
                                    resultsDropdown.appendChild(option);
                                });
                                resultsDropdown.style.display = 'block';
                            } else {
                                resultsDropdown.style.display = 'none';
                            }
                        })
                        .catch(error => console.error('Error:', error));
                });
            </script>
        </div>
        <hr>
        <div class="row">
            @if(Modules\Cargo\Entities\ShipmentSetting::getVal('is_date_required') == '1' || Modules\Cargo\Entities\ShipmentSetting::getVal('is_date_required') == null)
            <div class="col-md-4">
            @else
            <div class="col-md-6">
            @endif
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.table.branch') }}</label>
                    <select
                        class="form-control select-branch  @error('Shipment.branch_id') is-invalid @enderror"
                        data-control="select2"
                        data-placeholder="{{ __('cargo::view.table.choose_branch') }}"
                        data-allow-clear="true"
                        name="Shipment[branch_id]"
                    >
                        <option></option>
                        @foreach($branches as $branch)
                            @if($user_role == $auth_branch)
                                <option
                                    @if( $userBranch->id ==$branch->id) selected @endif value="{{$branch->id}}"

                                    {{ old('Shipment.branch_id') == $branch->id ? 'selected' : '' }}
                                    @if($typeForm == 'edit')
                                        {{ $model->branch_id == $branch->id ? 'selected' : '' }}
                                    @endif
                                >{{$branch->name}}
                                </option>
                            @else
                                <option
                                    @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_branch')==$branch->id) selected @endif
                                    value="{{$branch->id}}"
                                    {{ old('Shipment.branch_id') == $branch->id ? 'selected' : '' }}
                                    @if($typeForm == 'edit')
                                        {{ $model->branch_id == $branch->id ? 'selected' : '' }}
                                    @endif
                                    >{{$branch->name}}

                                </option>
                            @endif
                        @endforeach

                    </select>
                    @error('Shipment.branch_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>

            @if(Modules\Cargo\Entities\ShipmentSetting::getVal('is_date_required') == '1' || Modules\Cargo\Entities\ShipmentSetting::getVal('is_date_required') == null)
            <div class="col-md-4">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6">{{ __('cargo::view.shipping_date') }}</label>
                    <div class="input-group date">
                        @php
                            $defult_shipping_date = Modules\Cargo\Entities\ShipmentSetting::getVal('def_shipping_date');
                            if($defult_shipping_date == null )
                            {
                                $shipping_data = \Carbon\Carbon::now()->addDays(0);
                            }else{
                                $shipping_data = \Carbon\Carbon::now()->addDays($defult_shipping_date);
                            }

                        @endphp
                        <input type="text" placeholder="{{ __('cargo::view.shipping_date') }}" value="{{ old('Shipment.shipping_date', isset($model) ? $model->shipping_date : $shipping_data->toDateString() ) }}" name="Shipment[shipping_date]" autocomplete="off" class="form-control" id="kt_datepicker_3" />
                        <div class="input-group-append">
                            <span class="input-group-text" style="padding: 10px !important;">
                                <i class="la la-calendar"></i>
                            </span>
                        </div>
                    </div>

                </div>
            </div>
            @endif

            @if(Modules\Cargo\Entities\ShipmentSetting::getVal('is_date_required') == '1' || Modules\Cargo\Entities\ShipmentSetting::getVal('is_date_required') == null)
            <div class="col-md-4">
            @else
            <div class="col-md-6">
            @endif
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6">{{ __('cargo::view.collection_time') }}</label>
                    <div class="input-group date">
                        <input type="text" placeholder="{{ __('cargo::view.collection_time') }}" value="{{ old('Shipment.collection_time', isset($model) ? $model->collection_time : '' ) }}"  name="Shipment[collection_time]" autocomplete="off" class="form-control" id="kt_timepicker_3" />
                        <div class="input-group-append">
                            <span class="input-group-text" style="padding: 10px !important;">
                                <i class="la la-clock-o"></i>
                            </span>
                        </div>
                    </div>

                </div>
            </div>

        </div>
        <div class="row">

            <div class="col-md-6">
                <div class="form-group client-select">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.client_sender') }}</label>
                    @if($user_role == $auth_client)
                        <input type="text" placeholder="" class="form-control" name="" value="{{$userClient->name}}" disabled>
                        <input type="hidden" name="Shipment[client_id]" value="{{$userClient->id}}">
                    @else
                        <select
                            id="client-id" onchange="selectIdTriggered()"
                            class="form-control select-client  @error('Shipment.client_id') is-invalid @enderror"
                            data-control="select2"
                            data-placeholder="{{ __('cargo::view.choose_client') }}"
                            data-allow-clear="true"
                            name="Shipment[client_id]"
                        >
                            <option></option>
                            @foreach($clients as $client)
                                <option
                                    value="{{$client->id}}"
                                    data-phone="{{$client->responsible_mobile}}"
                                    data-country="{{$client->country_code}}"
                                    data-package_access_options="{{$client->package_access_options}}"
                                    {{ old('Shipment.client_id') == $client->id ? 'selected' : '' }}
                                    @if($typeForm == 'edit')
                                        {{ $model->client_id == $client->id ? 'selected' : '' }}
                                    @endif
                                > {{$client->name}}</option>
                            @endforeach

                        </select>
                        @error('Shipment.client_id')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    @endif

                </div>
            </div>
            <input hidden type="text" name="package_access_options" id="package_access_options" value="" />

            <div class="col-md-6">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required" style=" display: block; ">{{ __('cargo::view.client_phone') }}</label>

                    @if($user_role == $auth_client)
                        @if($typeForm == 'create')
                            <input type="tel" name="Shipment[client_phone]" placeholder="{{ __('cargo::view.client_phone') }}"  id="client_phone" value="{{$userClient->country_code.($userClient->responsible_mobile ??  base_country_code())}}" class="phone_input phone_input_1  number-only form-control @error('Shipment.client_phone') is-invalid @enderror" />
                            <input type="hidden" class="country_code country_code_1" name="Shipment[country_code]" value="{{$userClient->country_code ?? base_country_code()}}" data-reflection="phone">
                        @elseif($typeForm == 'edit')
                            <input type="tel" name="Shipment[client_phone]" placeholder="{{ __('cargo::view.client_phone') }}"  id="client_phone" value="{{ old('Shipment.client_phone', isset($model) ? $model->country_code.$model->client_phone :  base_country_code() ) }}" class="phone_input phone_input_1  number-only  form-control @error('Shipment.client_phone') is-invalid @enderror" />
                            <input type="hidden" class="country_code country_code_1" id="country_code" name="Shipment[country_code]" value="{{ old('country_code', isset($model) ? $model->country_code :  base_country_code()) }}" data-reflection="phone">
                        @endif
                    @else
                        <input type="tel" name="Shipment[client_phone]" placeholder="{{ __('cargo::view.client_phone') }}"  id="client_phone" value="{{ old('Shipment.client_phone', isset($model) ? $model->country_code.$model->client_phone :  base_country_code() ) }}" class="phone_input phone_input_1  number-only  form-control @error('Shipment.client_phone') is-invalid @enderror" />
                        <input type="hidden" class="country_code country_code_1" id="country_code" name="Shipment[country_code]" value="{{ old('country_code', isset($model) ? $model->country_code :  base_country_code()) }}" data-reflection="phone">
                    @endif

                    @error('Shipment.client_phone')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.client_address') }}</label>
                    <select
                        id="client-addresses"
                        name="Shipment[client_address]" class="form-control select-address @error('Shipment.client_id') is-invalid @enderror"
                    >
                        <option value=""></option>
                        @if($typeForm == 'edit')
                            @foreach(Modules\Cargo\Entities\ClientAddress::where('client_id', $model->client_id)->where('is_archived',0)->get() as $address)
                                <option
                                    value="{{$address->id}}"
                                    {{ old('Shipment.client_address') == $address->id ? 'selected' : '' }}
                                    @if($typeForm == 'edit')
                                        {{ $model->client_address == $address->id ? 'selected' : '' }}
                                    @endif
                                > {{$address->address}}</option>
                            @endforeach
                        @endif

                    </select>
                    @error('Shipment.client_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>

            <div class="p-3 mb-4 mt-4 col-md-12" id="show_address_div" style="border: 1px solid #e4e6ef; display:none">
                <div class="row">
                    @csrf
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.country') }}</label>
                            <select id="change-country-client-address" name="country_id" class="form-control select-country @error('country_id') is-invalid @enderror">
                                <option value=""></option>
                                @foreach($countries as $country)
                                    <option value="{{$country->id}}">{{$country->name}}</option>
                                @endforeach
                            </select>
                            @error('country_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.region') }}</label>
                            <select @error('state_id') is-invalid @enderror id="change-state-client-address" name="state_id" class="form-control select-state">
                                <option value=""></option>

                            </select>
                            @error('state_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.area') }}</label>
                    <select @error('area_id') is-invalid @enderror name="area_id" style="display: block !important;" class="form-control select-area">
                        <option value=""></option>

                    </select>
                    @error('area_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.address') }}</label>
                    <input @error('client_address') is-invalid @enderror type="text" placeholder="{{ __('cargo::view.address') }}" name="client_address" class="form-control" />
                    @error('client_address')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
                @if($googleMap)
                    <div class="location-client">
                        <label class="col-form-label fw-bold fs-6">{{ __('cargo::view.location') }}</label>
                        <input type="text" class="form-control address-client " placeholder="{{ __('cargo::view.location') }}" name="client_street_address_map"  rel="client" value="" />
                        <input type="hidden" class="form-control lat" data-client="lat" name="client_lat" />
                        <input type="hidden" class="form-control lng" data-client="lng" name="client_lng" />
                        <input type="hidden" class="form-control url" data-client="url" name="client_url" />

                        <div class="mt-2 col-sm-12 map_canvas map-client" style="width:100%;height:300px;"></div>
                        <span class="form-text text-muted">{{'Change the pin to select the right location'}}</span>
                    </div>
                @endif
                <div class="mt-4">
                    <button type="button" class="btn btn-primary" onclick="AddNewClientAddress()">{{ __('cargo::view.save') }}</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddressDiv()">{{ __('cargo::view.close') }}</button>
                </div>
            </div>


            <div class="col-md-6">
                <div class="form-group client-select">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.receiver_name') }}</label>
                    @if($user_role == $auth_client)
                        <input type="text" placeholder="" class="form-control" name="Shipment[reciver_name]" value="" >
                    @else
                        <select
                            id="reciver-id"
                            class="form-control select-reciver  @error('Shipment.reciver_name') is-invalid @enderror"
                            data-control="select2"
                            data-placeholder="{{ __('cargo::view.choose_client') }}"
                            data-allow-clear="true"
                            name="Shipment[reciver_name]"
                        >
                            <option></option>
                            @foreach($Recivers as $client)
                                <option
                                    value="{{$client->name}}"
                                    data-phone="{{$client->receiver_mobile}}"
                                    data-country="{{$client->country_code}}"
                                    data-address="{{$client->reciver_address}}"
                                    {{ old('Shipment.reciver_name') == $client->name ? 'selected' : '' }}
                                    @if($typeForm == 'edit')
                                        {{ $model->reciver_name == $client->name ? 'selected' : '' }}
                                    @endif
                                > {{$client->name}}</option>
                            @endforeach
                        </select>
                        @error('Shipment.reciver_name')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    @endif

                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required" style=" display: block; ">{{ __('cargo::view.receiver_phone') }}</label>

                    @if($user_role == $auth_client)
                        @if($typeForm == 'create')
                            <input type="tel" name="Shipment[reciver_phone]" placeholder="{{ __('cargo::view.reciver_phone') }}"  id="reciver_phone" value="{{base_country_code()}}" class="phone_input phone_input_2  number-only form-control @error('Shipment.reciver_phone') is-invalid @enderror" />
                            <input type="hidden" class="country_code country_code_2" name="Shipment[country_code]" value="{{ base_country_code()}}" data-reflection="phone">
                        @elseif($typeForm == 'edit')
                            <input type="tel" name="Shipment[reciver_phone]" placeholder="{{ __('cargo::view.reciver_phone') }}"  id="reciver_phone" value="" class="phone_input phone_input_2  number-only  form-control @error('Shipment.reciver_phone') is-invalid @enderror" />
                            <input type="hidden" class="country_code country_code_2" id="country_code" name="Shipment[country_code]" value="{{ old('country_code', isset($model) ? $model->country_code :  base_country_code()) }}" data-reflection="phone">
                        @endif
                    @else
                        <input type="tel" name="Shipment[reciver_phone]" placeholder="{{ __('cargo::view.reciver_phone') }}"  id="reciver_phone" value="{{ old('Shipment.reciver_phone', isset($model) ? $model->country_code.$model->reciver_phone :  base_country_code() ) }}" class="phone_input phone_input_2  number-only  form-control @error('Shipment.reciver_phone') is-invalid @enderror" />
                        <input type="hidden" class="country_code country_code_2" id="country_code" name="Shipment[country_code]" value="{{ old('country_code', isset($model) ? $model->country_code :  base_country_code()) }}" data-reflection="phone">
                    @endif

                    @error('Shipment.reciver_phone')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>
            </div>

            <div class="col-md-12">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.receiver_address') }}</label>
                    <input type="text"  id="reciver_address"   value="{{ old('Shipment.reciver_address', isset($model) ? $model->reciver_address : '') }}" placeholder="{{ __('cargo::view.receiver_address') }}" name="Shipment[reciver_address]" class="form-control @error('Shipment.reciver_address') is-invalid @enderror  address" data-reflection="address"/>
                    @error('Shipment.reciver_address')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>


            @if($googleMap)
                <div class="col-md-12">
                    <div class="location-receiver">
                        <label class="col-form-label fw-bold fs-6">{{ __('cargo::view.receiver_location') }}</label>
                        <input type="text" class="form-control address-receiver " placeholder="{{ __('cargo::view.receiver_location') }}" name="Shipment[reciver_street_address_map]" value="{{ old('Shipment.reciver_street_address_map', isset($model) ? $model->reciver_street_address_map : '') }}"  rel="receiver" value="" />
                        <input type="hidden" class="form-control lat" data-receiver="lat" name="Shipment[reciver_lat]" value="{{ old('Shipment.reciver_lat', isset($model) ? $model->reciver_lat : '') }}" />
                        <input type="hidden" class="form-control lng" data-receiver="lng" name="Shipment[reciver_lng]" value="{{ old('Shipment.reciver_lng', isset($model) ? $model->reciver_lng : '') }}" />
                        <input type="hidden" class="form-control url" data-receiver="url" name="Shipment[reciver_url]" value="{{ old('Shipment.reciver_url', isset($model) ? $model->reciver_url : '') }}" />

                        <div class="mt-2 col-sm-12 map_canvas map-receiver" style="width:100%;height:300px;"></div>
                        <span class="form-text text-muted">{{'Change the pin to select the right location'}}</span>
                    </div>
                </div>
            @endif

        </div>
        <hr>
        <div class="row mb-5">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.from_country') }}</label>
                    <select id="change-country" name="Shipment[from_country_id]" class="form-control select-country @error('Shipment.from_country_id') is-invalid @enderror">
                        <option value=""></option>
                        @foreach($countries as $country)
                            <option
                                value="{{$country->id}}"
                                {{ old('Shipment.from_country_id') == $country->id ? 'selected' : '' }}
                                @if($typeForm == 'edit')
                                    {{ $model->from_country_id == $country->id ? 'selected' : '' }}
                                @endif
                            >{{$country->name}}</option>
                        @endforeach
                    </select>
                    @error('Shipment.from_country_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.to_country') }}</label>
                    <select id="change-country-to" name="Shipment[to_country_id]" class="form-control select-country @error('Shipment.to_country_id') is-invalid @enderror">
                        <option value=""></option>
                        @foreach($countries as $country)
                            <option
                                value="{{$country->id}}"
                                {{ old('Shipment.to_country_id') == $country->id ? 'selected' : '' }}
                                @if($typeForm == 'edit')
                                    {{ $model->to_country_id == $country->id ? 'selected' : '' }}
                                @endif
                            >{{$country->name}}</option>
                        @endforeach
                    </select>
                    @error('Shipment.to_country_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
        </div>
        <div class="row mb-5">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.from_region') }}</label>
                    <select id="change-state-from" name="Shipment[from_state_id]" class="form-control select-state @error('Shipment.from_state_id') is-invalid @enderror">
                        <option value=""></option>
                        @if($typeForm == 'edit')
                            @foreach(Modules\Cargo\Entities\State::where('country_id',$model->from_country_id)->where('covered',1)->get() as $item)
                                <option
                                    value="{{$item->id}}"
                                    @if($typeForm == 'edit')
                                        {{ $model->from_state_id == $item->id ? 'selected' : '' }}
                                    @endif
                                >{{$item->name}}</option>
                            @endforeach
                        @endif
                    </select>
                    @error('Shipment.from_state_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.to_region') }}</label>
                    <select id="change-state-to" name="Shipment[to_state_id]" class="form-control select-state @error('Shipment.to_state_id') is-invalid @enderror">
                        <option value=""></option>
                        @if($typeForm == 'edit')
                            @foreach(Modules\Cargo\Entities\State::where('country_id',$model->to_country_id)->where('covered',1)->get() as $item)
                                <option
                                    value="{{$item->id}}"
                                    @if($typeForm == 'edit')
                                        {{ $model->to_state_id == $item->id ? 'selected' : '' }}
                                    @endif
                                >{{$item->name}}</option>
                            @endforeach
                        @endif
                    </select>
                    @error('Shipment.to_state_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
        </div>
        <div class="row mb-5">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.from_area') }}</label>
                    <select name="Shipment[from_area_id]" id="from_area_id" class="form-control select-area @error('Shipment.from_area_id') is-invalid @enderror">
                        <option value=""></option>
                        @if($typeForm == 'edit')
                            @foreach(Modules\Cargo\Entities\Area::where('state_id',$model->from_state_id)->get() as $item)
                                <option
                                    value="{{$item->id}}"
                                    @if($typeForm == 'edit')
                                        {{ $model->from_area_id == $item->id ? 'selected' : '' }}
                                    @endif
                                >{{json_decode($item->name, true)[app()->getLocale()]}}</option>
                            @endforeach
                        @endif
                    </select>
                    @error('Shipment.from_area_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.to_area') }}</label>
                    <select name="Shipment[to_area_id]" class="form-control select-area @error('Shipment.to_area_id') is-invalid @enderror">
                        <option value=""></option>
                        @if($typeForm == 'edit')
                            @foreach(Modules\Cargo\Entities\Area::where('state_id',$model->to_state_id)->get() as $item)
                                <option
                                    value="{{$item->id}}"
                                    @if($typeForm == 'edit')
                                        {{ $model->to_area_id == $item->id ? 'selected' : '' }}
                                    @endif
                                >{{json_decode($item->name, true)[app()->getLocale()]}}</option>
                            @endforeach
                        @endif
                    </select>
                    @error('Shipment.to_area_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
        </div>
        <hr>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.payment_type') }}</label>
                    <select
                        class="form-control kt-select2 payment-type"
                        id="payment_type" name="Shipment[payment_type]"
                        data-control="select2"
                        data-placeholder="{{ __('cargo::view.payment_type') }}"
                        data-allow-clear="true"
                    >
                        <option @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_payment_type')=='1' ) selected @endif value="1"
                            {{ old('Shipment.payment_type') == 1 ? 'selected' : '' }}
                            @if($typeForm == 'edit')
                                {{ $model->payment_type == 1 ? 'selected' : '' }}
                            @endif
                        >{{ __('cargo::view.postpaid') }}</option>
                        <option @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_payment_type')=='2' ) selected @endif value="2"
                            {{ old('Shipment.payment_type') == 2 ? 'selected' : '' }}
                            @if($typeForm == 'edit')
                                {{ $model->payment_type == 2 ? 'selected' : '' }}
                            @endif
                        >{{ __('cargo::view.prepaid') }}</option>
                    </select>

                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6">{{ __('cargo::view.payment_method') }}</label>
                    <select disabled
                        class="form-control kt-select2 payment-method @error('Shipment.payment_method_id') is-invalid @enderror"
                        id="payment_method_id" name="Shipment[payment_method_id]"
                        data-control="select2"
                        data-placeholder="{{ __('cargo::view.payment_method') }}"
                        data-allow-clear="true"
                    >
                    <option value="auto">Automatic</option>
                        {{-- @foreach ($paymentSettings as $key => $gateway){
                            @if($gateway)
                                <option value="{{$key}}"
                                    @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_payment_method') == $key) selected @endif
                                    {{ old('Shipment.payment_method_id') == $key ? 'selected' : '' }}
                                    @if($typeForm == 'edit')
                                        {{ $model->payment_method_id == $key ? 'selected' : '' }}
                                    @endif
                                >{{$key}}</option>
                            @endif
                        @endforeach --}}
                    </select>
                    @error('Shipment.payment_method_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6">{{ __('cargo::view.order_id') }}(optional)</label>
                    <input type="text" placeholder="{{ __('cargo::view.order_id') }}" name="Shipment[order_id]" class="form-control @error('Shipment.order_id') is-invalid @enderror" value="{{ old('Shipment.order_id', isset($model) ? $model->order_id : '' ) }}" />
                </div>
                @error('Shipment.order_id')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror
                @error('Shipment.order_id')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror
            </div>
            {{-- <div class="col-md-12">
                <div class="form-group">
                    <label class="col-form-label fw-bold fs-6">{{ __('cargo::view.attachments') }}</label>

                    @if(isset($model))
                        <x-media-library-collection name="image" :model="$model" collection="attachments" rules="mimes:jpg,jpeg,png,gif,bmp,svg,webp"/>
                    @else
                        <x-media-library-attachment multiple name="image" rules="mimes:jpg,jpeg,png,gif,bmp,svg,webp"/>
                    @endif
                </div>
            </div> --}}
        </div>
        <hr>

        <div id="kt_repeater_1">
            <div class="row" id="">
                <h2 class="text-left">{{ __('cargo::view.package_info') }}</h2>
                <div data-repeater-list="Package" class="col-lg-12">
                    @if($typeForm == 'create')
                        <div data-repeater-item class="row align-items-center" style="margin-top: 15px;padding-bottom: 15px;padding-top: 15px;border-top:1px solid #ccc;border-bottom:1px solid #ccc;">

                            <div class="col-md-3">
                                <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.package_type') }}</label>
                                <select
                                    class="form-control package-type-select"
                                    name="package_id"
                                >
                                    <option></option>
                                    @foreach($packages as $package)
                                    <option @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_package_type')==$package->id) selected @endif
                                        value="{{$package->id}}"
                                        {{ old('package_id') == $package->id ? 'selected' : '' }}
                                    >{{ json_decode($package->name, true)[app()->getLocale()] }}</option>
                                    @endforeach
                                </select>
                                <div class="mb-2 d-md-none"></div>
                            </div>
                            <div class="col-md-3">
                                <label class="col-form-label fw-bold fs-6">{{ __('cargo::view.description') }}</label>
                                <input type="text" placeholder="{{ __('cargo::view.description') }}" class="form-control" name="description" value="{{ old('description', isset($model) ? $model->description : '' ) }}" >
                                <div class="mb-2 d-md-none"></div>
                            </div>
                            <div class="col-md-3">

                                <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.quantity') }}</label>

                                <input class="form-control kt_touchspin_qty" placeholder="{{ __('cargo::view.quantity') }}" type="number" min="1" name="qty" value="{{ old('qty', isset($model) ? $model->qty : 1 ) }}" />
                                <div class="mb-2 d-md-none"></div>

                            </div>
                            <div class="col-md-3">

                                <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.weight') }}</label>

                                <input type="number" min="1" placeholder="{{ __('cargo::view.weight') }}" name="weight" class="form-control weight-listener kt_touchspin_weight" onchange="calcTotalWeight()" value="{{ old('weight', isset($model) ? $model->weight : 0.5 ) }}" />
                                <div class="mb-2 d-md-none"></div>

                            </div>
                            <div class="col-md-12" style="margin-top: 10px;">
                                <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.dimensions_length_width_height') }}</label>
                            </div>
                            <div class="col-md-2">
                                <input class="form-control dimensions_r" type="number" min="1" placeholder="{{ __('cargo::view.length') }}" name="length" value="{{ old('length', isset($model) ? $model->length : 1 ) }}" />
                            </div>
                            <div class="col-md-2">

                                <input class="form-control dimensions_r" type="number" min="1" placeholder="{{ __('cargo::view.width') }}" name="width" value="{{ old('width', isset($model) ? $model->width : 1 ) }}" />

                            </div>
                            <div class="col-md-2">

                                <input class="form-control dimensions_r" type="number" min="1"  placeholder="{{ __('cargo::view.height') }}" name="height" value="{{ old('height', isset($model) ? $model->height : 1 ) }}" />

                            </div>


                            <div class="col-md-4">
                                <div>
                                    <a href="javascript:;" data-repeater-delete="" class="btn btn-sm font-weight-bolder btn-light-danger delete_item">
                                        <i class="la la-trash-o"></i>{{ __('cargo::view.delete') }}
                                    </a>
                                </div>
                            </div>

                        </div>
                    @elseif($typeForm == 'edit')
                        @foreach(Modules\Cargo\Entities\PackageShipment::where('shipment_id',$model->id)->get() as $pack)
                            <div data-repeater-item class="row align-items-center" style="margin-top: 15px;padding-bottom: 15px;padding-top: 15px;border-top:1px solid #ccc;border-bottom:1px solid #ccc;">
                                <input type="hidden" name="id" value="{{ $pack->id }}">
                                <div class="col-md-3">
                                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.package_type') }}</label>
                                    <select
                                        class="form-control package-type-select"
                                        name="package_id"
                                    >
                                        <option></option>
                                        @foreach($packages as $package)
                                        <option @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_package_type')==$package->id) selected @endif
                                            value="{{$package->id}}"
                                            @if($pack->package_id == $package->id) selected @endif
                                            {{ old('package_id') == $package->id ? 'selected' : '' }}
                                        >{{ json_decode($package->name, true)[app()->getLocale()] }}</option>
                                        @endforeach
                                    </select>
                                    <div class="mb-2 d-md-none"></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="col-form-label fw-bold fs-6">{{ __('cargo::view.description') }}</label>
                                    <input type="text" placeholder="{{ __('cargo::view.description') }}" class="form-control" name="description" value="{{$pack->description}}" >
                                    <div class="mb-2 d-md-none"></div>
                                </div>
                                <div class="col-md-3">

                                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.quantity') }}</label>

                                    <input class="form-control kt_touchspin_qty" placeholder="{{ __('cargo::view.quantity') }}" type="number" min="1" name="qty" value="{{ $pack->qty }}" />
                                    <div class="mb-2 d-md-none"></div>

                                </div>
                                <div class="col-md-3">

                                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.weight') }}</label>

                                    <input type="number" min="0" step="0.01" placeholder="{{ __('cargo::view.weight') }}" name="weight" class="form-control weight-listener kt_touchspin_weight" onchange="calcTotalWeight()" value="{{ $pack->weight }}" />
                                    <div class="mb-2 d-md-none"></div>

                                </div>
                                <div class="col-md-12" style="margin-top: 10px;">
                                    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.dimensions_length_width_height') }}</label>
                                </div>
                                <div class="col-md-2">
                                    <input class="form-control dimensions_r" type="number" min="1" placeholder="{{ __('cargo::view.length') }}" name="length" value="{{ $pack->length }}" />
                                </div>
                                <div class="col-md-2">

                                    <input class="form-control dimensions_r" type="number" min="1" placeholder="{{ __('cargo::view.width') }}" name="width" value="{{ $pack->width }}" />

                                </div>
                                <div class="col-md-2">

                                    <input class="form-control dimensions_r" type="number" min="1"  placeholder="{{ __('cargo::view.height') }}" name="height" value="{{ $pack->height }}" />

                                </div>
                                <div class="col-md-4">
                                    <div>
                                        @if($model->consignment_id)
                                            <span class="form-text text-muted">Existing parcel rows are retained for audit safety.</span>
                                        @else
                                            <a href="javascript:;" data-repeater-delete="" class="btn btn-sm font-weight-bolder btn-light-danger delete_item">
                                                <i class="la la-trash-o"></i>{{ __('cargo::view.delete') }}
                                            </a>
                                        @endif
                                    </div>
                                </div>

                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
            <div class="form-group row">
                <div class="">
                    <label class="text-right col-form-label">{{ __('cargo::view.add_package') }}</label>
                    <div>
                        <a href="javascript:;" data-repeater-create="" class="btn btn-sm font-weight-bolder btn-light-primary">
                            <i class="la la-plus"></i>{{ __('cargo::view.add_package') }}
                        </a>
                    </div>
                </div>
            </div>
            <hr>
            @if($typeForm == 'edit')
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-4">
                            <label>{{ __('cargo::view.tax_duty') }}:</label>
                            <input id="kt_touchspin_2" type="text" @if($user_role == $auth_client) disabled @endif class="form-control" value="{{ old('Shipment.tax', isset($model) ? $model->tax : '') }}" name="Shipment[tax]" />
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-4">
                            <label>{{ __('cargo::view.insurance') }}:</label>
                            <input id="kt_touchspin_2_2" type="text" @if($user_role == $auth_client) disabled @endif class="form-control" value="{{ old('Shipment.insurance', isset($model) ? $model->insurance : '') }}" name="Shipment[insurance]" />
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-4">
                            @if($is_def_mile_or_fees=='2')
                                <label>{{ __('cargo::view.shipping_cost') }}:</label>
                            @elseif($is_def_mile_or_fees=='1')
                                <label>{{ __('cargo::view.mile_cost') }}:</label>
                            @endif
                            <input id="kt_touchspin_3_shipping_cost" type="text" @if($user_role == $auth_client) disabled @endif class="form-control" value="{{ old('Shipment.shipping_cost', isset($model) ? $model->shipping_cost : '') }}" name="Shipment[shipping_cost]" />
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-4">
                            @if($is_def_mile_or_fees=='2')
                                <label>{{ __('cargo::view.return_cost') }}:</label>
                            @elseif($is_def_mile_or_fees=='1')
                                <label>{{ __('cargo::view.return_mile_cost') }}:</label>
                            @endif
                            <input id="kt_touchspin_3_3" type="text" @if($user_role == $auth_client) disabled @endif class="form-control" value="{{ old('Shipment.return_cost', isset($model) ? $model->return_cost : '') }}" name="Shipment[return_cost]" />
                        </div>
                    </div>
                </div>
                <hr>
            @endif
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-form-label fw-bold fs-6">{{ __('cargo::view.amount_to_be_collected') }}</label>

                        <input id="kt_touchspin_3" placeholder="{{ __('cargo::view.amount_to_be_collected') }}" type="number" min="0" class="form-control @error('Shipment.amount_to_be_collected') is-invalid @enderror" value="{{ old('Shipment.amount_to_be_collected', isset($model) ? $model->amount_to_be_collected : 0 ) }}" name="Shipment[amount_to_be_collected]" />
                        @error('Shipment.amount_to_be_collected')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.total_weight') }}</label>
                        <input id="kt_touchspin_4" placeholder="{{ __('cargo::view.total_weight') }}" type="text" min="0.5" value="{{ old('Shipment.total_weight', isset($model) ? $model->total_weight : 0.5 ) }}" class="form-control @if($typeForm == 'create') total-weight @endif kt_touchspin_weight @error('Shipment.total_weight') is-invalid @enderror" name="Shipment[total_weight]" />
                        @error('Shipment.total_weight')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>

            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group">
                        <label class="col-form-label fw-bold fs-6">{{ __('cargo::view.delivery_time') }}</label>
                        <select class="form-control kt-select2 delivery-time @error('Shipment.delivery_time') is-invalid @enderror" id="delivery_time" name="Shipment[delivery_time]">
                            @foreach($deliveryTimes as $deliveryTime)
                                <option value="{{$deliveryTime->id}}"
                                    {{ old('Shipment.delivery_time') == $deliveryTime->id ? 'selected' : '' }}
                                    @if($typeForm == 'edit')
                                        {{ $model->delivery_time == $deliveryTime->id ? 'selected' : '' }}
                                    @endif
                                >{{json_decode($deliveryTime->name, true)[app()->getLocale()]}}</option>
                            @endforeach
                        </select>
                        @error('Shipment.delivery_time')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>

        <div class="mb-0 text-right form-group">

            <!-- Button trigger modal -->
            <button type="button" class="btn btn-sm btn-primary d-none" data-toggle="modal" data-target="#exampleModalCenter" id="modal_open">
                {{ __('cargo::view.save') }}
            </button>

            <!-- Modal -->
            <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLongTitle">{{ __('cargo::view.estimation_cost') }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close" id="modal_close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="text-left modal-body">
                            <div class="row">
                                @if($is_def_mile_or_fees=='2')
                                    <div class="col-6">{{ __('cargo::view.shipping_cost') }} :</div>
                                    <div class="col-6" id="shipping_cost"></div>
                                @elseif($is_def_mile_or_fees=='1')
                                    <div class="col-6">{{ __('cargo::view.mile_cost') }} :</div>
                                    <div class="col-6" id="mile_cost"></div>
                                @endif
                            </div>
                            <div class="row">
                                <div class="col-6">{{ __('cargo::view.tax_duty') }} :</div>
                                <div class="col-6" id="tax_duty"></div>
                            </div>
                            <div class="row">
                                <div class="col-6">{{ __('cargo::view.insurance') }} :</div>
                                <div class="col-6" id="insurance"></div>
                            </div>
                            <div class="row">
                                @if(Modules\Cargo\Entities\ShipmentSetting::getVal('is_def_mile_or_fees')=='2')
                                    <div class="col-6">{{ __('cargo::view.return_cost') }} :</div>
                                    <div class="col-6" id="return_cost"></div>
                                @elseif(Modules\Cargo\Entities\ShipmentSetting::getVal('is_def_mile_or_fees')=='1')
                                    <div class="col-6">{{ __('cargo::view.return_mile_cost') }} :</div>
                                    <div class="col-6" id="return_mile_cost"></div>
                                @endif
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-6">{{ __('cargo::view.total_cost') }} :</div>
                                <div class="col-6" id="total_cost"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('cargo::view.close') }}</button>
                            <button type="submit" class="btnclicky btn btn-primary">{{ __('cargo::view.save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="card-footer d-flex justify-content-end py-6 px-9">
    <a href="{{ url()->previous() }}" class="btn btn-light btn-active-light-primary me-2">@lang('view.discard')</a>
    <button type="button" class="btn btn-success" onclick="get_estimation_cost()" id="kt_account_profile_details_submit">@lang('view.update')</button>
</div>
{{-- Inject styles --}}
@section('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-touchspin/4.3.0/jquery.bootstrap-touchspin.css" integrity="sha512-g4B+TyvVE4aa0Y1SgjMHnT/4M4IRl8jnG3Ha9ebg8VhLyrfaAqL5AHDh7zo0/ZdES57Y1E7wvWwsDzX806b1Gw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-touchspin/4.3.0/jquery.bootstrap-touchspin.min.css" integrity="sha512-0GlDFjxPsBIRh0ZGa2IMkNT54XGNaGqeJQLtMAw6EMEDQJ0WqpnU6COVA91cUS0CeVA5HtfBfzS9rlJR3bPMyw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css" rel="Stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.min.css" crossorigin="anonymous" />
    <style>

        .input-group .iti--allow-dropdown,.phone_input ,.iti {
            width: 100% !important;
        }

        /* Chrome, Safari, Edge, Opera */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
        }

        /* Firefox */
        input[type=number] {
        -moz-appearance: textfield;
        }
    </style>
@endsection

{{-- Inject Scripts --}}
{{-- @push('js-component') --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.repeater/1.2.1/jquery.repeater.min.js" integrity="sha512-foIijUdV0fR0Zew7vmw98E6mOWd9gkGWQBWaoA1EOFAx+pY+N8FmmtIYAVj64R98KeD2wzZh1aHK0JSpKmRH8w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.repeater/1.2.1/jquery.repeater.js" integrity="sha512-bZAXvpVfp1+9AUHQzekEZaXclsgSlAeEnMJ6LfFAvjqYUVZfcuVXeQoN5LhD7Uw0Jy4NCY9q3kbdEXbwhZUmUQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-touchspin/4.3.0/jquery.bootstrap-touchspin.min.js" integrity="sha512-0hFHNPMD0WpvGGNbOaTXP0pTO9NkUeVSqW5uFG2f5F9nKyDuHE3T4xnfKhAhnAZWZIO/gBLacwVvxxq0HuZNqw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-touchspin/4.3.0/jquery.bootstrap-touchspin.js" integrity="sha512-k59zBVzm+v8h8BmbntzgQeJbRVBK6AL1doDblD1pSZ50rwUwQmC/qMLZ92/8PcbHWpWYeFaf9hCICWXaiMYVRg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.3/umd/popper.min.js" integrity="sha384-vFJXuSJphROIrBnz7yo7oB41mKfc8JzQZiCq4NCceLEaO4IHwicKwpJf9c9IpFgh" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/js/bootstrap.min.js" integrity="sha384-alpBpkh1PFOepccYVYDB4do5UnbKysX5WZXm3XxPqe5iKTfUKjNkCk9SaVuEZflJ" crossorigin="anonymous"></script>
    <script src="{{ asset('assets/global/js/jquery.geocomplete.js') }}"></script>
    <script src="//maps.googleapis.com/maps/api/js?libraries=places&key={{$google_map_key}}"></script>
  <!-- REQUIRED CDN  -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8-beta.1/inputmask.js" integrity="sha512-aSxEzzrnqlqgASdjAelu/V291nzZNygMSFMJ0h4PFQ+uwdEz6zKkgsIMbcv0O0ZPwFRNPFWssY7gcL2gZ6/t9A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js" crossorigin="anonymous"></script>


    <script>
        // Map Address For Receiver
        $('.address-receiver').each(function(){
            var address = $(this);
            address.geocomplete({
                map: ".map_canvas.map-receiver",
                mapOptions: {
                    zoom: 8,
                    center: { lat: -34.397, lng: 150.644 },
                },
                markerOptions: {
                    draggable: true
                },
                details: ".location-receiver",
                detailsAttribute: 'data-receiver',
                autoselect: true,
                restoreValueAfterBlur: true,
            });
            address.bind("geocode:dragged", function(event, latLng){
                $("input[data-receiver=lat]").val(latLng.lat());
                $("input[data-receiver=lng]").val(latLng.lng());
            });
        });

        // Map Address For Client
        $('.address-client').each(function(){
            var address = $(this);
            address.geocomplete({
                map: ".map_canvas.map-client",
                mapOptions: {
                    zoom: 8,
                    center: { lat: -34.397, lng: 150.644 },
                },
                markerOptions: {
                    draggable: true
                },
                details: ".location-client",
                detailsAttribute: 'data-client',
                autoselect: true,
                restoreValueAfterBlur: true,
            });
            address.bind("geocode:dragged", function(event, latLng){
                $("input[data-client=lat]").val(latLng.lat());
                $("input[data-client=lng]").val(latLng.lng());
            });
        });

        // IF Branches Select Not Have Branches
        @if(auth()->user()->can('create-branches') || $user_role == $admin)
            $('.select-branch').select2().on('select2:open', () => {
                $(".select2-results:not(:has(a))").append(`<li style='list-style: none; padding: 10px;'><a style="width: 100%" href="{{route('branches.create')}}?redirect=shipments.create"
                    class="btn btn-primary" >+ {{ __('cargo::view.create_new_branch') }}</a>
                    </li>`);
            });
        @endif

        var inputDate = $(`#kt_datepicker_3`);

        // Trigger date picker for Shipping Date
        inputDate.daterangepicker({
            showDropdowns: true,
            singleDatePicker: true,
            autoUpdateInput: false,
            autoclose: true,
            minYear: parseInt(moment().format('YYYY')) - 10,
            maxYear: parseInt(moment().format('YYYY')) + 10,
            todayHighlight: true,
            startDate: new Date(),
            todayBtn: true,
            locale: {
                format: "DD/MM/YYYY",
                cancelLabel: "{{ __('view.cancel') }}",
                applyLabel: "{{ __('view.apply') }}",
                "fromLabel": "{{ __('view.from') }}",
                "toLabel": "{{ __('view.to') }}",
                "customRangeLabel": "{{ __('datepicker.custom_range') }}",
                "weekLabel": "{{ __('datepicker.week_label') }}",
                "daysOfWeek": [
                    "{{ __('datepicker.days_of_week.sunday') }}",
                    "{{ __('datepicker.days_of_week.monday') }}",
                    "{{ __('datepicker.days_of_week.tuesday') }}",
                    "{{ __('datepicker.days_of_week.wednesday') }}",
                    "{{ __('datepicker.days_of_week.thursday') }}",
                    "{{ __('datepicker.days_of_week.friday') }}",
                    "{{ __('datepicker.days_of_week.saturday') }}",
                ],
                "monthNames": [
                    "{{ __('datepicker.month_names.january') }}",
                    "{{ __('datepicker.month_names.february') }}",
                    "{{ __('datepicker.month_names.march') }}",
                    "{{ __('datepicker.month_names.april') }}",
                    "{{ __('datepicker.month_names.may') }}",
                    "{{ __('datepicker.month_names.june') }}",
                    "{{ __('datepicker.month_names.july') }}",
                    "{{ __('datepicker.month_names.august') }}",
                    "{{ __('datepicker.month_names.september') }}",
                    "{{ __('datepicker.month_names.october') }}",
                    "{{ __('datepicker.month_names.november') }}",
                    "{{ __('datepicker.month_names.december') }}",
                ],
            }
        }, cb);

        // call back after choose date
        function cb(start) {
            var apiDate = start ? start.format("YYYY-MM-DD H:m") : '';
            var inputShowDate = start ? (start.format("YYYY-MM-DD")) : '';
            if (start) {
                inputDate.val(inputShowDate);
            }
        }

        // Trigger time picker for Collection Time
        $('#kt_timepicker_3').daterangepicker({
            timePicker: true,
            singleDatePicker:true,
            timePicker24Hour: false,
            timePickerIncrement: 1,
            timePickerSeconds: true,
            startDate: moment(),
            locale: {
                format: 'HH:mm:ss A'
            }
        }).on('show.daterangepicker', function (ev, picker) {
            picker.container.find(".calendar-table").hide();
        });


        // IF Clients Select Not Have Clients
        @if(auth()->user()->can('create-clients') || $user_role == $admin || $user_role == $auth_branch)
            $('.select-client').select2().on('select2:open', () => {
                $(".select2-results:not(:has(a))").append(`<li style='list-style: none; padding: 10px;'><a style="width: 100%"
                    class="btn btn-primary create-new-client" >+ {{ __('cargo::view.create_new_client') }}</a>
                    </li>`);
                $(".create-new-client").click(() => {
                    $("#myModal").modal("show");
                  //    $(".create-new-client").hide();
                });
            });
        @endif
        // IF Clients Select Not Have reciver
        @if( $user_role == $admin || $user_role == $auth_branch)
            $('.select-reciver').select2().on('select2:open', () => {
                $(".select2-results:not(:has(a))").append(`<li style='list-style: none; padding: 10px;'><a style="width: 100%"
                    class="btn btn-primary reciver-new-client" >+ {{ __('cargo::view.create_new_receiver') }}</a>
                    </li>`);

                    // href="{{route('receivers.create')}}?redirect=shipments.create"
                $(".reciver-new-client").click(() => {
                    $("#myModalReciver").modal("show");

                });
            });
        @endif

        // IF Payments Select Not Have Clients
        @if(auth()->user()->can('payments-settings') || $user_role == $admin)
            $('.payment-method').select2().on('select2:open', () => {
                $(".select2-results:not(:has(a))").append(`<li style='list-style: none; padding: 10px;'><a style="width: 100%" href="{{route('payments.index')}}?redirect=shipments.create"
                    class="btn btn-primary" >+ {{ __('cargo::view.add_payment') }}</a>
                    </li>`);
            });
        @endif


        // Set Client Phone After Select Client
        $('.select-client').change(function(){
            var client_phone = $(this).find(':selected').data('phone');
            var client_country_code = $(this).find(':selected').data('country');
            var package_access_options = $(this).find(':selected').data('package_access_options');

            $("#client_phone").val(client_phone);
            $("#country_code").val(client_country_code);
            $("#package_access_options").val(package_access_options);

        })
        // Set Client Phone After Select Client
        $('.select-reciver').change(function(){
            var client_phone = $(this).find(':selected').data('phone');
            var client_country_code = $(this).find(':selected').data('country');
            var client_reciver_address = $(this).find(':selected').data('address');

            $("#reciver_phone").val(client_phone);
            $("#country_code").val(client_country_code);
            $("#reciver_address").val(client_reciver_address);

        })

        // Get Client Id
        function selectIdTriggered()
        {
            getClientAddresses(document.getElementById("client-id").value);
        }

        $('.select-address').select2({
            placeholder: "{{ __('cargo::view.choose_client_first') }}",
        })

        // Ajax Get Client Addresses By Id
        function getClientAddresses(client_id)
        {
            var id = client_id;

            $.get("{{route('ajax-get-client-addresses-ajax')}}?client_id=" + id, function(data) {
                if(data.length != 0){

                    $('select[name ="Shipment[client_address]"]').empty();
                    $('select[name ="Shipment[client_address]"]').append('<option value=""></option>');
                    for (let index = 0; index < data.length; index++) {
                        const element = data[index];
                        var old_client_address = {{old('Shipment.client_address') ? old('Shipment.client_address') : 'null'}};
                        var edit_client_address = 'address';
                        // var edit_client_address = {{ isset($model) ? $model->client_address : 'null'}};
                        if(element['is_default'] == 1 || old_client_address == element['id'] || edit_client_address == element['id']){
                            $('select[name ="Shipment[client_address]"]').append('<option selected value="' + element['id'] + '">' + element['address'] + '</option>');
                        }else{
                            $('select[name ="Shipment[client_address]"]').append('<option value="' + element['id'] + '">' + element['address'] + '</option>');
                        }

                    }

                    $('.select-address').select2({
                        placeholder: "{{ __('cargo::view.choose_address') }}",
                    })
                    @if($user_role == $admin || $user_role == $auth_client || $user_role == $auth_branch || auth()->user()->can('manage-clients') )
                        .on('select2:open', () => {

                            $('.toRemoveLi').remove();

                            $(".select2-results:not(:has(a))").append(`<li style='list-style: none; padding: 10px;' class='toRemoveLi'><a style="width: 100%" onclick="openAddressDiv()"
                                class="btn btn-primary" >+ {{ __('cargo::view.create_new_address') }}</a>
                                </li>`);
                        });
                    @endif
                }else{
                    $('select[name ="Shipment[client_address]"]').empty();
                    $('.select-address').select2({
                        placeholder: "{{ __('cargo::view.no_addresses_found') }}",
                    })
                    @if($user_role == $admin || $user_role == $auth_client || auth()->user()->can('manage-clients') )
                        .on('select2:open', () => {

                            $('.toRemoveLi').remove();

                            $(".select2-results:not(:has(a))").append(`<li style='list-style: none; padding: 10px;' class='toRemoveLi'><a style="width: 100%" onclick="openAddressDiv()"
                                class="btn btn-primary" >+ {{ __('cargo::view.create_new_address') }}</a>
                                </li>`);
                        });
                    @endif
                }
            });
        }

        // Ajax Get One Client Addresses By Id
        $('#client-addresses').change(function() {
            var id = $(this).val();
            $.get("{{route('client.get.one.address')}}?address_id=" + id, function(data) {
                $("#change-country").val(data[0]['country_id']).change();
                setTimeout(function(){
                    $("#change-state-from").val(data[0]['state_id']).change();
                    if(data[0]['area_id'] != null || data[0]['area_id'] != ""){
                        setTimeout(function(){
                            $("#from_area_id").val(data[0]['area_id']).change();
                        }, 800);
                    }
                }, 800);
            });
        });

        @if(old('Shipment.client_id'))
            getClientAddresses({{old('Shipment.client_id')}});
        @endif
        // Ajax Get Address With Client logged in
        @if($user_role == $auth_client)
            getClientAddresses({{$userClient->id}});
        @endif

        // Ajax Add New Address For Client
        function AddNewClientAddress()
        {
            @if($user_role == $auth_client)
                var id                    = {{$userClient->id}};
            @else
                var id                    = document.getElementById("client-id").value;
            @endif
            var address                   = document.getElementsByName("client_address")[0].value;
            var country = $('select[name ="country_id"]').val();
            var state = $('select[name ="state_id"]').val();
            var area = $('select[name ="area_id"]').val();

            @if($googleMap)
                var client_street_address_map = document.getElementsByName("client_street_address_map")[0].value;
                var client_lat                = document.getElementsByName("client_lat")[0].value;
                var client_lng                = document.getElementsByName("client_lng")[0].value;
                var client_url                = document.getElementsByName("client_url")[0].value;
                if(address != "" && country != "" && state != "" && address != null && country != null && state != null )
                {
                    $.post( "{{route('client.add.new.address')}}",
                    {
                        _token: "{{ csrf_token() }}",
                        client_id: parseInt(id),
                        address: address,
                        client_street_address_map: client_street_address_map,
                        client_lat: client_lat,
                        client_lng: client_lng,
                        client_url: client_url,
                        country: country,
                        state: state,
                        area: area
                    } , function(data){
                        $('select[name ="Shipment[client_address]"]').empty();
                        for (let index = 0; index < data.length; index++) {
                            const element = data[index];
                            $('select[name ="Shipment[client_address]"]').append('<option value="' + element['id'] + '">' + element['address'] + '</option>');
                        }
                        document.getElementsByName("client_address")[0].value            = "";
                        document.getElementsByName("client_street_address_map")[0].value = "";
                        document.getElementsByName("client_lat")[0].value = "";
                        document.getElementsByName("client_lng")[0].value = "";
                        document.getElementsByName("client_url")[0].value = "";
                    });
                }else{
                    Swal.fire("{{__('cargo::view.please_enter_all_reqired_fields') }}", "", "error");
                }
            @else
                if(address != "" && country != "" && state != "" && address != null && country != null && state != null )
                {
                    $.post( "{{route('client.add.new.address')}}",
                    {
                        _token: "{{ csrf_token() }}",
                        client_id: parseInt(id),
                        address: address,
                        country: country,
                        state: state,
                        area: area
                    } , function(data){
                        $('select[name ="Shipment[client_address]"]').empty();
                        for (let index = 0; index < data.length; index++) {
                            const element = data[index];
                            $('select[name ="Shipment[client_address]"]').append('<option value="' + element['id'] + '">' + element['address'] + '</option>');
                        }
                        document.getElementsByName("client_address")[0].value  = "";
                        var country = $('select[name ="country_id"]').val();
                        var state = $('select[name ="state_id"]').val();
                        var area = $('select[name ="area_id"]').val();
                    });
                }else{
                    Swal.fire("{{ __('cargo::view.please_enter_all_reqired_fields') }}", "", "error");
                }
            @endif
        }

        function openAddressDiv()
        {
            $( "#show_address_div" ).slideDown( "slow", function() {
                // Animation complete.
            });
        }
        function closeAddressDiv()
        {
            $( "#show_address_div" ).slideUp( "slow", function() {
                // Animation complete.
            });
        }

        $('#change-country-client-address').change(function() {
            var id = $(this).val();
            $.get("{{route('ajax.getStates')}}?country_id=" + id, function(data) {
                $('select[name ="state_id"]').empty();
                $('select[name ="state_id"]').append('<option value=""></option>');
                for (let index = 0; index < data.length; index++) {
                    const element = data[index];

                    $('select[name ="state_id"]').append('<option value="' + element['id'] + '">' + element['name'] + '</option>');
                }
            });
            @if(auth()->user()->can('add-covered-countries') || $user_role == $admin)
                $('.select-state').on('select2:open', () => {
                    $(".select2-results:not(:has(a))").append(`<li style='list-style: none; padding: 10px;'><a style="width: 100%" href="{{route('countries.index')}}?redirect=shipments.create"
                        class="btn btn-primary" >{{ __('cargo::view.manage')}} {{__('cargo::view.covered_countries') }}</a>
                        </li>`);
                });
            @endif
        });
        $('#change-state-client-address').change(function() {
            var id = $(this).val();
            $.get("{{route('ajax.getAreas')}}?state_id=" + id, function(data) {
                $('select[name ="area_id"]').empty();
                $('select[name ="area_id"]').append('<option value=""></option>');
                for (let index = 0; index < data.length; index++) {
                    const element = data[index];
                    $('select[name ="area_id"]').append('<option value="' + element['id'] + '">' + JSON.parse(element['name'], true)[`{{app()->getLocale()}}`] + '</option>');
                }
            });

            @if(auth()->user()->can('create-areas') || $user_role == $admin)
                $('.select-area').on('select2:open', () => {
                    $(".select2-results:not(:has(a))").append(`<li style='list-style: none; padding: 10px;'><a style="width: 100%" href="{{route('areas.create')}}?redirect=shipments.create"
                        class="btn btn-primary" >{{ __('cargo::view.areas_management') }}</a>
                        </li>`);
                });
            @endif
        });

        $('.select-country').select2({
            placeholder: "{{ __('cargo::view.choose_country') }}",
            width: '100%'
        })
        @if(auth()->user()->can('add-covered-countries') || $user_role == $admin)
            .on('select2:open', () => {
                $(".select2-results:not(:has(a))").append(`<li style='list-style: none; padding: 10px;'><a style="width: 100%" href="{{route('countries.index')}}?redirect=shipments.create"
                    class="btn btn-primary" >{{ __('cargo::view.manage')}} {{__('cargo::view.covered_countries') }}</a>
                    </li>`);
            });
        @endif

        $('.select-state').select2({
            placeholder: "{{ __('cargo::view.choose_region') }}",
            width: '100%'
        })
        @if(auth()->user()->can('add-covered-regions') || $user_role == $admin)
            .on('select2:open', () => {
                $(".select2-results:not(:has(a))").append(`<li style='list-style: none; padding: 10px;'><a style="width: 100%" href="{{route('countries.index')}}?redirect=shipments.create"
                    class="btn btn-primary" >{{ __('cargo::view.manage')}} {{__('cargo::view.covered_regions') }}</a>
                    </li>`);
            });
        @endif

        $('.select-area').select2({
            placeholder: "{{ __('cargo::view.choose_area') }}",
            width: '100%'
        })
        @if(auth()->user()->can('manage-areas') || $user_role == $admin)
            .on('select2:open', () => {
                $(".select2-results:not(:has(a))").append(`<li style='list-style: none; padding: 10px;'><a style="width: 100%" href="{{route('areas.index')}}?redirect=shipments.create"
                    class="btn btn-primary" >{{ __('cargo::view.manage')}} {{__('cargo::view.areas') }}</a>
                    </li>`);
            });
        @endif

        function changeCountry(id){
            $.get("{{route('ajax.getStates')}}?country_id=" + id, function(data) {
                $('select[name ="Shipment[from_state_id]"]').empty();
                $('select[name ="Shipment[from_state_id]"]').append('<option value=""></option>');
                for (let index = 0; index < data.length; index++) {
                    const element = data[index];

                    var old_from_state = {{old('Shipment.from_state_id') ? old('Shipment.from_state_id') : 'null'}};
                    if(old_from_state == element['id'] ){
                        $('select[name ="Shipment[from_state_id]"]').append('<option selected value="' + element['id'] + '">' + element['name'] + '</option>');
                    }else{
                        $('select[name ="Shipment[from_state_id]"]').append('<option value="' + element['id'] + '">' + element['name'] + '</option>');
                    }
                }
            });
        }
        $('#change-country').change(function() {
            var id = $(this).val();
            changeCountry(id)
        });
        @if(old('Shipment.from_country_id'))
            changeCountry({{old('Shipment.from_country_id')}})
        @endif

        function changeCountryTo(id){
            $.get("{{route('ajax.getStates')}}?country_id=" + id, function(data) {
                $('select[name ="Shipment[to_state_id]"]').empty();
                $('select[name ="Shipment[to_state_id]"]').append('<option value=""></option>');
                for (let index = 0; index < data.length; index++) {
                    const element = data[index];
                    var old_to_state = {{old('Shipment.to_state_id') ? old('Shipment.to_state_id') : 'null'}};
                    if(old_to_state == element['id'] ){
                        $('select[name ="Shipment[to_state_id]"]').append('<option selected value="' + element['id'] + '">' + element['name'] + '</option>');
                    }else{
                        $('select[name ="Shipment[to_state_id]"]').append('<option value="' + element['id'] + '">' + element['name'] + '</option>');
                    }
                }
            });
        }
        $('#change-country-to').change(function() {
            var id = $(this).val();
            changeCountryTo(id);
        });
        @if(old('Shipment.to_country_id'))
            changeCountryTo({{old('Shipment.to_country_id')}})
        @endif

        function changeStateFrom(id){
            $.get("{{route('ajax.getAreas')}}?state_id=" + id, function(data) {
                $('select[name ="Shipment[from_area_id]"]').empty();
                $('select[name ="Shipment[from_area_id]"]').append('<option value=""></option>');
                for (let index = 0; index < data.length; index++) {
                    const element = data[index];
                    var old_from_area = {{old('Shipment.from_area_id') ? old('Shipment.from_area_id') : 'null'}};
                    if(old_from_area == element['id'] ){
                        $('select[name ="Shipment[from_area_id]"]').append('<option selected value="' + element['id'] + '">' + JSON.parse(element['name'], true)[`{{app()->getLocale()}}`] + '</option>');
                    }else{
                        $('select[name ="Shipment[from_area_id]"]').append('<option value="' + element['id'] + '">' + JSON.parse(element['name'], true)[`{{app()->getLocale()}}`] + '</option>');
                    }
                }
            });
        }
        $('#change-state-from').change(function() {
            var id = $(this).val();
            changeStateFrom(id);
        });
        @if(old('Shipment.from_state_id'))
            changeStateFrom({{old('Shipment.from_state_id')}})
        @endif

        function changeStateTo(id){
            $.get("{{route('ajax.getAreas')}}?state_id=" + id, function(data) {
                $('select[name ="Shipment[to_area_id]"]').empty();
                $('select[name ="Shipment[to_area_id]"]').append('<option value=""></option>');
                for (let index = 0; index < data.length; index++) {
                    const element = data[index];
                    var old_to_area = {{old('Shipment.to_area_id') ? old('Shipment.to_area_id') : 'null'}};
                    if(old_to_area == element['id'] ){
                        $('select[name ="Shipment[to_area_id]"]').append('<option selected value="' + element['id'] + '">' + JSON.parse(element['name'], true)[`{{app()->getLocale()}}`] + '</option>');
                    }else{
                        $('select[name ="Shipment[to_area_id]"]').append('<option value="' + element['id'] + '">' + JSON.parse(element['name'], true)[`{{app()->getLocale()}}`] + '</option>');
                    }
                }
            });
        }
        $('#change-state-to').change(function() {
            var id = $(this).val();
            changeStateTo(id);
        });
        @if(old('Shipment.to_state_id'))
            changeStateTo({{old('Shipment.to_state_id')}})
        @endif

        //Package Types Repeater

        $('#kt_repeater_1').repeater({
            initEmpty: false,

            show: function() {
                $(this).slideDown();

                $('.dimensions_r').TouchSpin({
                    buttondown_class: 'btn btn-secondary',
                    buttonup_class: 'btn btn-secondary',

                    min: 1,
                    max: 1000000000,
                    stepinterval: 50,
                    maxboostedstep: 10000000,
                    initval: 1,
                });

                $('.kt_touchspin_weight').TouchSpin({
                    buttondown_class: 'btn btn-secondary',
                    buttonup_class: 'btn btn-secondary',

                    step: 0.5,
                    min: 0.5,
                    boostat: 5,
                    min: 1,
                    max: 1000000000,
                    stepinterval: 50,
                    maxboostedstep: 50,
                    initval: 1,
                    prefix: 'Kg'
                });
                $('.kt_touchspin_qty').TouchSpin({
                    buttondown_class: 'btn btn-secondary',
                    buttonup_class: 'btn btn-secondary',

                    min: 1,
                    max: 1000000000,
                    stepinterval: 50,
                    maxboostedstep: 10000000,
                    initval: 1,
                });
                calcTotalWeight();
            },

            hide: function(deleteElement) {
                $(this).slideUp(deleteElement);
            },

            isFirstItemUndeletable: true
        });

        $('body').on('click', '.delete_item', function(){
            $('.total-weight').val("{{ __('cargo::view.calculated') }}");
            setTimeout(function(){ calcTotalWeight(); }, 500);
        });

        $('#kt_touchspin_2, #kt_touchspin_2_2').TouchSpin({
            buttondown_class: 'btn btn-secondary',
            buttonup_class: 'btn btn-secondary',

            min: -1000000000,
            max: 1000000000,
            stepinterval: 50,
            maxboostedstep: 10000000,
            prefix: '%'
        });
        $('#kt_touchspin_3, #kt_touchspin_3_shipping_cost, #kt_touchspin_3_3').TouchSpin({
            buttondown_class: 'btn btn-secondary',
            buttonup_class: 'btn btn-secondary',

            min: 0,
            max: 1000000000,
            stepinterval: 50,
            maxboostedstep: 10000000,
            step: 0.001,
            decimals: 3,
            prefix: ''
        });
        $('.kt_touchspin_weight').TouchSpin({
            buttondown_class: 'btn btn-secondary',
            buttonup_class: 'btn btn-secondary',

            step: 0.5,
            decimals: 1,
            boostat: 5,
            min: 0.5,
            max: 1000000000,
            stepinterval: 50,
            maxboostedstep: 50,
            initval: 1,
            prefix: 'Kg'
        });
        $('.kt_touchspin_qty').TouchSpin({
            buttondown_class: 'btn btn-secondary',
            buttonup_class: 'btn btn-secondary',

            min: 1,
            max: 1000000000,
            stepinterval: 50,
            maxboostedstep: 10000000,
            initval: 1,
        });
        $('.dimensions_r').TouchSpin({
            buttondown_class: 'btn btn-secondary',
            buttonup_class: 'btn btn-secondary',

            min: 1,
            max: 1000000000,
            stepinterval: 50,
            maxboostedstep: 10000000,
            initval: 1,
        });

        $('.delivery-time').select2({ placeholder: "{{ __('cargo::view.delivery_time') }}" })
        @if(auth()->user()->can('create-delivery-time') || $user_role == $admin)
            .on('select2:open', () => {
                $(".select2-results:not(:has(a))").append(`<li style='list-style: none; padding: 10px;'><a style="width: 100%" href="{{route('deliveryTime.create')}}?redirect=shipments.create"
                    class="btn btn-primary" >{{ __('cargo::view.manage')}} {{__('cargo::view.delivery_time') }}</a>
                    </li>`);
            });
        @endif

        function calcTotalWeight() {
            var elements = $('.weight-listener');
            var sumWeight = 0;
            elements.map(function() {
                sumWeight += parseFloat($(this).val());
            }).get();
            $('.total-weight').val(sumWeight);
        }

        function get_estimation_cost() {
            var total_weight = document.getElementById('kt_touchspin_4').value;
            var select_packages = document.getElementsByClassName('package-type-select');

            var from_country_id = document.getElementsByName("Shipment[from_country_id]")[0].value;
            var to_country_id = document.getElementsByName("Shipment[to_country_id]")[0].value;
            var from_state_id = document.getElementsByName("Shipment[from_state_id]")[0].value;
            var to_state_id = document.getElementsByName("Shipment[to_state_id]")[0].value;
            var from_area_id = document.getElementsByName("Shipment[from_area_id]")[0].value;
            var to_area_id = document.getElementsByName("Shipment[to_area_id]")[0].value;
            @if($user_role == $auth_client)
                var client_id = {{$userClient->id}};
            @else
                var client_id = document.getElementById("client-id").value;
                console.log(client_id);
            @endif

            var package_ids = [];
            for (let index = 0; index < select_packages.length; index++) {
                if(select_packages[index].value){
                    package_ids[index] = new Object();
                    package_ids[index]["package_id"] = select_packages[index].value;
                }else{
                    Swal.fire("{{ __('cargo::view.please_select_package_type')}}" + (index+1), "", "error");
                    return 0;
                }
            }

            var request_data = { _token : '{{ csrf_token() }}',

                package_ids : package_ids,
                total_weight : total_weight,
                from_country_id : from_country_id,
                to_country_id : to_country_id,
                from_state_id : from_state_id,
                to_state_id : to_state_id,
                from_area_id : from_area_id,
                to_area_id : to_area_id,
                client_id : client_id,
            };
            $.post('{{ route('shipments.get-estimation-cost') }}', request_data, function(response){

                if({{$is_def_mile_or_fees}} =='2')
                {
                    document.getElementById("shipping_cost").innerHTML = response.shipping_cost;
                    document.getElementById("return_cost").innerHTML = response.return_cost;
                }else if({{$is_def_mile_or_fees}} =='1')
                {
                    document.getElementById("mile_cost").innerHTML = response.shipping_cost;
                    document.getElementById("return_mile_cost").innerHTML = response.return_cost;
                }
                document.getElementById("tax_duty").innerHTML = response.tax;
                document.getElementById("insurance").innerHTML = response.insurance;
                document.getElementById("total_cost").innerHTML = response.total_cost;
                document.getElementById('modal_open').click();
            });
        }


        $(function () {
            let phoneNumbers_1 = $('.phone_input_1'),
                wrong_number = window.wrong_number_msg,
                required_phone = window.required_phone

                phoneNumbers_1.each(function () {
                let self = $(this),
                    input = self[0],
                    type = self.attr('data-type');
                    // initialise plugin
                let iti = window.intlTelInput(input, {
                    separateDialCode: true,
                    utilsScript: window.static_asset_utils_file,
                    initialCountry: "ng",
                    preferredCountries: ["eg","ng","ke"],
                    autoPlaceholder: "aggressive"
                });
                 // Set Client Phone After Select Client
                $('.select-client').change(function(){
                    var client_phone = $(this).find(':selected').data('phone');
                    var client_country_code = $(this).find(':selected').data('country');
                        // Change the country code
                    iti.setNumber(client_country_code + client_phone)
                })
                input.addEventListener("countrychange", function() {
                    $('.phone_input_1').filter(`[data-reflection="${type}"]`).val(iti.getSelectedCountryData().dialCode);
                    $('.country_code_1').val('+'+iti.getSelectedCountryData().dialCode);
                });
                let reset = function() {
                    self.parent().next('.invalid-feedback').remove();
                    self.parent().removeClass('not-valid');
                    self.removeClass("error is-invalid");
                };
                let addError = function(msg) {
                    self.addClass('error is-invalid');
                    self.parent().addClass('not-valid');
                    self.parent().after("<span style='display: block' class=\"invalid-feedback\" role=\"alert\">\n" +
                        " <strong>" + msg + "</strong>\n" +
                        " </span>");
                    return false;
                };
                // on blur: validate
                input.addEventListener('blur', function() {
                    reset();
                    if (self.attr('required')) {
                        if (input.value.trim() == '') {
                            return addError('field is empty')
                        }
                    }
                    if (input.value.trim() && !iti.isValidNumber()) {
                        return addError('reqierd')
                    }
                    // run code if verified
                });
                // on keyup / change flag: reset
                input.addEventListener('change', reset);
                input.addEventListener('keyup', reset);
            })
            $(".number-only").keypress(function(event){
                var ewn = event.which;
                if(ewn >= 48 && ewn <= 57) {
                    return true;
                }
                return false;
            });
            $(".phone-validation").on("submit", function(evt) {
                var phoneField = $(this).find(".phone_input_1");
                if (phoneField.hasClass('error')) {
                    evt.preventDefault();
                    return false
                } else {
                    //do the rest of your validations here
                    $(this).submit();
                }
            });

        });

        $(function () {
            let phoneNumbers_2 = $('.phone_input_2'),
                wrong_number = window.wrong_number_msg,
                required_phone = window.required_phone

                phoneNumbers_2.each(function () {
                let self = $(this),
                    input = self[0],
                    type = self.attr('data-type');
                    // initialise plugin
                let iti = window.intlTelInput(input, {
                    separateDialCode: true,
                    utilsScript: window.static_asset_utils_file,
                    initialCountry: "ng",
                    preferredCountries: ["eg","ng","ke"],
                    autoPlaceholder: "aggressive"
                });
                // Set Reciver Phone After Select Client
                $('.select-reciver').change(function(){
                    var client_phone = $(this).find(':selected').data('phone');
                    var client_country_code = $(this).find(':selected').data('country');

                     // Change the country code
                    iti.setNumber(client_country_code + client_phone)
                })
                input.addEventListener("countrychange", function() {
                    $('.phone_input_2').filter(`[data-reflection="${type}"]`).val(iti.getSelectedCountryData().dialCode);
                    $('.country_code_2').val('+'+iti.getSelectedCountryData().dialCode);
                });
                let reset = function() {
                    self.parent().next('.invalid-feedback').remove();
                    self.parent().removeClass('not-valid');
                    self.removeClass("error is-invalid");
                };
                let addError = function(msg) {
                    self.addClass('error is-invalid');
                    self.parent().addClass('not-valid');
                    self.parent().after("<span style='display: block' class=\"invalid-feedback\" role=\"alert\">\n" +
                        " <strong>" + msg + "</strong>\n" +
                        " </span>");
                    return false;
                };
                // on blur: validate
                input.addEventListener('blur', function() {
                    reset();
                    if (self.attr('required')) {
                        if (input.value.trim() == '') {
                            return addError('field is empty')
                        }
                    }
                    if (input.value.trim() && !iti.isValidNumber()) {
                        return addError('reqierd')
                    }
                    // run code if verified
                });
                // on keyup / change flag: reset
                input.addEventListener('change', reset);
                input.addEventListener('keyup', reset);
            })

            $(".number-only").keypress(function(event){
                var ewn = event.which;
                if(ewn >= 48 && ewn <= 57) {
                    return true;
                }
                return false;
            });

            $(".phone-validation").on("submit", function(evt) {
                var phoneField = $(this).find(".phone_input_2");
                if (phoneField.hasClass('error')) {
                    evt.preventDefault();
                    return false
                } else {
                    //do the rest of your validations here
                    $(this).submit();
                }
            });
        });


        $('.address').filter(`[data-reflection="${type}"]`).val(iti.getSelectedCountryData().dialCode);

    </script>
{{-- @endpush --}}
