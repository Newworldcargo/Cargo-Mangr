@csrf

@php
    $hasAvatar = isset($model) && $model->img;
    $getAvatar = $hasAvatar ? $model->img : '';
@endphp
<!--css & jq country_code -->
@include('cargo::adminLte.components.inputs.phone')

<!--begin::Col Avatar -->
{{-- <div class="row mb-6">
    <!--begin::Label-->
    <label class="col-form-label fw-bold fs-6">{{ __('cargo::view.table.avatar') }}</label>
    <!--end::Label-->
    <div class="col-md-12">
        <!--begin::Image input-->
        @if(isset($model))
            <x-media-library-collection max-items="1" name="image" :model="$model" collection="avatar" rules="mimes:jpg,jpeg,png,gif,bmp,svg,webp"/>
        @else
            <x-media-library-attachment name="image" rules="mimes:jpg,jpeg,png,gif,bmp,svg,webp"/>
        @endif
        <!--end::Image input-->

        <!--begin::Hint-->
        <div class="form-text">{{ __('view.hint_image_ext') }}</div>
        <!--end::Hint-->

        @error('avatar')
            <div class="is-invalid"></div>
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror

    </div>
</div> --}}

<div class="container">
    <div class="row mb-3">
        <div class="col-12">
            <input
                type="file"
                id="imageUpload"
                class="form-control d-none"
                accept="image/jpeg,image/png,image/gif,image/webp"
                name="image"
            >
            <label
                for="imageUpload"
                class="btn btn-primary w-100"
            >
                Choose Image
            </label>

            <div id="fileInfo" class="text-muted mt-2 small"></div>

            <div
                id="previewContainer"
                class="mt-3 position-relative d-none"
            >
                <div class="preview-wrapper" style="width: 200px; height: 200px; overflow: hidden; position: relative;">
                    <img
                        id="previewImage"
                        class="img-fluid position-absolute top-50 start-50 translate-middle"
                        style="min-width: 100%; min-height: 100%; object-fit: cover;"
                    >
                    <button
                        id="removeImageBtn"
                        class="btn btn-danger btn-sm position-absolute top-0 end-0 m-2 rounded-circle"
                    >
                        ×
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const upload = document.getElementById('imageUpload');
    const preview = document.getElementById('previewImage');
    const previewContainer = document.getElementById('previewContainer');
    const fileInfo = document.getElementById('fileInfo');
    const removeBtn = document.getElementById('removeImageBtn');

    const showError = (message) => {
        fileInfo.textContent = message;
        fileInfo.className = 'text-danger mt-2 small';
        upload.value = '';
    };

    upload.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            return showError('Invalid file type. Use JPEG, PNG, GIF, or WebP.');
        }

        if (file.size > 5 * 1024 * 1024) {
            return showError('File too large. Maximum 5MB.');
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            preview.src = e.target.result;
            previewContainer.classList.remove('d-none');
            fileInfo.textContent = file.name;
            fileInfo.className = 'text-muted mt-2 small';
        };
        reader.readAsDataURL(file);
    });

    removeBtn.addEventListener('click', () => {
        upload.value = '';
        previewContainer.classList.add('d-none');
        fileInfo.textContent = '';
    });
});
</script>
<!--end::Col-->


<!--begin::Input group --  Full name -->
<div class="row mb-6">
    <!--begin::Label-->
    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.table.full_name') }}</label>
    <!--end::Label-->

    <!--begin::Input group-->
    <div class="col-lg-12 fv-row">
        <div class="input-group mb-4">
            <input type="text" name="name" class="form-control form-control-lg @error('name') is-invalid @enderror" placeholder="{{ __('cargo::view.table.full_name') }}" value="{{ old('name', isset($model) ? $model->name : '') }}" />
            @error('name')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>
    </div>
    <!--end::Input group-->
</div>
<!--end::Input group-->

<!--begin::Input group --  Email -->
<div class="row mb-6">
    <!--begin::Label-->
    <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.table.email') }}</label>
    <!--end::Label-->
    <!--begin::Input group-->
    <div class="col-lg-12 fv-row">
        <div class="input-group mb-4">
            <input type="text" name="email" class="form-control form-control-lg @error('email') is-invalid @enderror" placeholder="{{ __('cargo::view.table.email') }}" value="{{ old('email', isset($model) ? $model->email : '') }}" />
            @error('email')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>
    </div>
    <!--end::Input group-->
</div>
<!--end::Input group-->

