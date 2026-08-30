<x-base-layout>

    <x-slot name="pageTitle">
            {{__('cargo::view.support')}}
    </x-slot>

    <!--begin::Basic info-->
    <div class="card mb-5 mb-xl-10">

        <div class="wrapper-settings">
            <div class="mx-auto mb-5 col-lg-12">

                <div class="card mb-5">
                    <div class="card-body">
                        <div class="message message--info">
                            <p style="font-family: 'Poppins', sans-serif !important;">
                                <strong>GreenWebb System Support</strong><br>
                                This system is supported by GreenWebb. For technical assistance, product guidance, and service updates, visit
                                <a target="_blank" rel="noopener noreferrer" href="https://greenweeb.tech" style="color:#007bff;">greenweeb.tech</a>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!--end::Basic info-->
    @section('scripts')
    <script>

    </script>
    @endsection
</x-base-layout>
