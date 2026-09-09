@csrf

@php
    $hasAvatar = isset($model) && $model->avatar;
    $getAvatar = $hasAvatar ? $model->avatarImage : '';
    $branches = Modules\Cargo\Entities\Branch::where('is_archived',0)->get();
@endphp
<!--css & jq country_code -->
@include('cargo::adminLte.components.inputs.phone')

<!--begin::Col Avatar -->
<div class="row mb-6">
    <!--begin::Label-->
    <label class="col-md-4 col-form-label fw-bold fs-6">{{ __('users::view.table.avatar') }}</label>
    <!--end::Label-->
    <div class="col-md-8">
        <!--begin::Image input-->
        {{-- @if(isset($model))
            <x-media-library-collection max-items="1" name="image" :model="$model" collection="avatar" rules="mimes:jpg,jpeg,png,gif,bmp,svg,webp"/>
        @else
            <x-media-library-attachment name="image" rules="mimes:jpg,jpeg,png,gif,bmp,svg,webp"/>
        @endif --}}



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
                        <label for="imageUpload" class="btn btn-primary w-100" >
                            Choose Image
                        </label>
                        <div id="fileInfo" class="text-muted mt-2 small"></div>
                        <div id="previewContainer" class="mt-3 position-relative d-none">
                            <div class="preview-wrapper" style="width: 200px; height: 200px; overflow: hidden; position: relative;">
                                <img id="previewImage" class="img-fluid position-absolute top-50 start-50 translate-middle" style="min-width: 100%; min-height: 100%; object-fit: cover;">
                                <button id="removeImageBtn" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-2 rounded-circle">
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
        <!--end::Image input-->

        @error('avatar')
            <div class="is-invalid"></div>
            <div class="invalid-feedback">
                {{ $message }}
            </div>
        @enderror

    </div>
</div>
<!--end::Col-->


<!--begin::Input group --  Full name -->
<div class="row mb-6">
    <!--begin::Label-->
    <label class="col-lg-4 col-form-label @if ($typeForm == 'create') required @endif fw-bold fs-6">{{ __('users::view.table.full_name') }}</label>
    <!--end::Label-->

    <!--begin::Input group-->
    <div class="col-lg-8 fv-row">
        <div class="input-group mb-4">
            <input type="text" name="name" class="form-control form-control-lg @error('name') is-invalid @enderror" placeholder="{{ __('users::view.table.full_name') }}" value="{{ old('name', isset($model) ? $model->name : '') }}" />
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
    <label class="col-lg-4 col-form-label @if ($typeForm == 'create') required @endif fw-bold fs-6">{{ __('users::view.table.email') }}</label>
    <!--end::Label-->
    <!--begin::Input group-->
    <div class="col-lg-8 fv-row">
        <div class="input-group mb-4">
            <input type="text" name="email" class="form-control form-control-lg @error('email') is-invalid @enderror" placeholder="{{ __('users::view.table.email') }}" value="{{ old('email', isset($model) ? $model->email : '') }}" />
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
    <!--begin::Label-->

        <label class="col-lg-4 col-form-label @if ($typeForm == 'create') required @endif fw-bold fs-6">{{ __('users::view.table.password') }}</label>
        <!--end::Label-->

        <!--begin::Input group-->
        <div class="col-lg-8 fv-row">
            <div class="input-group mb-4">
                <input type="password" name="password" class="form-control form-control-lg @error('password') is-invalid @enderror" placeholder="{{ __('users::view.table.password') }}" value="{{ old('password') }}"  />
                @error('password')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror
            </div>
        </div>
        <!--end::Input group-->
    </div>
    <!--end::Input group-->


<!-- Show role only in the following cases -->
<!-- if auth is admin only, if user id not equal 1 -->

    <!--begin::Input group -- Phone -->
    <div class="row mb-6">

        <!--begin::Label-->
        <label class="col-lg-4 col-form-label required fw-bold fs-6">{{ __('cargo::view.table.phone') }}</label>
        <!--end::Label-->

        <!--begin::Input group-->
        <div class="col-lg-8 fv-row">
            <div class="input-group mb-4">
                <input type="tel" name="responsible_mobile"  id="phone" dir="ltr" autocomplete="off" required  class=" phone_input number-only form-control form-control-lg inptFielsd  @error('responsible_mobile') is-invalid @enderror" placeholder="{{ __('cargo::view.table.phone') }}" value="{{ old('responsible_mobile', isset($model) ? $model->country_code.$model->responsible_mobile : base_country_code()) }}" />
                <input type="hidden" class="country_code" name="country_code" value="{{ old('country_code', isset($model) ?$model->country_code : base_country_code() )}}" data-reflection="phone">
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

@if (auth()->user()->role == 1 && ( (isset($model) && $model->id != 1) || !isset($model)))

