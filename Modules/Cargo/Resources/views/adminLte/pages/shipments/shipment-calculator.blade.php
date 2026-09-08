@extends('cargo::adminLte.layouts.master')

@php
    $pageTitle =  __('cargo::view.shipment_calculator');

    $googleSettings = resolve(\app\Models\GoogleSettings::class)->toArray();
    $googleMap = json_decode($googleSettings['google_map'], true);
    $google_map_key = '';
    if($googleMap){
        $google_map_key = $googleMap['google_map_key'];
    }

    $mile_price = Modules\Cargo\Entities\ShipmentSetting::getVal('def_mile_cost');
    if(!$mile_price)
    {
        $mile_price = '0';
    }
    $countries = Modules\Cargo\Entities\Country::where('covered',1)->get();
    $packages = Modules\Cargo\Entities\Package::all();
    $is_def_mile_or_fees = Modules\Cargo\Entities\ShipmentSetting::getVal('is_def_mile_or_fees');
    if(!$is_def_mile_or_fees){
        $is_def_mile_or_fees = 0;
    }
    $deliveryTimes = Modules\Cargo\Entities\DeliveryTime::all();

    // is_def_mile_or_fees if result 1 for mile if result 2 for fees

    $branches = Modules\Cargo\Entities\Branch::where('is_archived', 0)->get();
    $paymentSettings = resolve(\Modules\Payments\Entities\PaymentSetting::class)->toArray();
@endphp

@section('page-title', $pageTitle )

@section('page-type', 'page')

