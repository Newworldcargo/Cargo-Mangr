<?php 
use \Milon\Barcode\DNS1D;
$d = new DNS1D();
?>

@php
    if (check_module('Localization')) {
        $current_lang = Modules\Localization\Entities\Language::where('code', LaravelLocalization::getCurrentLocale())->first();
    }
@endphp
<!DOCTYPE html>
@if(isset($current_lang) && $current_lang->dir == 'rtl')
<html lang="{{LaravelLocalization::getCurrentLocale()}}" direction="rtl" dir="rtl" style="direction: rtl;">
@else
<html lang="{{LaravelLocalization::getCurrentLocale()}}">
@endif
    <head>
        <title>{{ config('app.name') . ' | ' . ($pageTitle ?? 'Payment') }}</title>
        <meta name="description" content="Algoriza - Framework" />
        <meta name="keywords" content="Algoriza - Framework" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta charset="utf-8" />
        <meta property="og:locale" content="en_US" />
        <meta property="og:type" content="article" />
        <meta property="og:title" content="{{ config('app.name') }}" />
		@php 
            $model = App\Models\Settings::where('group', 'general')->where('name','system_logo')->first();
        @endphp
        <link rel="shortcut icon" href="{{ $model->getFirstMediaUrl('system_logo') ? $model->getFirstMediaUrl('system_logo') : asset('assets/lte/media/logos/favicon.png') }}" />

        <!-- Google Font: Poppins -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">

        <!--begin::Global Stylesheets Bundle(used by all pages)-->
		<link href="{{ asset('assets/lte/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
        <!--end::Global Stylesheets Bundle-->
        
        <!-- Select2 -->
        <link rel="stylesheet" href="{{ asset('assets/lte') }}/plugins/select2/css/select2.min.css">
        <!-- Theme style -->
        <link rel="stylesheet" href="{{ asset('assets/lte') }}/css/adminlte.css">
        @if(isset($current_lang) && $current_lang->dir == 'rtl')
            <link rel="stylesheet" href="{{ asset('assets/lte') }}/css/rtl.css">
        @else
            <link rel="stylesheet" href="{{ asset('assets/lte') }}/css/ltr.css">
        @endif

        <!-- Datatable style -->
        <link rel="stylesheet" href="{{ asset('assets/lte') }}/css/datatable.min.css">
        <!-- overlayScrollbars -->
        <link rel="stylesheet" href="{{ asset('assets/lte') }}/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
        <!-- Daterange picker -->
        <link rel="stylesheet" href="{{ asset('assets/lte') }}/plugins/daterangepicker/daterangepicker.css">
        <!-- summernote -->
        <link rel="stylesheet" href="{{ asset('assets/lte') }}/plugins/summernote/summernote-bs4.min.css">
        <!-- Bootstrap4 Duallistbox -->
        <link rel="stylesheet" href="{{ asset('assets/lte') }}/plugins/bootstrap4-duallistbox/bootstrap-duallistbox.min.css">
        <!-- BS Stepper -->
        <link rel="stylesheet" href="{{ asset('assets/lte') }}/plugins/bs-stepper/css/bs-stepper.min.css">
        <!-- dropzonejs -->
        <link rel="stylesheet" href="{{ asset('assets/lte') }}/plugins/dropzone/min/dropzone.min.css">
        <!-- flag-icon-css -->
        <link rel="stylesheet" href="{{ asset('assets/lte') }}/plugins/flag-icon-css/css/flag-icon.min.css">
        
        <!--begin::Custom Stylesheets-->
		<link href="{{ asset('assets/global/css/app.css') }}" rel="stylesheet" type="text/css" />
		<link href="{{ asset('assets/custom/css/custom.css') }}" rel="stylesheet" type="text/css" />
		<!--end::Custom Stylesheets-->

        <script>
			var hostUrl = "assets/";
			window._csrf_token = '{!! csrf_token() !!}'
		</script>
		<!--begin::Javascript-->
        <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
		<!--begin::Global Javascript Bundle(used by all pages)-->
		<script src="{{ asset('assets/lte/plugins/global/plugins.bundle.js') }}"></script>
		<script src="{{ asset('assets/lte/js/scripts.bundle.js') }}"></script>
		<!--end::Global Javascript Bundle-->

        <livewire:styles />
        <livewire:scripts />
        @yield('styles')
        <!-- signature pad -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/1.5.3/signature_pad.min.js"></script>
    </head>
    <body id="kt_body" class="header-fixed header-mobile-fixed aside-enabled aside-fixed aside-minimize-hoverable page-loading">

        <div class="px-8 py-8 row justify-content-center py-md-27 px-md-0">
            <div class="col-md-9">
                <div class="pb-10 d-flex justify-content-between pb-md-20 flex-column flex-md-row">

                    <h1 class="mb-10 display-4 font-weight-boldest">
                        @php 
                            $system_logo = App\Models\Settings::where('group', 'general')->where('name','system_logo')->first();
                        @endphp
                        <img alt="Logo" src="{{  $system_logo->getFirstMediaUrl('system_logo') ? $system_logo->getFirstMediaUrl('system_logo') : asset('assets/lte/cargo-logo.svg') }}" class="logo" style="max-height: 90px;" />
                    </h1>
                    <!--end::Logo-->

                    <!--begin::Engage Widget 14-->
                    <div class="row mb-17">
                        <div class="col-xxl-5 mb-11 mb-xxl-0">
                            <!--begin::Image-->
                            <div class="p-0 px-10 mr-5 py-15 d-flex align-items-center justify-content-center" style="background-color: #FFCC69;">
                                <h1>D {{$shipment->code}}</h1>
                            </div>
                            <!--end::Image-->
                        </div>
                        <div class="col-xxl-7 pl-xxl-11">
                            <h2 class="font-weight-bolder text-dark mb-7" style="font-size: 32px;">{{ __('cargo::view.client_sender') }}: {{$shipment->client->name}}</h2>
                            <div class="font-size-h2 mb-7 text-dark-50">{{__('cargo::view.to_receiver')}}
                                <span class="ml-2 text-info font-weight-boldest">{{$shipment->reciver_name}}</span>
                            </div>
                            @if($shipment->barcode != null)
                            <?=$d->getBarcodeHTML(str_replace(Modules\Cargo\Entities\ShipmentSetting::getVal('shipment_code_prefix'),"",$shipment->code), "C128");?>
                            @endif
                        </div>
                    </div>
                    <!--end::Engage Widget 14-->

                    <!--begin::Engage Widget 14-->
                    <div class="row mb-17 card card-custom card-stretch gutter-b">
                        <h1>{{__('cargo::view.payment_gateway')}}: {{$shipment->payment_method_id}}</h1>
                        <div class="d-flex justify-content-between">
                            <form action="{{ route('payment.checkout') }}" class="form-default" role="form" method="POST" id="checkout-form">
                                @csrf
                                <input type="hidden" name="shipment_id" value="{{$shipment->id}}">
                                <button type="submit" class="btnclicky mr-3 btn btn-success btn-md">{{__('cargo::view.pay_now')}} <i class="ml-2 far fa-credit-card"></i></button>
                            </form>
                        </div>
                    </div>
                    <!--end::Engage Widget 14-->
                    
                    <div class="row mb-17 card card-custom card-stretch card-stretch-half gutter-b"  >
                        <!--begin::Header-->
                        <div class="mb-2 border-0 card-header">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="mb-3 card-label font-weight-bold font-size-h4 text-dark-75">{{__('cargo::view.package_info')}}</span>
                                <span class="text-muted font-weight-bold font-size-sm">{{Modules\Cargo\Entities\PackageShipment::where('shipment_id',$shipment->id)->count()}} {{__('cargo::view.packages')}}</span>
                            </h3>
                        </div>
                        <!--end::Header-->
                        <!--begin::Body-->
                        <div class="pt-2 card-body">
                            <!--begin::Table-->
                            <div class="table-responsive">
                                <div class='text-right font-size-sm font-weight-bold'>{{__('cargo::view.Weight_length_width_height_')}}</div>
                                <table class="table mb-0 table-borderless">
                                    <tbody>

                                        @foreach(Modules\Cargo\Entities\PackageShipment::where('shipment_id',$shipment->id)->get() as $package)
                                        <tr>
                                            <td class="pb-6 pl-0 pr-2 align-middle w-40px">
                                                <!--begin::Symbol-->
                                                <div class="symbol symbol-40 symbol-light-success">
                                                    <span class="symbol-label">
                                                        <span class="svg-icon svg-icon-lg svg-icon-success">
                                                            <!--begin::Svg Icon | path:assets/media/svg/icons/Shopping/Cart3.svg-->
                                                            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="24px" height="24px" viewBox="0 0 24 24" version="1.1">
                                                                <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                                                    <rect x="0" y="0" width="24" height="24" />
                                                                    <path d="M12,4.56204994 L7.76822128,9.6401844 C7.4146572,10.0644613 6.7840925,10.1217854 6.3598156,9.76822128 C5.9355387,9.4146572 5.87821464,8.7840925 6.23177872,8.3598156 L11.2317787,2.3598156 C11.6315738,1.88006147 12.3684262,1.88006147 12.7682213,2.3598156 L17.7682213,8.3598156 C18.1217854,8.7840925 18.0644613,9.4146572 17.6401844,9.76822128 C17.2159075,10.1217854 16.5853428,10.0644613 16.2317787,9.6401844 L12,4.56204994 Z" fill="#000000" fill-rule="nonzero" opacity="0.3" />
                                                                    <path d="M3.5,9 L20.5,9 C21.0522847,9 21.5,9.44771525 21.5,10 C21.5,10.132026 21.4738562,10.2627452 21.4230769,10.3846154 L17.7692308,19.1538462 C17.3034221,20.271787 16.2111026,21 15,21 L9,21 C7.78889745,21 6.6965779,20.271787 6.23076923,19.1538462 L2.57692308,10.3846154 C2.36450587,9.87481408 2.60558331,9.28934029 3.11538462,9.07692308 C3.23725479,9.02614384 3.36797398,9 3.5,9 Z M12,17 C13.1045695,17 14,16.1045695 14,15 C14,13.8954305 13.1045695,13 12,13 C10.8954305,13 10,13.8954305 10,15 C10,16.1045695 10.8954305,17 12,17 Z" fill="#000000" />
                                                                </g>
                                                            </svg>
                                                            <!--end::Svg Icon-->
                                                        </span>
                                                    </span>
                                                </div>
                                                <!--end::Symbol-->
                                            </td>
                                            <td class="pb-6 align-middle font-size-lg font-weight-bolder text-dark-75 w-100px">{{$package->description}}</td>

                                            <td class="pb-6 text-right align-middle font-weight-bold text-muted">{{ __('cargo::view.type') }}: @if(isset($package->package->name)){{json_decode($package->package->name, true)[app()->getLocale()]}}@endif</td>
                                            <td class="pb-6 text-right align-middle font-weight-bold text-muted">{{ __('cargo::view.weight') }}: {{$package->weight}}</td>
                                            <td class="pb-6 text-right align-middle font-weight-bolder font-size-lg text-dark-75">{{$package->length."x".$package->width."x".$package->height}} <br> </td>
                                        </tr>
                                        @endforeach

                                    </tbody>
                                </table>
                            </div>
                            <!--end::Table-->
                            <div class="mb-8 d-flex flex-column">
                                <span class="mb-4 text-dark font-weight-bold">{{ __('cargo::view.total_cost') }}</span>
                                <span class="text-muted font-weight-bolder font-size-lg">{{ format_shipment_price($shipment->tax + $shipment->shipping_cost + $shipment->insurance, $shipment) }}</span>
                                <span class="text-muted font-weight-bolder font-size-lg">{{ __('cargo::view.included_tax_insurance') }}</span>
                            </div>
                        </div>
                        <!--end::Body-->
                    </div>

                </div>
            </div>
        </div>
        <!-- jQuery -->
        <script src="{{ asset('assets/lte') }}/plugins/jquery/jquery.min.js"></script>
        <!-- jQuery UI 1.11.4 -->
        <script src="{{ asset('assets/lte') }}/plugins/jquery-ui/jquery-ui.min.js"></script>
        <!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
        <script>
            $.widget.bridge('uibutton', $.ui.button)
        </script>
        <!-- Bootstrap 4 -->
        <script src="{{ asset('assets/lte') }}/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
        <!-- ChartJS -->
        <script src="{{ asset('assets/lte') }}/plugins/chart.js/Chart.min.js"></script>
        <!-- Sparkline -->
        <script src="{{ asset('assets/lte') }}/plugins/sparklines/sparkline.js"></script>
        <!-- JQVMap -->
        <script src="{{ asset('assets/lte') }}/plugins/jqvmap/jquery.vmap.min.js"></script>
        <script src="{{ asset('assets/lte') }}/plugins/jqvmap/maps/jquery.vmap.usa.js"></script>
        <!-- jQuery Knob Chart -->
        <script src="{{ asset('assets/lte') }}/plugins/jquery-knob/jquery.knob.min.js"></script>
        <!-- daterangepicker -->
        <script src="{{ asset('assets/lte') }}/plugins/moment/moment.min.js"></script>
        <script src="{{ asset('assets/lte') }}/plugins/daterangepicker/daterangepicker.js"></script>
        <!-- Tempusdominus Bootstrap 4 -->
        <script src="{{ asset('assets/lte') }}/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
        <!-- Summernote -->
        <script src="{{ asset('assets/lte') }}/plugins/summernote/summernote-bs4.min.js"></script>
        <!-- overlayScrollbars -->
        <script src="{{ asset('assets/lte') }}/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
        <!-- bs-custom-file-input -->
        <script src="{{ asset('assets/lte') }}/plugins/bs-custom-file-input/bs-custom-file-input.min.js"></script>
        <!-- AdminLTE App -->
        <script src="{{ asset('assets/lte') }}/js/adminlte.js"></script>
        <!-- Select2 -->
        <script src="{{ asset('assets/lte') }}/plugins/select2/js/select2.full.min.js"></script>
        <!--begin::Custom javascript-->
		<script src="{{ asset('assets/global/js/app.js') }}"></script>
		<script src="{{ asset('assets/custom/js/custom.js') }}"></script>
		<!--end::Custom javascript-->

        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.repeater/1.2.1/jquery.repeater.min.js" integrity="sha512-foIijUdV0fR0Zew7vmw98E6mOWd9gkGWQBWaoA1EOFAx+pY+N8FmmtIYAVj64R98KeD2wzZh1aHK0JSpKmRH8w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

        <script>
            $(function () {
            bsCustomFileInput.init();
            });
        </script>
		
		{{-- Show message alert from session flash --}}
		@include('adminLte.helpers.message-alert')
		<!--end::Javascript-->

        @yield('scripts')

		@stack('js-component')
    </body>
</html>
