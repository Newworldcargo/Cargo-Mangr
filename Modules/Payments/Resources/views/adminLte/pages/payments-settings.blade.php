<x-base-layout>

    <x-slot name="pageTitle">
        @lang('view.payment_settings')
    </x-slot>

    <section class="border-bottom mb-5 pb-4" aria-labelledby="lipila-heading">
        <h2 id="lipila-heading" class="h4">Lipila</h2>
        <p class="mb-2">Customer portal: {{ config('customerportalapi.payment_provider') === 'lipila' ? 'Default provider' : 'Available integration' }}</p>
        <p class="mb-2">{{ app(\Modules\CustomerPortalApi\Services\LipilaGateway::class)->ready() ? 'Enabled' : 'Awaiting activation' }}. Mobile money and hosted card payments.</p>
        <p class="text-muted mb-0">Credentials and activation are managed in protected deployment settings. Existing providers below are unchanged.</p>
    </section>

    <!--begin::Basic info-->
    <div class="card mb-5 mb-xl-10">

        <div class="wrapper-settings">
                <!--begin::Content-->
                <div class="main-setting">
                    <!--begin::Form-->
                    <form id="kt_account_profile_details_form" class="form" method="post" enctype="multipart/form-data">
                        @method('PUT')
                        <!--begin::Card body-->
                        <div class="card-body border-top p-9">
                        @csrf
                        @include('adminLte.pages.fields', ['fields' => $fields])
                        </div>
                        <!--end::Card body-->
                        <!--begin::Actions-->
                        <div class="card-footer d-flex justify-content-end py-6 px-9">
                            <a href="{{ url()->previous() }}" class="btn btn-light btn-active-light-primary me-2">@lang('view.discard')</a>
                            <button type="submit" class="btn btn-success" id="kt_account_profile_details_submit">@lang('view.update')</button>
                        </div>
                        <!--end::Actions-->
                    </form>
                    <!--end::Form-->
                </div>
                <!--end::Content-->
                
        </div>
    </div>
    <!--end::Basic info-->


    @section('styles')
    <link rel="stylesheet" href="{{ asset('assets/plugins/spectrum/spectrum.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/modules/css/setting.css') }}">
    @endsection

    @section('scripts')
    
    <script src="{{ asset('assets/plugins/spectrum/spectrum.min.js') }}"></script>
    <script>

        $('.color_picker_input').spectrum({
            type: "component",
            showInput: true,
             showInitial: true,
            clickoutFiresChange: true,
            allowEmpty: true,
            maxSelectionSize: 8,
        });

    </script>
    @endsection

</x-base-layout>