@section('styles')
    <style>
        /* Enhanced Shipment Calculator Styling - Smooth, Compact, Professional */

        :root {
        --primary-color: #ff700a;
        --primary-light: rgba(255, 112, 10, 0.1);
        --text-dark: #212121;
        --text-medium: #536273;
        --text-light: #8a859d;
        --border-color: #eaecee;
        --white: #ffffff;
        --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
        --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
        --transition: all 0.25s ease;
        }

        /* Main Container */
        #grad1 {
        max-width: 1120px;
        margin: 60px auto;
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        #msform {
        position: relative;
        margin-top: 20px;
        }

        /* Fieldsets */
        .fieldset_1,
        .fieldset_2,
        .fieldset_3 {
        background: var(--white);
        margin-bottom: 30px;
        padding: 28px !important;
        border-radius: 8px;
        box-shadow: var(--shadow-sm);
        border: none;
        position: relative;
        transition: var(--transition);
        }

        .fieldset_1:after,
        .fieldset_2:after,
        .fieldset_3:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: var(--primary-color);
        border-radius: 0 0 8px 8px;
        }

        .fieldset_1:hover,
        .fieldset_2:hover,
        .fieldset_3:hover {
        box-shadow: var(--shadow-md);
        }

        #msform fieldset {
        background: transparent;
        border: 0 none;
        width: 100%;
        margin: 0 !important;
        padding: 0 !important;
        position: relative;
        }

        #msform fieldset:not(:first-of-type) {
        display: none;
        }

        /* Progress Bar */
        #progressbar {
        margin-bottom: 40px;
        overflow: hidden;
        font-size: 15px;
        color: var(--text-medium);
        display: flex;
        justify-content: space-between;
        padding-left: 0;
        }

        #progressbar .active {
        color: var(--text-dark);
        font-weight: 600;
        }

        #progressbar li {
        list-style-type: none;
        width: 33%;
        float: left;
        position: relative;
        text-align: center;
        }

        #progressbar #account:before,
        #progressbar #personal:before,
        #progressbar #payment:before {
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        }

        #progressbar #account:before {
        content: "1";
        }

        #progressbar #personal:before {
        content: "2";
        }

        #progressbar #payment:before {
        content: "3";
        }

        #progressbar li:before {
        width: 44px;
        height: 44px;
        display: block;
        color: var(--text-medium);
        background: var(--border-color);
        border-radius: 50%;
        margin: 0 auto 10px auto;
        line-height: 44px;
        font-size: 16px;
        transition: var(--transition);
        }

        #progressbar li:after {
        content: '';
        width: 82%;
        height: 2px;
        background: var(--border-color);
        position: absolute;
        left: -41%;
        top: 22px;
        z-index: -1;
        transition: var(--transition);
        }

        .progressbar-account:after {
        content: '';
        width: 0% !important;
        height: 0px !important;
        }

        #progressbar li.active:before,
        #progressbar li.active:after {
        background: var(--primary-color);
        color: var(--white);
        }

        /* Tabs */
        .nav-tabs {
        border-bottom: none !important;
        display: flex;
        overflow: hidden;
        border-radius: 6px;
        margin-bottom: 28px !important;
        }

        .nav-tabs .nav-item {
        flex: 1;
        text-align: center !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        text-transform: uppercase !important;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 0.5px;
        }

        .nav-tabs .nav-link {
        border: none !important;
        color: var(--text-medium) !important;
        background-color: var(--border-color);
        padding: 15px !important;
        width: 100%;
        transition: var(--transition);
        }

        .nav-tabs .nav-item.show .nav-link,
        .nav-tabs .nav-link.active {
        color: var(--white) !important;
        width: 100% !important;
        padding: 15px !important;
        background-color: var(--primary-color);
        border-radius: 0px !important;
        }

        /* Form Elements */
        .chbs-form-label-group {
        display: block;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
        padding: 14px 21px;
        background-color: var(--border-color);
        color: var(--text-medium);
        text-align: center;
        margin-bottom: 24px !important;
        border-radius: 6px;
        letter-spacing: 0.5px;
        }

        .fade {
        border: 1px solid var(--border-color);
        border-radius: 6px;
        padding: 20px;
        }

        .chbs-form-field {
        position: relative;
        text-align: unset;
        padding-bottom: 20px;
        margin-bottom: 5px;
        }

        .chbs-form-field label {
        clear: both;
        margin-bottom: 7px;
        font-weight: 500;
        font-size: 13px;
        text-transform: uppercase;
        color: var(--text-medium);
        display: block;
        text-align: unset !important;
        letter-spacing: 0.3px;
        }

        .chbs-form-field input,
        .chbs-form-field select {
        outline: none;
        width: 100%;
        transition: var(--transition);
        }

        .chbs-form-field select,
        .chbs-form-field input:not([type=checkbox]):not([type=radio]) {
        width: 100%;
        min-height: 48px;
        line-height: 48px;
        padding: 0 16px;
        appearance: none;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        color: var(--text-dark);
        font-size: 14px;
        }

        .chbs-form-field select:focus,
        .chbs-form-field input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(255, 112, 10, 0.1);
        }

        /* Custom Select */
        .search_categories {
        position: relative;
        }

        .search_categories .select:after {
        content: "";
        position: absolute;
        right: 16px;
        top: 50%;
        transform: translateY(-50%);
        width: 0;
        height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-top: 5px solid var(--text-medium);
        pointer-events: none;
        }

        .select-country {
        width: 100%;
        min-height: 48px;
        padding: 0 16px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        color: var(--text-dark);
        font-size: 14px;
        appearance: none;
        background-color: var(--white);
        transition: var(--transition);
        }

        .select-country:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(255, 112, 10, 0.1);
        outline: none;
        }

        /* Results Section */
        #result {
        margin: 25px auto 0;
        }

        #result .chbs-meta {
        background: var(--primary-light);
        border-radius: 8px;
        padding: 20px;
        height: 100%;
        transition: var(--transition);
        display: flex;
        align-items: center;
        }

        #result .chbs-meta:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-sm);
        }

        .chbs-meta-icon-route {
        color: var(--primary-color);
        font-size: 24px;
        float: left;
        margin-right: 20px;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(255, 112, 10, 0.12);
        border-radius: 50%;
        }

        .chbs-meta-title {
        color: var(--text-medium);
        display: block;
        text-align: left;
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        }

        .chbs-meta-count {
        color: var(--primary-color);
        display: block;
        text-align: left;
        font-size: 18px;
        padding-top: 6px;
        font-weight: 700;
        }

        /* Error Messages */
        .error {
        position: absolute;
        top: -6px;
        font-size: 12px;
        background-color: #fff2f2;
        padding: 8px 12px;
        color: #d8000c;
        width: auto !important;
        min-width: 200px;
        display: none;
        border-radius: 4px;
        border-left: 3px solid #d8000c;
        box-shadow: var(--shadow-sm);
        z-index: 10;
        }

        .error::before {
        content: "";
        width: 0;
        height: 0;
        border-bottom: solid rgba(0,0,0,0) 8px;
        border-right: solid rgba(0,0,0,0) 8px;
        border-left: solid #fff2f2 8px;
        position: absolute;
        top: 28px;
        left: 0;
        }

        /* Navigation Buttons */
        .steps-navi {
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
        }

        .steps-navi input {
        min-height: 48px;
        padding: 0 30px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 14px;
        letter-spacing: 0.5px;
        cursor: pointer;
        transition: var(--transition);
        }

        .steps-navi .next {
        background-color: var(--primary-color);
        color: var(--white);
        border: none;
        }

        .steps-navi .navi-nxt{
        background-color: var(--primary-color);
        color: var(--white);
        border: none;
        }

        .steps-navi .next:hover {
        background-color: #e86600;
        box-shadow: 0 4px 12px rgba(255, 112, 10, 0.3);
        }

        .steps-navi .previous {
        background-color: var(--white);
        color: var(--text-medium);
        border: 1px solid var(--border-color);
        }

        .steps-navi .previous:hover {
        background-color: #f8f9fa;
        color: var(--text-dark);
        }

        /* Map Container */
        .responsive_map {
        height: 380px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        margin-bottom: 25px;
        }

        /* Responsive Adjustments */
        @media (min-width: 300px) and (max-width: 993px) {
        .responsive_map {
            height: 260px;
            margin-top: 20px;
        }

        #grad1 {
            padding: 0 15px;
        }

        .fieldset_1,
        .fieldset_2,
        .fieldset_3 {
            padding: 20px !important;
        }

        .chbs-meta {
            padding: 15px !important;
            margin-bottom: 15px;
        }

        .chbs-meta-icon-route {
            font-size: 20px;
            margin-right: 15px;
            width: 40px;
            height: 40px;
        }

        .chbs-meta-count {
            font-size: 16px;
        }

        .steps-navi input {
            padding: 0 20px;
        }

        #progressbar {
            margin-bottom: 30px;
        }

        #progressbar li {
            font-size: 12px;
        }

        #progressbar li:before {
            width: 38px;
            height: 38px;
            line-height: 38px;
            font-size: 14px;
        }

        .nav-tabs .nav-link {
            padding: 12px !important;
            font-size: 12px;
        }
        }

        /* Row Layout */
        .row {
        display: flex;
        flex-wrap: wrap;
        margin-right: -15px;
        margin-left: -15px;
        }

        /* Column Layouts */
        .col-lg-3, .col-md-6, .col-sm-12 {
        position: relative;
        width: 100%;
        padding-right: 15px;
        padding-left: 15px;
        }

        @media (min-width: 576px) {
        .col-sm-12 {
            flex: 0 0 100%;
            max-width: 100%;
        }
        }

        @media (min-width: 768px) {
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }
        }

        @media (min-width: 992px) {
        .col-lg-3 {
            flex: 0 0 25%;
            max-width: 25%;
        }

        .col-lg-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }

        .col-lg-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }
        }

        /* Slight animations */
        @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(255, 112, 10, 0.4);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(255, 112, 10, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(255, 112, 10, 0);
        }
        }

        .steps-navi .next:focus {
        animation: pulse 1.5s;
        }

        /* Tab content */
        .tab-content {
        margin-top: 10px;
        }

        .tab-pane {
        display: none;
        }

        .tab-pane.show.active {
        display: block;
        animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
        }

        /* Custom alerts */
        .alert {
        margin-block: 2rem;
        padding: 1rem 1.25rem;
        display: grid;
        grid-gap: 1rem;
        grid-template-columns: max-content auto;
        border-radius: 6px;
        border-width: 3px;
        border-left-style: solid;
        position: relative;
        overflow: hidden;
        background-color: var(--white);
        box-shadow: var(--shadow-sm);
        }

        .alert:before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        mix-blend-mode: soft-light;
        background: linear-gradient(90deg, rgba(255,255,255,0) 30%, rgba(255,255,255,1) 56%, rgba(2,0,36,0.1) 82%);
        z-index: 1;
        }

        .alert .icon,
        .alert .content {
        z-index: 2;
        }

        .alert .icon {
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        }

        .alert .title {
        font-weight: 700;
        margin-bottom: 0.5rem;
        font-size: 1rem;
        }

        .alert .content {
        max-width: 60ch;
        }

        .alert.alert--warning {
        background-color: #fff5eb;
        border-left-color: var(--primary-color);
        }

        .alert.alert--warning .icon {
        color: var(--primary-color);
        }

        @media (max-width: 767px) {
        .alert {
            grid-template-columns: auto;
            padding: 0.875rem;
            grid-gap: 0.75rem;
        }

        .alert .icon {
            font-size: 1.25rem;
        }

        .alert .title {
            margin-bottom: 0.375rem;
        }
        }

        /* Sidebar tweaks */
        .sidebar-main #grad1 {
        margin: 30px auto 0;
        }

        .sidebar-main #grad1 #progressbar li {
        font-size: 11px;
        }

        .sidebar-main #grad1 #progressbar {
        margin: 0 auto 20px;
        }

        .sidebar-main #grad1 .col-lg-3,
        .sidebar-main #grad1 .chbs-form-field {
        display: unset !important;
        max-width: 100% !important;
        flex: unset !important;
        }

        .sidebar-main #grad1 .col-lg-3:not(:last-child) {
        margin: 0 auto 10px;
        }

        .sidebar-main #grad1 .chbs-meta {
        padding: 15px;
        }

        .sidebar-main #grad1 .steps-navi input {
        width: 48%;
        }

        .sidebar-main #grad1 .fieldset_1 {
        margin-bottom: 15px;
        }

        /* Focus states for accessibility */
        input:focus, select:focus, button:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 112, 10, 0.2);
        }
    </style>