<!--begin::Input group-->
<div class="row mb-6">
    <!--begin::Label-->
    <label class="col-lg-4 col-form-label @if ($typeForm == 'create') required @endif fw-bold fs-6">{{ __('users::view.table.user_type') }}</label>
    <!--end::Label-->
    <!--begin::Col-->
    <div class="col-lg-8 fv-row">
        <!--begin::Options-->
        <div class="d-flex align-items-center form-group clearfix">
            @foreach (config('cms.user_roles') as $value => $titleRole)
                <!--begin::Option-->
                <div class="form-check form-check-custom form-check-solid me-5">
                    <input
                        class="is_user"
                        name="role"
                        type="radio"
                        value="{{ $value }}"
                        id="{{ $titleRole . $value }}"
                        {{ isset($model) && $model->role == $value ? 'checked="checked"' : ($value == 0 ? 'checked="checked"' : '') }}
                    >
                    <label class="form-check-label" for="{{ $titleRole . $value }}">
                        {{ $titleRole }}
                    </label>
                </div>
                <!--end::Option-->
            @endforeach
        </div>
        <!--end::Options-->
    </div>
    <!--end::Col-->
</div>
<!--end::Input group-->

@endif

@if($typeForm == 'edit')
    @if (isset($model) && $model->role != 1)
   <!--begin::Input group --  National Id -->
    <div class="row mb-6">

        <!--begin::Label-->
        <label class="col-lg-4 col-form-label required fw-bold fs-6">{{ __('cargo::view.table.national_id') }}</label>
        <!--end::Label-->

        <!--begin::Input group-->
        <div class="col-lg-8 fv-row">

            <div class="input-group mb-4">
                <input type="number" name="national_id" class="form-control form-control-lg @error('national_id') is-invalid @enderror" placeholder="{{ __('cargo::view.table.national_id') }}" value="{{ old('national_id', isset($model) ? $model->national_id : '') }}" />
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

    <!--begin::Input group -- Branch -->
    <div class="row mb-6">
        <!--begin::Label-->
        <label class="col-lg-4 col-form-label required fw-bold fs-6">{{ __('cargo::view.table.branch') }}</label>
        <!--end::Label-->

        <!--begin::Input group-->
        <div class="col-lg-8 fv-row fv-row">
            <div class="mb-4">
                <select
                    class="form-control  @error('branch_id') is-invalid @enderror"
                    name="branch_id"
                    data-control="select2"
                    data-placeholder="{{ __('cargo::view.table.choose_branch') }}"
                    data-allow-clear="true"
                    id="change-country"
                >
                    <option></option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}"
                            {{ old('branch_id') == $branch->id ? 'selected' : '' }}
                        >{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branch_id')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror
            </div>
        </div>
        <!--end::Input group-->
    </div>
    <!--end::Input group-->

    @endif
@elseif($typeForm == 'create')
    <div id="user_type">
    <!--begin::Input group --  National Id -->
        <div class="row mb-6">

            <!--begin::Label-->
            <label class="col-lg-4 col-form-label required fw-bold fs-6">{{ __('cargo::view.table.national_id') }}</label>
            <!--end::Label-->

            <!--begin::Input group-->
            <div class="col-lg-8 fv-row">

                <div class="input-group mb-4">
                    <input type="number" name="national_id" class="form-control form-control-lg @error('national_id') is-invalid @enderror" placeholder="{{ __('cargo::view.table.national_id') }}" value="{{ old('national_id', isset($model) ? $model->national_id : '') }}" />
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

    <!--begin::Input group -- Branch -->
        <div class="row mb-6">
            <!--begin::Label-->
            <label class="col-lg-4 col-form-label required fw-bold fs-6">{{ __('cargo::view.table.branch') }}</label>
            <!--end::Label-->

            <!--begin::Input group-->
            <div class="col-lg-8 fv-row fv-row">
                <div class="mb-4">
                    <select
                        class="form-control  @error('branch_id') is-invalid @enderror"
                        name="branch_id"
                        data-control="select2"
                        data-placeholder="{{ __('cargo::view.table.choose_branch') }}"
                        data-allow-clear="true"
                        id="change-country"
                    >
                        <option></option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}"
                                {{ old('branch_id') == $branch->id ? 'selected' : '' }}
                            >{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
            </div>
            <!--end::Input group-->
        </div>
    <!--end::Input group-->

    </div>
@endif

{{-- Inject Scripts --}}
@push('js-component')
<script type="text/javascript">
    $('input[type=radio][class=is_user]:checked').each(function () {
        if(this.value == 0)
        {
            $("#user_type").css("display","block");
        }else{
            $("#user_type").css("display","none");
        }
    });

    $('input[type=radio][class=is_user]').change(function() {
        if(this.value == 0)
        {
            $("#user_type").css("display","block");
        }else{
            $("#user_type").css("display","none");
        }
    });
</script>
@endpush