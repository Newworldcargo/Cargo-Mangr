@php
    $user_role = auth()->user()->role;
    $admin = 1;
    $branch = 3;
    $client = 4;
@endphp

@extends('cargo::adminLte.layouts.master')

@section('pageTitle')
    Edit Consignments
@endsection

@section('content')
    <!-- Tailwind CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .breadcrumb a {
            color: #ffc507;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }
    </style>

    <div class="">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-light p-2 mb-0" style="font-size: 0.9rem; border-radius: 0.25rem;">
                <li class="breadcrumb-item"><a href="#">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('consignment.index') }}">Consignments</a></li>
                <li class="breadcrumb-item active" aria-current="page">Consignment - Edit</li>
            </ol>
        </nav>
    </div>
    <div class="container">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="bg-yellow-400 text-white px-6 py-4 flex items-center">
                <i class="fas fa-edit text-xl mr-3"></i>
                <h1 class="text-xl font-bold">Edit Consignment</h1>
            </div>
            <div class="p-6">
                <form action="{{ route('consignment.update', $consignment->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Left Column -->
                        <div>
                            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                                <div class="bg-gray-100 px-4 py-3 border-b">
                                    <h2 class="flex items-center text-gray-700 font-medium">
                                        <i class="fas fa-info-circle mr-2"></i>General Information
                                    </h2>
                                </div>
                                <div class="p-5 space-y-4">
                                    <div>
                                        <label for="consignment_code"
                                            class="block text-sm font-medium text-gray-700 mb-1">Consignment Code/ Job
                                            No</label>
                                        <div class="flex">
                                            <span
                                                class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                                <i class="fas fa-barcode"></i>
                                            </span>
                                            <input type="text" id="consignment_code" name="consignment_code"
                                                value="{{ $consignment->consignment_code }}"
                                                class="bg-gray-100 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300"
                                                required>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="name"
                                            class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                        <div class="flex">
                                            <span
                                                class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                                <i class="fas fa-file-signature"></i>
                                            </span>
                                            <input type="text" id="name" name="name"
                                                value="{{ $consignment->name }}"
                                                class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300"
                                                required>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="consignee"
                                            class="block text-sm font-medium text-gray-700 mb-1">Consignee</label>
                                        <div class="flex">
                                            <span
                                                class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <input type="text" id="consignee" name="consignee"
                                                value="{{ $consignment->consignee ?? 'NWC' }}"
                                                class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300"
                                                required>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="mawb_num" class="block text-sm font-medium text-gray-700 mb-1">MAWB
                                            Number</label>
                                        <div class="flex">
                                            <span
                                                class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                                <i class="fas fa-plane"></i>
                                            </span>
                                            <input type="text" id="mawb_num" name="mawb_num"
                                                value="{{ $consignment->mawb_num ?? 'none' }}"
                                                class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div>
                            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                                <div class="bg-gray-100 px-4 py-3 border-b">
                                    <h2 class="flex items-center text-gray-700 font-medium">
                                        <i class="fas fa-route mr-2"></i>Route & Type
                                    </h2>
                                </div>
                                <div class="p-5 space-y-4">
                                    <div>
                                        <label for="source"
                                            class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                                        <div class="flex">
                                            <span
                                                class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </span>
                                            <input type="text" id="source" name="source"
                                                value="{{ $consignment->source ?? 'China' }}"
                                                class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300"
                                                required>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="destination"
                                            class="block text-sm font-medium text-gray-700 mb-1">Destination</label>
                                        <div class="flex">
                                            <span
                                                class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                                <i class="fas fa-map"></i>
                                            </span>
                                            <input type="text" id="destination" name="destination"
                                                value="{{ $consignment->destination ?? 'Zambia' }}"
                                                class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300"
                                                required>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="cargo_type" class="block text-sm font-medium text-gray-700 mb-1">Cargo
                                            Type</label>
                                        <div class="flex">
                                            <span
                                                class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                                <i class="fas fa-shipping-fast"></i>
                                            </span>
                                            <select id="cargo_type" name="cargo_type"
                                                class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300"
                                                required>
                                                <option value="sea"
                                                    {{ isset($consignment->cargo_type) && $consignment->cargo_type == 'sea' ? 'selected' : '' }}>
                                                    Sea</option>
                                                <option value="air"
                                                    {{ isset($consignment->cargo_type) && $consignment->cargo_type == 'air' ? 'selected' : '' }}>
                                                    Air</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="status"
                                            class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                        <div class="flex">
                                            <span
                                                class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                                <i class="fas fa-tasks"></i>
                                            </span>
                                            <select id="status" name="status"
                                                class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300">
                                                <option value="pending"
                                                    {{ $consignment->status == 'pending' ? 'selected' : '' }}>Pending
                                                </option>
                                                <option value="in_transit"
                                                    {{ $consignment->status == 'in_transit' ? 'selected' : '' }}>In Transit
                                                </option>
                                                <option value="delivered"
                                                    {{ $consignment->status == 'delivered' ? 'selected' : '' }}>Delivered
                                                    to Main Branch</option>
                                                <option value="canceled"
                                                    {{ $consignment->status == 'canceled' ? 'selected' : '' }}>Canceled
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Information -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                        <div class="bg-gray-100 px-4 py-3 border-b">
                            <h2 class="flex items-center text-gray-700 font-medium">
                                <i class="fas fa-calendar-alt mr-2"></i>Shipping Information
                            </h2>
                        </div>
                        <div class="p-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <div>
                                    <label for="eta" class="block text-sm font-medium text-gray-700 mb-1">ETA</label>
                                    <div class="flex">
                                        <span
                                            class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                            <i class="fas fa-calendar-alt"></i>
                                        </span>
                                        <input type="date" id="eta" name="eta"
                                            value="{{ $consignment->eta ?? '' }}"
                                            class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300">
                                    </div>
                                </div>
                                <div>
                                    <label for="cargo_date" class="block text-sm font-medium text-gray-700 mb-1">Consignment Date</label>
                                    <div class="flex">
                                        <span
                                            class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                            <i class="fas fa-calendar-check"></i>
                                        </span>
                                        <input type="date" id="cargo_date" name="cargo_date"
                                            value="{{ old('cargo_date', optional($consignment->cargo_date ?: $consignment->created_at)->format('Y-m-d')) }}"
                                            class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300" required>
                                    </div>
                                </div>

                                <!-- Sea-specific fields -->
                                <div
                                    class="sea-field {{ isset($consignment->cargo_type) && $consignment->cargo_type != 'sea' ? 'hidden' : '' }}">
                                    <label for="job_num" class="block text-sm font-medium text-gray-700 mb-1">Job
                                        Number</label>
                                    <div class="flex">
                                        <span
                                            class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                            <i class="fas fa-briefcase"></i>
                                        </span>
                                        <input type="text" id="job_num" name="job_num"
                                            value="{{ $consignment->job_num ?? '' }}"
                                            class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300">
                                    </div>
                                </div>
                                <div
                                    class="sea-field {{ isset($consignment->cargo_type) && $consignment->cargo_type != 'sea' ? 'hidden' : '' }}">
                                    <label for="eta_dar" class="block text-sm font-medium text-gray-700 mb-1">ETA
                                        DAR</label>
                                    <div class="flex">
                                        <span
                                            class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                            <i class="fas fa-calendar-day"></i>
                                        </span>
                                        <input type="date" id="eta_dar" name="eta_dar"
                                            value="{{ $consignment->eta_dar ?? '' }}"
                                            class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300">
                                    </div>
                                </div>
                                <div
                                    class="sea-field {{ isset($consignment->cargo_type) && $consignment->cargo_type != 'sea' ? 'hidden' : '' }}">
                                    <label for="eta_nak" class="block text-sm font-medium text-gray-700 mb-1">ETA
                                        NAK</label>
                                    <div class="flex">
                                        <span
                                            class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                            <i class="fas fa-calendar-day"></i>
                                        </span>
                                        <input type="date" id="eta_nak" name="eta_nak"
                                            value="{{ $consignment->eta_nak ?? '' }}"
                                            class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300">
                                    </div>
                                </div>
                                <div
                                    class="sea-field {{ isset($consignment->cargo_type) && $consignment->cargo_type != 'sea' ? 'hidden' : '' }}">
                                    <label for="eta_lun" class="block text-sm font-medium text-gray-700 mb-1">ETA
                                        LUN</label>
                                    <div class="flex">
                                        <span
                                            class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-yellow-400 text-white text-sm">
                                            <i class="fas fa-calendar-day"></i>
                                        </span>
                                        <input type="date" id="eta_lun" name="eta_lun"
                                            value="{{ $consignment->eta_lun ?? '' }}"
                                            class="border-blue-500 flex-1 block w-full rounded-r-md sm:text-sm border-gray-300">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-3 mt-6">
                        <button type="submit"
                            class="btnclicky inline-flex justify-center items-center py-2 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 ease-in-out">
                            <i class="fas fa-save mr-2"></i>Update Consignment
                        </button>
                        <a href="{{ route('consignment.index') }}"
                            class="inline-flex justify-center items-center py-2 px-6 border border-transparent shadow-sm text-base font-medium rounded-md text-gray-700 bg-gray-200 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition duration-150 ease-in-out">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle cargo type change
            const cargoTypeSelect = document.getElementById('cargo_type');
            const seaFields = document.querySelectorAll('.sea-field');

            cargoTypeSelect.addEventListener('change', function() {
                if (this.value === 'sea') {
                    seaFields.forEach(field => {
                        field.classList.remove('hidden');
                        field.querySelector('input').setAttribute('required', 'required');
                    });
                } else {
                    seaFields.forEach(field => {
                        field.classList.add('hidden');
                        field.querySelector('input').removeAttribute('required');
                    });
                }
            });

            // Form validation with visual feedback
            const form = document.querySelector('form');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();

                    // Add visual feedback to invalid fields
                    const invalidFields = form.querySelectorAll(':invalid');
                    invalidFields.forEach(field => {
                        field.classList.add('border-red-500', 'ring-red-500');
                        field.classList.add('animate-shake');

                        field.addEventListener('input', function() {
                            this.classList.remove('border-red-500', 'ring-red-500');
                            this.classList.remove('animate-shake');
                        });

                        // Remove animation class after animation completes
                        setTimeout(() => {
                            field.classList.remove('animate-shake');
                        }, 500);
                    });
                }
            });
        });
    </script>

    <style>
        /* Add minimal custom styles that aren't available in basic Tailwind */
        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
        }

        .animate-shake {
            animation: shake 0.5s cubic-bezier(.36, .07, .19, .97) both;
        }

        /* Add some sensible form resets that Tailwind might not include by default */
        input,
        select {
            appearance: none;
            padding: 0.5rem;
        }

        /* Ensure the form layout works without relying on Tailwind's form plugin */
        input:focus,
        select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 1px #3b82f6;
        }
    </style>
@endsection