<!--begin::Input group --  Password -->
<div class="row mb-6">

    <!--begin::Input group-->
    <div class="col-lg-6 fv-row">
        <!--begin::Label-->
        <label class="col-form-label fw-bold fs-6 @if($typeForm == 'create') required @endif">{{ __('cargo::view.table.password') }}</label>
        <!--end::Label-->
        <div class="input-group mb-4">
            <input type="password" id="password" name="password" class="form-control form-control-lg has-feedback @error('password') is-invalid @enderror" placeholder="{{ __('cargo::view.table.password') }}" value="{{ old('password', isset($model) ? $model->password : '') }}" />
            <i id="check" class="far fa-eye" id="togglePassword" style="cursor: pointer;position: absolute;right: 0;padding: 3%;font-size: 16px;"></i>
            @error('password')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>
    </div>
    <!--end::Input group-->


    <!--begin::Input group --  National Id -->
    <!--begin::Input group-->
    <div class="col-lg-6 fv-row">
        <!--begin::Label-->
        <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.table.owner_national_id') }}</label>
        <!--end::Label-->
        <div class="input-group mb-4">
            <input type="number" name="national_id" class="form-control form-control-lg @error('national_id') is-invalid @enderror" placeholder="{{ __('cargo::view.table.owner_national_id') }}" value="{{ old('national_id', isset($model) ? $model->national_id : '') }}" />
            @error('national_id')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>
    </div>
    <!--end::Input group-->
</div>
<!--end::Input group-->

<!--begin::Input group --  Address -->
<div class="row mb-6">

    <!--begin::Input group-->
    <div class="col-lg-12 fv-row">
        <!--begin::Label-->
        <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.table.address') }}</label>
        <!--end::Label-->
        <div class="input-group mb-4">
            <input type="text" name="address" class="form-control form-control-lg @error('address') is-invalid @enderror" placeholder="{{ __('cargo::view.table.address') }}" value="{{ old('address', isset($model) ? $model->address : '') }}" />
            @error('address')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>
    </div>
    <!--end::Input group-->
</div>
<!--end::Input group-->

<!-- Branch location used by routing and spreadsheet imports -->
<div class="row mb-6">
    <div class="col-lg-6 fv-row">
        <label class="col-form-label fw-bold fs-6 required">Branch country</label>
        <select name="country_id" class="form-control form-control-lg @error('country_id') is-invalid @enderror">
            <option value="">Select country</option>
            @foreach($countries as $country)
                <option value="{{ $country->id }}" {{ old('country_id', isset($model) ? $model->country_id : '') == $country->id ? 'selected' : '' }}>{{ $country->name }}</option>
            @endforeach
        </select>
        @error('country_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-lg-6 fv-row">
        <label class="col-form-label fw-bold fs-6 required">Branch province/state</label>
        <select name="state_id" class="form-control form-control-lg @error('state_id') is-invalid @enderror">
            <option value="">Select province/state</option>
            @foreach($states as $state)
                @php($stateCountry = $countries->firstWhere('id', $state->country_id))
                <option value="{{ $state->id }}" {{ old('state_id', isset($model) ? $model->state_id : '') == $state->id ? 'selected' : '' }}>{{ $stateCountry ? $stateCountry->name.' — ' : '' }}{{ $state->name }}</option>
            @endforeach
        </select>
        @error('state_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Configured once here, then reused by shipment imports.</small>
    </div>
</div>


<!--begin::Input group --  Owner all -->
<div class="row mb-6">
    <!--begin::Input group --  Owner Name -->
    <!--begin::Input group-->
    <div class="col-lg-6 fv-row">
        <!--begin::Label-->
        <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.table.owner_name') }}</label>
        <!--end::Label-->
        <div class="input-group mb-4">
            <input type="text" name="responsible_name" class="form-control form-control-lg @error('responsible_name') is-invalid @enderror" placeholder="{{ __('cargo::view.table.owner_name') }}" value="{{ old('responsible_name', isset($model) ? $model->responsible_name : '') }}" />
            @error('responsible_name')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>
    </div>
    <!--end::Input group-->


    <!--begin::Input group --  Owner Phone -->
    <!--begin::Input group-->
    <div class="col-lg-6 fv-row">
        <!--begin::Label-->
        <label class="col-form-label fw-bold fs-6 required">{{ __('cargo::view.table.owner_phone') }}</label>
        <!--end::Label-->
        <div class="input-group mb-4">
            <input  type="tel" name="responsible_mobile" id="phone" dir="ltr" autocomplete="off" required  class=" phone_input number-only form-control mb-3 inptFielsd @error('responsible_mobile') is-invalid @enderror" placeholder="{{ __('cargo::view.table.owner_phone') }}" value="{{ old('responsible_mobile', isset($model) ? $model->country_code.$model->responsible_mobile : base_country_code()) }}" />
            <input type="hidden" class="country_code" name="country_code" value="{{ old('country_code', isset($model) ? $model->country_code : base_country_code()) }}" data-reflection="phone">
            @error('responsible_mobile')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>
    </div>
    <!--end::Input group-->
</div>
<!--end::Input group-->

{{-- Inject Scripts --}}
@section('scripts')
<script>
    $('#check').click(function(){
        console.log('salman');
        const type = $('#password').attr('type') === 'password' ? 'text' : 'password';
        $('#password').prop('type', type);
    });
</script>
@endsection