@endsection

{{-- @section('page-content') --}}
@section('content')
    <!-- MultiStep Form -->
    <div id="grad1">
        <div class="justify-content-center">

            @if( $is_def_mile_or_fees == '1' || $is_def_mile_or_fees == '2' )
                <form id="msform" action="{{ route('shipments.calculator.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <!-- progressbar -->
                    <ul id="progressbar" class="text-center">
                        <li class="active progressbar-account" id="account">{{ __('cargo::view.enter_ride_details') }}</li>
                        <li id="personal">{{ __('cargo::view.personal') }}</li>
                        <li id="payment">{{ __('cargo::view.shipment_details') }}</li>
                    </ul> <!-- fieldsets -->
                    <fieldset>
                        <div class="fieldset_1">
                            <div>
                                <div @if( $is_def_mile_or_fees =='1' && $googleMap ) class="" @else class="" @endif >
                                    <ul class="nav nav-tabs" id="myTab" role="tablist">

                                        @if( $is_def_mile_or_fees =='1')
                                            <li class="nav-item" @if($is_def_mile_or_fees =='1') style="width: 50% !important;" @else style="width: 33.3% !important;" @endif>
                                                <a class="nav-link active" id="distance-tab" data-toggle="tab" href="#distance" role="tab" aria-controls="distance" aria-selected="true">{{ __('cargo::view.distance') }}</a>
                                            </li>
                                        @endif
                                        <li class="nav-item" @if( $is_def_mile_or_fees =='1') style="width: 50% !important;" @else style="width: 100% !important;" @endif>
                                            <a @if( $is_def_mile_or_fees =='1') class="nav-link" aria-selected="false" @else class="nav-link active" aria-selected="true" @endif id="location-tab" data-toggle="tab" href="#location" role="tab" aria-controls="location" >{{ __('cargo::view.location') }}</a>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="myTabContent">
                                        <div @if( $is_def_mile_or_fees =='1') class="tab-pane fade show active" @else class="tab-pane fade" @endif  id="distance" role="tabpanel" aria-labelledby="distance-tab">

                                            <label class="chbs-form-label-group">{{ __('cargo::view.ride_details') }}</label>

                                            <div class="chbs-form-field">
                                                <div class="error" id="error_pickup">{{ __('cargo::view.enter_valid_pickup_location') }}</div>
                                                <label>{{ __('cargo::view.pickup_location') }}</label>
                                                <input class="pickup-location" id="from_places" autocomplete="off" placeholder="{{ __('cargo::view.enter_location') }}"/>
                                                <input id="origin" name="origin" type="hidden"/>
                                            </div>

                                            <div class="chbs-form-field">
                                                <div class="error" id="error_drop_off">{{ __('cargo::view.enter_valid_dropoff_location') }}</div>
                                                <label>{{ __('cargo::view.dropoff_location') }}</label>
                                                <input class="drop-off-location" id="to_places" autocomplete="off" placeholder="{{ __('cargo::view.enter_location') }}"/>
                                                <input id="destination" name="destination" type="hidden"/>
                                            </div>

                                        </div>
                                        <div @if( $is_def_mile_or_fees =='1') class="tab-pane fade" else class="tab-pane fade show active" @endif id="location" role="tabpanel" aria-labelledby="location-tab">
                                            <label class="chbs-form-label-group">{{ __('cargo::view.details') }}</label>
                                            <div class="row" style="display:flex;">
                                                <div class="col-md-6 chbs-form-field" >
                                                    <div class="error" id="error_from_country">{{ __('cargo::view.enter_valid_country') }}</div>
                                                    <label style="padding-left: 4px;">{{ __('cargo::view.from_country') }}:</label>
                                                    <div class="search_categories">
                                                        <div class="select">
                                                            <select id="change-country" name="Shipment[from_country_id]" class="form-select select-country">
                                                                <option value="null">{{ __('cargo::view.select_country') }}</option>
                                                                @foreach($countries as $country)
                                                                    <option value="{{$country->id}}">{{$country->name}}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 chbs-form-field" >
                                                    <div class="error" id="error_to_country">{{ __('cargo::view.enter_valid_country') }}</div>
                                                    <label style="padding-left: 4px;">{{ __('cargo::view.to_country') }}:</label>
                                                    <select id="change-country-to" name="Shipment[to_country_id]" class="select-country">
                                                        <option value="null">{{ __('cargo::view.select_country') }}</option>
                                                        @foreach($countries as $country)
                                                            <option value="{{$country->id}}">{{$country->name}}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row" style="display:flex;">
                                                <div class="col-md-6 chbs-form-field">
                                                    <label style="padding-left: 4px;">{{ __('cargo::view.from_region') }}:</label>
                                                    <select id="change-state-from" name="Shipment[from_state_id]" class="select-country">
                                                        <option>{{ __('cargo::view.select_country_first') }}</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 chbs-form-field">
                                                    <label style="padding-left: 4px;">{{ __('cargo::view.to_region') }}:</label>
                                                    <select id="change-state-to" name="Shipment[to_state_id]" class="select-country">
                                                        <option>{{ __('cargo::view.select_country_first') }}</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row" style="display:flex;">
                                                <div class="col-md-6 chbs-form-field">
                                                    <label style="padding-left: 4px;">{{ __('cargo::view.from_area') }}:</label>
                                                    <select id="change-area-from" name="Shipment[from_area_id]" class="select-country">
                                                        <option>{{ __('cargo::view.select_region_first') }}</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 chbs-form-field">
                                                    <label style="padding-left: 4px;">{{ __('cargo::view.to_area') }}:</label>
                                                    <select id="change-area-to" name="Shipment[to_area_id]" class="select-country">
                                                        <option>{{ __('cargo::view.select_region_first') }}</option>
                                                    </select>
                                                </div>
                                            </div>

                                        </div>

                                    </div>
                                </div>
                                @if( $is_def_mile_or_fees =='1' && $googleMap )
                                    <div class="responsive_map col-md-12 col-lg-6">
                                        <div class="col-sm-12" id="map" style="width:100%;height:100%;"></div>
                                    </div>
                                @endif
                            </div>

                            <div id="result">
                                <div>
                                    <div class="row">
                                        @if( $is_def_mile_or_fees =='1')
                                            <div class="col-sm-12 col-lg-4">
                                                <div class="chbs-meta">
                                                    <span class="chbs-meta-icon-route">
                                                        <i class="fas fa-route"></i>
                                                    </span>
                                                    <div>
                                                        <span class="chbs-meta-title">{{ __('cargo::view.TOTAL_DISTANCE') }}</span>
                                                        <span class="chbs-meta-count" id="in_mile">0 MI</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-sm-12 col-lg-4">
                                                <div class="chbs-meta">
                                                    <span class="chbs-meta-icon-route">
                                                        <i class="far fa-clock"></i>
                                                    </span>
                                                    <div>
                                                        <span class="chbs-meta-title">{{ __('cargo::view.TOTAL_TIME') }}</span>
                                                        <span class="chbs-meta-count" id="duration_text">0 H 0 M</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-sm-12 col-lg-4">
                                                <div class="chbs-meta">
                                                    <span class="chbs-meta-icon-route">
                                                        <i class="fas fa-dollar-sign"></i>
                                                    </span>
                                                    <div>
                                                        <span class="chbs-meta-title">{{ __('cargo::view.TOTAL_PRICE') }}</span>
                                                        <span class="chbs-meta-count" id="price">0{{ current_branch_currency_symbol() }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @elseif( $is_def_mile_or_fees == '2')
                                            <div class="col-sm-12 col-lg-3">
                                                <div class="chbs-meta">
                                                    <span class="chbs-meta-icon-route">
                                                        <i class="fas fa-dollar-sign"></i>
                                                    </span>
                                                    <div>
                                                        <span class="chbs-meta-title">{{ __('cargo::view.SHIPPING_COST') }}</span>
                                                        <span class="chbs-meta-count" id="shipping_cost">{{ current_branch_currency_symbol() }}0</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-sm-12 col-lg-3">
                                                <div class="chbs-meta">
                                                    <span class="chbs-meta-icon-route">
                                                        <i class="fas fa-dollar-sign"></i>
                                                    </span>
                                                    <div>
                                                        <span class="chbs-meta-title">{{ __('cargo::view.TAX_COST') }}</span>
                                                        <span class="chbs-meta-count" id="tax_duty">{{ current_branch_currency_symbol() }}0</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-sm-12 col-lg-3">
                                                <div class="chbs-meta">
                                                    <span class="chbs-meta-icon-route">
                                                        <i class="fas fa-dollar-sign"></i>
                                                    </span>
                                                    <div>
                                                        <span class="chbs-meta-title">{{ __('cargo::view.INSURANCE_COST') }}</span>
                                                        <span class="chbs-meta-count" id="insurance">{{ current_branch_currency_symbol() }}0</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-sm-12 col-lg-3">
                                                <div class="chbs-meta">
                                                    <span class="chbs-meta-icon-route">
                                                        <i class="fas fa-dollar-sign"></i>
                                                    </span>
                                                    <div>
                                                        <span class="chbs-meta-title">{{ __('cargo::view.TOTAL_COST') }}</span>
                                                        <span class="chbs-meta-count" id="total_cost">{{ current_branch_currency_symbol() }}0</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="steps-navi">
                            <input style="opacity: 0.3" type="button" name="previous" class="navi-prev previous action-button-previous" value="{{ __('cargo::view.previous') }}" />
                            <input type="button" name="next" @if( $is_def_mile_or_fees =='2')
                            class="navi-nxt nextStep1_button next action-button" @else
                            class="navi-nxt nextStep1_button action-button" @endif
                            value="{{ __('cargo::view.Next_Step') }}" />
                        </div>

                    </fieldset>

                    <fieldset>
                        <div class="fieldset_2">
                            <div>
                                <div>
                                    <div>
                                        <label class="chbs-form-label-group">{{ __('cargo::view.client_sender_details') }}</label>

                                        <div class="row mb-3">
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <label class=" col-form-label">{{ __('cargo::view.do_you_have_account') }}</label>
                                                </div>
                                            </div>
                                            <div class=" col-md-6 d-flex">
                                                <input style="margin-top: 4px;margin-right: 6px;" type="radio" name="if_have_account" value="1" required />
                                                <label style="margin-right: 15px;" >{{ __('cargo::view.yes') }}</label>

                                                <input style="margin-top: 4px;margin-right: 6px;" type="radio" checked="checked" name="if_have_account" value="0" required />
                                                <label>{{ __('cargo::view.no') }}</label>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="chbs-form-field col-md-6">
                                                <div class="error" id="error_email">{{ __('cargo::view.enter_a_valid_email') }}</div>
                                                <div class="error" id="error_email_no_have_account">{{ __('cargo::view.the_email_is_already_exist') }}</div>
                                                <label>{{ __('cargo::view.table.email') }}</label>
                                                <input id="email" type="email" name="client_email" autocomplete="off" placeholder="{{ __('cargo::view.enter_a_email') }}" required />
                                            </div>
                                            <div class="chbs-form-field col-md-6">
                                                <label>{{ __('cargo::view.table.password') }}</label>
                                                <input id="password" type="password" name="client_password" autocomplete="off" placeholder="{{ __('cargo::view.enter_a_password') }}" required />
                                            </div>
                                        </div>

                                        <div class="row" id="full_name_and_phone">
                                            <div class="chbs-form-field col-md-6">
                                                <label>{{ __('cargo::view.table.full_name') }}</label>
                                                <input type="text" id="name" autocomplete="off" name="client_name" placeholder="{{ __('cargo::view.table.full_name') }}"  />
                                            </div>
                                            <div class="chbs-form-field col-md-6">
                                                <label>{{ __('cargo::view.phone_number') }}</label>
                                                <input type="text" autocomplete="off" name="Shipment[client_phone]" placeholder="{{ __('cargo::view.phone_number') }}"  />
                                            </div>
                                            <div class="chbs-form-field col-md-6">
                                                <label>{{ __('cargo::view.table.owner_national_id') }}</label>
                                                <input type="text" autocomplete="off" name="national_id" placeholder="{{ __('cargo::view.table.owner_national_id') }}"  />
                                            </div>
                                        </div>

                                        <label class="chbs-form-label-group">{{ __('cargo::view.receiver_details') }}</label>
                                        <div class="row">
                                            <div class="chbs-form-field col-md-6">
                                                <label>{{ __('cargo::view.table.full_name') }}</label>
                                                <input type="text" autocomplete="off" name="Shipment[reciver_name]" placeholder="{{ __('cargo::view.table.full_name') }}" required />
                                            </div>


                                            <div class="chbs-form-field col-md-6">
                                                <label>{{ __('cargo::view.phone_number') }}</label>
                                                <input type="text" autocomplete="off" name="Shipment[reciver_phone]" placeholder="{{ __('cargo::view.phone_number') }}" required />
                                            </div>
                                        </div>

                                        <label class="chbs-form-label-group">{{ __('cargo::view.address_details') }}</label>
                                        <div class="row">
                                            <div class="chbs-form-field col-md-6">
                                                <label>{{ __('cargo::view.client_address') }}</label>
                                                <div class="error" id="error_client_address">{{ __('cargo::view.check_number_length') }}</div>
                                                <input type="text" onkeypress="clientAddress()" autocomplete="off" id="client_address" name="client_address" placeholder="{{ __('cargo::view.client_address') }}" required />

                                                @if($googleMap)
                                                    <div class="location-client">
                                                        <label>{{ __('cargo::view.client_location') }}:</label>
                                                        <input type="text" class="address-client " placeholder="{{ __('cargo::view.client_location') }}" name="client_street_address_map"  rel="client" value="" required  />
                                                        <input type="hidden" class="form-control lat" data-client="lat" name="client_lat" required />
                                                        <input type="hidden" class="form-control lng" data-client="lng" name="client_lng" required />
                                                        <input type="hidden" class="form-control url" data-client="url" name="client_url" required />

                                                        <div class="mt-2 col-sm-12 map_canvas map-client" style="width:100%;height:300px;"></div>
                                                        <span class="form-text text-muted">{{'Change the pin to select the right location'}}</span>
                                                    </div>
                                                @endif

                                            </div>
                                            <div class="chbs-form-field col-md-6">
                                                <label>{{ __('cargo::view.receiver_address') }}</label>
                                                <div class="error" id="error_reciver_address"> {{ __('cargo::view.check_number_length') }}</div>
                                                <input type="text" autocomplete="off" onkeypress="reciverAddress()" id="reciver_address" name="Shipment[reciver_address]" placeholder="{{ __('cargo::view.receiver_address') }}" required />

                                                @if($googleMap)
                                                    <div class="location-receiver">
                                                        <label>{{ __('cargo::view.receiver_location') }}:</label>
                                                        <input type="text" class="address-receiver " placeholder="{{ __('cargo::view.receiver_location') }}" name="Shipment[reciver_street_address_map]"  rel="receiver" value="" required />
                                                        <input type="hidden" class="form-control lat" data-receiver="lat" name="Shipment[reciver_lat]"required  />
                                                        <input type="hidden" class="form-control lng" data-receiver="lng" name="Shipment[reciver_lng]" required />
                                                        <input type="hidden" class="form-control url" data-receiver="url" name="Shipment[reciver_url]" required />

                                                        <div class="mt-2 col-sm-12 map_canvas map-receiver" style="width:100%;height:300px;"></div>
                                                        <span class="form-text text-muted">{{'Change the pin to select the right location'}}</span>
                                                    </div>
                                                @endif
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>
                        </div>
                        <div class="steps-navi">
                            <input type="button" name="previous" class="navi-prev previous action-button-previous" value="{{ __('cargo::view.previous') }}" />
                            <input type="button" name="next" class="navi-nxt nextStep2_button action-button" value="{{ __('cargo::view.Next_Step') }}" />
                        </div>

                    </fieldset>

                    <fieldset>
                        <div class="alert alert--warning">
                            <i class="fa fa-triangle-exclamation fa-2xl icon"></i>
                            <div class="content">
                                <div class="title">
                                    {{ __('cargo::view.note_regarding_weight') }}
                                </div>
                                <div class="body">
                                    {{ __('cargo::view.shipping_price_may_vary_depending_on_weight') }}
                                </div>
                            </div>
                        </div>
                        <div class="fieldset_3">
                            <div>
                                <div>
                                    <div>
                                        <label class="chbs-form-label-group">{{ __('cargo::view.shipment_details') }}</label>
                                        <div class="row">
                                            <div @if(Modules\Cargo\Entities\ShipmentSetting::getVal('is_date_required') == '1' || Modules\Cargo\Entities\ShipmentSetting::getVal('is_date_required') == null) class="chbs-form-field col-md-6" @else class="chbs-form-field col-md-12" @endif>
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <div class="form-group">
                                                            <label class=" col-form-label">{{ __('cargo::view.shipment_type') }}</label>
                                                        </div>
                                                    </div>
                                                    <div class=" col-md-12 d-flex">
                                                        <input style="width: 20px !important;margin-right: 6px;" @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_shipment_type')=='1' ) checked @endif type="radio" name="Shipment[type]" checked="checked" value="1" />
                                                        <label style="margin-right: 15px;" >{{ __('cargo::view.Pickup_For_door_to_door_delivery') }}</label>
                                                    </div>
                                                    <div class=" col-md-12 d-flex">
                                                        <input style="width: 20px !important;margin-right: 6px;" @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_shipment_type')=='2' ) checked @endif type="radio" name="Shipment[type]" checked="checked" value="1" />
                                                        <label style="margin-right: 15px;" >{{ __('cargo::view.drop_off_For_delivery_package_from_branch_directly') }}</label>
                                                    </div>
                                                </div>
                                            </div>

                                            <div @if(Modules\Cargo\Entities\ShipmentSetting::getVal('is_date_required') == '1' || Modules\Cargo\Entities\ShipmentSetting::getVal('is_date_required') == null) class="chbs-form-field col-md-12" @endif>
                                                @if(Modules\Cargo\Entities\ShipmentSetting::getVal('is_date_required') == '1' || Modules\Cargo\Entities\ShipmentSetting::getVal('is_date_required') == null)
                                                    <label>{{ __('cargo::view.shipping_date') }}:</label>
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
                                                        <input type="text" placeholder="{{ __('cargo::view.shipping_date') }}" value="{{ $shipping_data->toDateString() }}" name="Shipment[shipping_date]" autocomplete="off" id="kt_datepicker_3" />
                                                    </div>
                                                @endif
                                            </div>

                                            <div  class="chbs-form-field col-md-4">
                                                <label>{{ __('cargo::view.branch') }}:</label>
                                                <div class="search_categories">
                                                    <div class="select">
                                                        <select  name="Shipment[branch_id]" class="form-select">
                                                            @foreach($branches as $branch)
                                                                <option @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_branch')==$branch->id) selected @endif value="{{$branch->id}}">{{$branch->name}}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div  class="chbs-form-field col-md-4">
                                                <label>{{ __('cargo::view.delivery_time') }}:</label>
                                                <div class="search_categories">
                                                    <div class="select">
                                                        <select  name="Shipment[delivery_time]" class="form-select">
                                                            @foreach($deliveryTimes as $deliveryTime)
                                                                <option value="{{$deliveryTime->name}}">{{json_decode($deliveryTime->name, true)[app()->getLocale()]}}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="chbs-form-field col-md-4">
                                                <label>{{ __('cargo::view.amount_to_be_collected') }}:</label>
                                                <input placeholder="{{ __('cargo::view.amount_to_be_collected') }}" type="number" min="0" value="0" name="Shipment[amount_to_be_collected]" />
                                            </div>

                                            <div class="chbs-form-field col-md-4">
                                                <label>{{ __('cargo::view.payment_type') }}:</label>
                                                <select class="kt-select2 payment-type" id="payment_type" name="Shipment[payment_type]">
                                                    <option @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_payment_type')=='1' ) selected @endif value="1">{{ __('cargo::view.postpaid') }}</option>
                                                    <option @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_payment_type')=='2' ) selected @endif value="2">{{ __('cargo::view.prepaid') }}</option>
                                                </select>
                                            </div>

                                            <div class="chbs-form-field col-md-4">
                                                <label>{{ __('cargo::view.payment_method') }}:</label>
                                                <select class="kt-select2 payment-method" id="payment_method_id" name="Shipment[payment_method_id]">
                                                    @foreach ($paymentSettings as $key => $gateway){
                                                    @if($gateway)
                                                        <option value="{{$key}}"
                                                                @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_payment_method') == $key) selected @endif
                                                            {{ old('Shipment.payment_method_id') == $key ? 'selected' : '' }}
                                                        >{{$key}}</option>
                                                    @endif
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="chbs-form-field col-md-4">
                                                <label>{{ __('cargo::view.order_id') }}:</label>
                                                <input placeholder="{{ __('cargo::view.order_id') }}" type="text" name="Shipment[order_id]" />
                                            </div>

                                        </div>

                                        <label class="chbs-form-label-group">{{ __('cargo::view.package_info') }}</label>

                                        <div data-repeater-item class="row" style="margin-top: 15px;padding-bottom: 15px;padding-top: 15px;">

                                            <div class="col-md-6 chbs-form-field">
                                                <label>{{ __('cargo::view.package_type') }}:</label>
                                                <div class="select">
                                                    <select id="package_type_id" class="package-type-select" name="Package[0][package_id]">
                                                        @foreach($packages as $package)
                                                            <option @if(Modules\Cargo\Entities\ShipmentSetting::getVal('def_package_type')==$package->id) selected @endif value="{{$package->id}}">{{ json_decode($package->name, true)[app()->getLocale()] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-2 d-md-none"></div>
                                            </div>

                                            <div class="col-md-6 chbs-form-field">
                                                <label>{{ __('cargo::view.description') }}:</label>
                                                <input type="text" placeholder="{{ __('cargo::view.description') }}" name="Package[0][description]">
                                                <div class="mb-2 d-md-none"></div>
                                            </div>
                                            <div class="col-md-3 chbs-form-field" style="border-right: 1px solid #eaecee;">
                                                <label>{{ __('cargo::view.quantity') }}:</label>
                                                <input class="kt_touchspin_qty" placeholder="{{ __('cargo::view.quantity') }}" type="number" min="1" name="Package[0][qty]" class="form-control" value="1" />
                                                <div class="mb-2 d-md-none"></div>
                                            </div>

                                            <div class="col-md-2 chbs-form-field" style="border-right: 1px solid #eaecee;">
                                                <label>{{ __('cargo::view.weight') }}:</label>
                                                <input type="number" id="package_total_weight" min="1" placeholder="{{ __('cargo::view.weight') }}" name="Package[0][weight]" class="weight-listener kt_touchspin_weight" onchange="calcTotalWeight()" value="1" />
                                                <div class="mb-2 d-md-none"></div>
                                            </div>

                                            <div class="col-md-2 chbs-form-field" style="border-right: 1px solid #eaecee;">
                                                <label>{{ __('cargo::view.length') }}</label>
                                                <input class="dimensions_r" type="number" min="1" class="form-control" placeholder="{{ __('cargo::view.length') }}" name="Package[0][length]" value="1" />

                                            </div>
                                            <div class="col-md-2 chbs-form-field" style="border-right: 1px solid #eaecee;">
                                                <label>{{ __('cargo::view.width') }}:</label>
                                                <input class="dimensions_r" type="number" min="1" class="form-control" placeholder="{{ __('cargo::view.width') }}" name="Package[0][width]" value="1" />

                                            </div>
                                            <div class="col-md-3 chbs-form-field">
                                                <label>{{ __('cargo::view.height') }}:</label>
                                                <input class="dimensions_r" type="number" min="1" class="form-control " placeholder="{{ __('cargo::view.height') }}" name="Package[0][height]" value="1" />

                                            </div>

                                        </div>

                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="steps-navi">
                            <input type="button" name="previous" class="navi-prev previous action-button-previous" value="{{ __('cargo::view.previous') }}" />
                            <input type="submit" id="submit" name="make_payment" class="btnclicky next action-button" value="Confirm" />
                        </div>
                    </fieldset>

                    <fieldset>
                        <div class="form-card">
                            <h2 class="text-center fs-title">Success !</h2> <br><br>
                            <div class="row justify-content-center">
                                <div class="col-3"> <img src="https://img.icons8.com/color/96/000000/ok--v2.png" class="fit-image"> </div>
                            </div> <br><br>
                            <div class="row justify-content-center">
                                <div class="text-center col-7">
                                    <h5>{{__('cargo::messages.created')}}</h5>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            @endif

        </div>
    </div>
@endsection

@section('scripts')
    <script type="text/javascript" src="{{ asset('themes/html/assets/js/jquery.slim.min.js') }}"  ></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/js/bootstrap.bundle.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
    @if(isset($google_map_key) && $google_map_key != '')
        <script src="{{ asset('assets/global/js/jquery.geocomplete.js') }}"></script>
        <script src="//maps.googleapis.com/maps/api/js?libraries=places&key={{$google_map_key}}"></script>
        <script>
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
        </script>
    @endif
    <script>

        function validateInput(id)
        {
            $("#"+id).fadeIn(300);
            setTimeout(function(){
                $("#"+id).fadeOut(300);
            }, 5000);
        }

        $("#submit").click(function(){
            $("#msform").submit(); // Submit the form
        });

        // Step 2
        $('input[type=radio][name=if_have_account]').change(function() {
            if (this.value == '1') {
                $("#full_name_and_phone").css("display", "none");
            }
            else if (this.value == '0') {
                $("#full_name_and_phone").css("display", "flex");
            }
        });

        $(".nextStep2_button").click(function(){
            var if_have_account = $('input[type=radio][name=if_have_account]:checked').val();
            var email = $("#email").val();
            var type = 'Client';
            $.get("{{route('user.checkEmail')}}?type="+type+"&Client[email]="+email+"&calc=true", function(data) {
                data = JSON.parse(data);
                if (if_have_account == '1') {
                    if (data.valid) {
                        $('#email').focus()
                        validateInput('error_email');
                    }
                    else{
                        $(".nextStep2_button").addClass("next");
                        nextStep();
                    }
                }
                else if (if_have_account == '0') {
                    if (data.valid) {
                        $(".nextStep2_button").addClass("next");
                        nextStep();
                    }
                    else{
                        $('#email').focus()
                        validateInput('error_email_no_have_account');
                    }
                }
            });
        });

        $(document).ready(function(){
            var current_fs, next_fs, previous_fs; //fieldsets
            var opacity;
            nextStep();
            $(".previous").click(function(){
                current_fs = $(this).parent().parent();
                previous_fs = $(this).parent().parent().prev();
                //Remove class active
                $("#progressbar li").eq($("fieldset").index(current_fs)).removeClass("active");
                //show the previous fieldset
                previous_fs.show();
                //hide the current fieldset with style
                current_fs.animate({opacity: 0}, {
                    step: function(now) {
                        // for making fielset appear animation
                        opacity = 1 - now;
                        current_fs.css({
                            'display': 'none',
                            'position': 'relative'
                        });
                        previous_fs.css({'opacity': opacity});
                    },
                    duration: 600
                });
            });
            $('.radio-group .radio').click(function(){
                $(this).parent().find('.radio').removeClass('selected');
                $(this).addClass('selected');
            });
            $(".submit").click(function(){
                return false;
            })
        });

        $('#kt_datepicker_3').datepicker({
            orientation: "bottom auto",
            autoclose: true,
            format: 'yyyy-mm-dd',
            todayBtn: true,
            todayHighlight: true,
            startDate: new Date(),
        });

        // Step 1
        $(".nextStep1_button").click(function(){
            if( {{$is_def_mile_or_fees}} =='1')
            {
                if($("#location-tab").hasClass("active")){
                    nextStep();
                }else{
                    $("#distance-tab").removeClass("active");
                    $("#distance").removeClass("active show");
                    $("#location-tab").addClass("active");
                    $("#location").addClass("active show");
                }
                $(".nextStep1_button").addClass("next");
            }else{
                nextStep();
            }

        });

        $(".previousStep1_button").click(function(){
            if( {{$is_def_mile_or_fees}} =='1')
            {
                if($("#location-tab").hasClass("active")){

                    $("#location-tab").removeClass("active");
                    $("#location").removeClass("active show");
                    $("#distance-tab").addClass("active");
                    $("#distance").addClass("active show");
                }

            }
        });


        $('#change-country').change(function() {
            let id = $(this).val();
            $.get("{{route('ajax.getStates')}}?country_id=" + id, function(data) {
                $('select[name ="Shipment[from_state_id]"]').empty();
                $('select[name ="Shipment[from_state_id]"]').append('<option>Select Region</option>');
                for (let index = 0; index < data.length; index++) {
                    const element = data[index];
                    $('select[name ="Shipment[from_state_id]"]').append('<option value="' + element['id'] + '">' + element['name'] + '</option>');
                }
            });

            let country_to_value = $('#change-country-to').val();
            if( country_to_value == 'null' ){
                validateInput('error_to_country');
            }else{
                if( {{$is_def_mile_or_fees}} =='1'){
                    calc_waiting_meil();
                    setTimeout(function(){ ajaxShipmentCost() }, 500);
                }else if( {{$is_def_mile_or_fees}} =='2'){
                    calc_waiting_fees();
                    setTimeout(function(){ ajaxShipmentCost() }, 500);
                }

            }
        });

        $('#change-country-to').change(function() {
            let id = $(this).val();
            $.get("{{route('ajax.getStates')}}?country_id=" + id, function(data) {
                $('select[name ="Shipment[to_state_id]"]').empty();
                $('select[name ="Shipment[to_state_id]"]').append('<option>Select Region</option>');
                for (let index = 0; index < data.length; index++) {
                    const element = data[index];
                    $('select[name ="Shipment[to_state_id]"]').append('<option value="' + element['id'] + '">' + element['name'] + '</option>');
                }
            });

            let country_from_value = $('#change-country').val();
            if( country_from_value == 'null' ){
                validateInput('error_from_country');
            }else{
                if( {{$is_def_mile_or_fees}} =='1'){
                    calc_waiting_meil();
                    setTimeout(function(){ ajaxShipmentCost() }, 500);
                }else if( {{$is_def_mile_or_fees}} =='2'){
                    calc_waiting_fees();
                    setTimeout(function(){ ajaxShipmentCost() }, 500);
                }
            }
        });

        function clientAddress() {
            var client_address = $('#client_address').val().length;

            if( client_address < 8 ){
                validateInput('error_client_address');
            }
        }

        function reciverAddress() {
            var reciver_address = $('#reciver_address').val().length;

            if( reciver_address < 8 ){
                validateInput('error_reciver_address');
            }
        }

        $('#change-state-from').change(function() {
            let id = $(this).val();
            $.get("{{route('ajax.getAreas')}}?state_id=" + id, function(data) {
                $('select[name ="Shipment[from_area_id]"]').empty();
                $('select[name ="Shipment[from_area_id]"]').append('<option>Select Area</option>');
                for (let index = 0; index < data.length; index++) {
                    const element = data[index];
                    $('select[name ="Shipment[from_area_id]"]').append('<option value="' + element['id'] + '">' + JSON.parse(element['name'], true)[`{{app()->getLocale()}}`] + '</option>');
                }
            });

            if( {{$is_def_mile_or_fees}} =='1'){
                calc_waiting_meil();
                setTimeout(function(){ ajaxShipmentCost() }, 500);
            }else if( {{$is_def_mile_or_fees}} =='2'){

                calc_waiting_fees();
                setTimeout(function(){ ajaxShipmentCost() }, 500);
            }
        });

        $('#change-state-to').change(function() {
            let id = $(this).val();
            $.get("{{route('ajax.getAreas')}}?state_id=" + id, function(data) {
                $('select[name ="Shipment[to_area_id]"]').empty();
                $('select[name ="Shipment[to_area_id]"]').append('<option>Select Area</option>');
                for (let index = 0; index < data.length; index++) {
                    const element = data[index];
                    $('select[name ="Shipment[to_area_id]"]').append('<option value="' + element['id'] + '">' + JSON.parse(element['name'], true)[`{{app()->getLocale()}}`] + '</option>');
                }
            });

            if( {{$is_def_mile_or_fees}} =='1'){
                calc_waiting_meil();
                setTimeout(function(){ ajaxShipmentCost() }, 500);
            }else if( {{$is_def_mile_or_fees}} =='2'){
                calc_waiting_fees();
                setTimeout(function(){ ajaxShipmentCost() }, 500);
            }
        });

        $('#change-area-from').change(function(){
            if( {{$is_def_mile_or_fees}} =='1'){
                calc_waiting_meil();
                setTimeout(function(){ ajaxShipmentCost() }, 500);
            }else if( {{$is_def_mile_or_fees}} =='2'){

                calc_waiting_fees();
                setTimeout(function(){ ajaxShipmentCost() }, 500);
            }
        });
        $('#change-area-to').change(function(){
            if( {{$is_def_mile_or_fees}} =='1'){
                calc_waiting_meil();
                setTimeout(function(){ ajaxShipmentCost() }, 500);
            }else if( {{$is_def_mile_or_fees}} =='2'){
                calc_waiting_fees();
                setTimeout(function(){ ajaxShipmentCost() }, 500);
            }
        });

        // End Step 1

        function nextStep() {
        $(".next").click(function() {
            var current_fs = $(this).parent().parent();
            var next_fs = $(this).parent().parent().next();

            var isValid = true;
            current_fs.find('input[required], select[required], textarea[required]').each(function() {
                if ($(this).val() === "") {
                    isValid = false;
                    $(this).css('border', '2px solid red');
                }
            });

        if (!isValid) {
            alert("Please fill all the required fields in this section.");
            return;
        }

                //Add Class Active
                $("#progressbar li").eq($("fieldset").index(next_fs)).addClass("active");
                //show the next fieldset
                next_fs.show();
                //hide the current fieldset with style
                current_fs.animate({opacity: 0}, {
                    step: function(now) {
                        // for making fielset appear animation
                        opacity = 1 - now;
                        current_fs.css({
                            'display': 'none',
                            'position': 'relative'
                        });
                        next_fs.css({'opacity': opacity});
                    },
                    duration: 600
                });
            });
        }

            var origin, destination, map;
            // add input listeners
            google.maps.event.addDomListener(window, 'load', function (listener) {
                setDestination();
                initMap();
            });
            // init or load map
            function initMap() {
                var myLatLng = {
                    lat: 52.520008,
                    lng: 13.404954
                };
                map = new google.maps.Map(document.getElementById('map'), {zoom: 16, center: myLatLng,});
            }
            function setDestination() {
                var from_places = new google.maps.places.Autocomplete(document.getElementById('from_places'));
                var to_places = new google.maps.places.Autocomplete(document.getElementById('to_places'));
                google.maps.event.addListener(from_places, 'place_changed', function () {
                    var from_place = from_places.getPlace();
                    var from_address = from_place.formatted_address;
                    $('#origin').val(from_address);
                });
                google.maps.event.addListener(to_places, 'place_changed', function () {
                    var to_place = to_places.getPlace();
                    var to_address = to_place.formatted_address;
                    $('#destination').val(to_address);
                });
            }
            function displayRoute(travel_mode, origin, destination, directionsService, directionsDisplay) {
                directionsService.route({
                    origin: origin,
                    destination: destination,
                    travelMode: travel_mode,
                    avoidTolls: true
                }, function (response, status) {
                    if (status === 'OK') {
                        directionsDisplay.setMap(map);
                        directionsDisplay.setDirections(response);
                    } else {
                        directionsDisplay.setMap(null);
                        directionsDisplay.setDirections(null);
                        alert('Could not display directions due to: ' + status);
                    }
                });
            }
            // calculate distance , after finish send result to callback function
            function calculateDistance(travel_mode, origin, destination) {
                var DistanceMatrixService = new google.maps.DistanceMatrixService();
                DistanceMatrixService.getDistanceMatrix(
                    {
                        origins: [origin],
                        destinations: [destination],
                        travelMode: google.maps.TravelMode[travel_mode],
                        unitSystem: google.maps.UnitSystem.IMPERIAL, // miles and feet.
                        // unitSystem: google.maps.UnitSystem.metric, // kilometers and meters.
                        avoidHighways: false,
                        avoidTolls: false
                    }, save_results);
            }
            // save distance results
            function save_results(response, status) {
                if (status != google.maps.DistanceMatrixStatus.OK) {
                    $('#result').html(err);
                } else {
                    var origin = response.originAddresses[0];
                    var destination = response.destinationAddresses[0];
                    if (response.rows[0].elements[0].status === "ZERO_RESULTS") {
                        $('#result').html("Sorry , not available to use this travel mode between " + origin + " and " + destination);
                    } else {
                        var distance = response.rows[0].elements[0].distance;
                        var duration = response.rows[0].elements[0].duration;
                        var distance_in_kilo = distance.value / 1000; // the kilo meter
                        var distance_in_mile = distance.value / 1609.34; // the mile
                        var duration_text = duration.text;
                        appendResults(distance_in_kilo, distance_in_mile, duration_text);
                    }
                }
            }
            // append html results
            function appendResults(distance_in_kilo, distance_in_mile, duration_text) {
                var price = {{$mile_price}} ;
                var tota_price = distance_in_mile.toFixed(2) * price ;
                $('#in_mile').html(distance_in_mile.toFixed(2) + " MI");
                $('#price').html(tota_price+"{{ current_branch_currency_symbol() }}");
                $('#duration_text').html(duration_text);
            }
            // on submit  display route ,append results and send calculateDistance to ajax request
            function finishCalculate () {
                if( {{$is_def_mile_or_fees}} =='1')
                {
                    var origin = $('#origin').val();
                    var destination = $('#destination').val();
                    var travel_mode = 'DRIVING';
                    var directionsDisplay = new google.maps.DirectionsRenderer({'draggable': false});
                    var directionsService = new google.maps.DirectionsService();
                    displayRoute(travel_mode, origin, destination, directionsService, directionsDisplay);
                    calculateDistance(travel_mode, origin, destination);
                }else if( {{$is_def_mile_or_fees}} =='2'){
                    ajaxShipmentCost()
                }
            };
            function ajaxShipmentCost()
            {
                var package_ids = [];
                package_ids[0] = new Object();
                package_ids[0]["package_id"] = document.getElementById('package_type_id').value;
                var total_weight    = document.getElementById('package_total_weight').value;
                var from_country_id = document.getElementsByName("Shipment[from_country_id]")[0].value;
                var to_country_id   = document.getElementsByName("Shipment[to_country_id]")[0].value;
                var from_state_id   = document.getElementsByName("Shipment[from_state_id]")[0].value;
                var to_state_id     = document.getElementsByName("Shipment[to_state_id]")[0].value;
                var from_area_id   = document.getElementsByName("Shipment[from_area_id]")[0].value;
                var to_area_id     = document.getElementsByName("Shipment[to_area_id]")[0].value;
                var request_data = { _token : '{{ csrf_token() }}',
                    package_ids     : package_ids,
                    total_weight    : total_weight,
                    from_country_id : from_country_id,
                    to_country_id   : to_country_id,
                    from_state_id   : from_state_id,
                    to_state_id     : to_state_id,
                    from_area_id    : from_area_id,
                    to_area_id      : to_area_id,
                };
                $.post('{{ route('shipments.get-estimation-cost') }}', request_data, function(response){
                    if( {{$is_def_mile_or_fees}} =='1'){
                        document.getElementById("price").innerHTML = response.total_cost;
                    }else if( {{$is_def_mile_or_fees}} =='2'){
                        document.getElementById("shipping_cost").innerHTML = response.shipping_cost;
                        document.getElementById("tax_duty").innerHTML = response.tax;
                        document.getElementById("insurance").innerHTML = response.insurance;
                        document.getElementById("total_cost").innerHTML = response.total_cost;
                    }

                });
            }
            $('#to_places').change(function(){
                var from_places_value = $('#from_places').val();
                if( from_places_value == "" || from_places_value == null ){
                    validateInput('error_pickup');
                }else{
                    calc_waiting_meil();
                    setTimeout(function(){ finishCalculate() }, 500);
                }

            });
            $('#from_places').change(function(){
                var to_places_value = $('#to_places').val();
                if( to_places_value == "" || to_places_value == null ){
                    validateInput('error_drop_off');
                }else{
                    calc_waiting_meil();
                    setTimeout(function(){ finishCalculate() }, 500);
                }

            });

            $('#package_id').change(function(){
                calc_waiting();
                setTimeout(function(){ finishCalculate() }, 500);
            });
            $('#total_weight').keyup(function(){
                var country_to_value = $('#change-country-to').val();
                var country_from_value = $('#change-country').val();
                if( country_to_value != 'null' && country_from_value != 'null' ){
                    calc_waiting();
                    var total_weight_value = $('#total_weight').val();
                    if(total_weight_value == 0 || total_weight_value < 0 || total_weight_value > 10000000 || total_weight_value == "" ){
                        validateInput('error_weight');
                    }else{
                        setTimeout(function(){ finishCalculate() }, 500);
                    }
                }else{
                    validateInput('error_weight_country');
                }
            });

            function calc_waiting_fees()
            {
                document.getElementById("shipping_cost").innerHTML = "{{ __('cargo::view.calculating') }}";
                document.getElementById("tax_duty").innerHTML      = "{{ __('cargo::view.calculating') }}";
                document.getElementById("insurance").innerHTML     = "{{ __('cargo::view.calculating') }}";
                document.getElementById("total_cost").innerHTML    = "{{ __('cargo::view.calculating') }}";
            }
            function calc_waiting_meil()
            {
                $('#in_mile').html("{{ __('cargo::view.calculating') }}");
                $('#price').html("{{ __('cargo::view.calculating') }}");
                $('#duration_text').html("{{ __('cargo::view.calculating') }}");
            }
    </script>
@endsection
