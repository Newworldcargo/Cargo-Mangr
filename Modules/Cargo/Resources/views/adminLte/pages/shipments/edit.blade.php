@extends('cargo::adminLte.layouts.master')

@section('pageTitle')
    {{ __('cargo::view.edit_shipment') }} - {{$model->name}}
@endsection


@section('content')

    <!--begin::Basic info-->
    <div class="card mb-5 mb-xl-10">
        <!--begin::Card header-->
        {{-- <div class="card-header border-0 cursor-pointer" role="button" data-bs-toggle="collapse" data-bs-target="#kt_account_profile_details" aria-expanded="true" aria-controls="kt_account_profile_details"> --}}
        <div class="card-header">
            <!--begin::Card title-->
            <div class="card-title m-0">
                <h3 class="fw-bolder m-0">{{ __('cargo::view.edit_shipment') }}</h3>
            </div>
            @if($model->consignment_id)
                <div class="card-toolbar">
                    <a href="{{ route('consignment.show', $model->consignment_id) }}" class="btn btn-light">
                        <i class="fas fa-arrow-left"></i> Back to consignment
                    </a>
                </div>
            @endif
            <!--end::Card title-->

        </div>
        <!--begin::Card header-->
        <!--begin::Content-->
        <div>
            <!--begin::Form-->
            <form id="kt_account_profile_details_form" class="form" action="{{ fr_route('shipments.update', ['shipment' => $model->id]) }}" method="post" enctype="multipart/form-data">
                @method('PUT')
                <!--begin::Card body-->
                <div class="card-body border-top p-9">
                    @include('cargo::adminLte.pages.shipments.form', ['typeForm' => 'edit'])
                </div>
                <!--end::Card body-->
                <!--begin::Actions-->
                <!--end::Actions-->
            </form>
            <!--end::Form-->
        </div>
        <!--end::Content-->
    </div>
    <!--end::Basic info-->

@endsection
