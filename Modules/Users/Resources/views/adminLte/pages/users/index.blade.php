@extends('users::adminLte.layouts.master')

@section('pageTitle')
    {{ __('users::view.user_list') }}
@endsection

@section('content')
<!-- Tailwind CSS CDN -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />

    <!-- Enhanced Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-6">
        <ol class="flex items-center py-3 px-5 bg-gradient-to-r from-yellow-50 to-indigo-50 rounded-xl shadow-sm border border-yellow-100 text-sm">
            <li class="flex items-center">
                <svg class="h-4 w-4 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4l2 2 4-4"></path>
                </svg>
                <a href="{{ fr_route('dashboard') }}" class="text-yellow-600 hover:text-yellow-700 font-medium transition-all duration-200 hover:underline">Dashboard</a>
                <svg class="h-4 w-4 mx-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
            </li>
            <li class="flex items-center">
                <svg class="h-4 w-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <span class="text-gray-700 font-semibold" aria-current="page">Users</span>
            </li>
        </ol>
    </nav>

    <!--begin::Enhanced Card-->
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
        <!--begin::Card header with gradient-->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
            <div class="px-6 py-6">
                <!--begin::Header content-->
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                    <!--begin::Card title-->
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-yellow-500 rounded-xl shadow-md">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">User Management</h2>
                            <p class="text-sm text-gray-600 mt-1">Manage and organize your users</p>
                        </div>
                    </div>
                    <!--end::Card title-->

                    <!--begin::Add user button-->
                    @can('create-users')
                        <a href="{{ fr_route('users.create') }}" 
                           class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-yellow-400 to-yellow-500 hover:from-yellow-400 hover:to-yellow-500 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 group">
                            <svg class="h-5 w-5 mr-2 group-hover:rotate-90 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            {{ __('users::view.add_user') }}
                        </a>
                    @endcan
                    <!--end::Add user button-->
                </div>
                <!--end::Header content-->
            </div>

            <div class="px-6 pb-5">
                <div class="user-scope-filter">
                    <div class="user-scope-filter__shell">
                        <div class="user-scope-filter__title">Show users for</div>
                        <div class="user-scope-filter__card">
                        @if($scopeBranches->isNotEmpty())
                            <label class="user-scope-filter__field">
                                <span>Branch</span>
                                <select id="{{ $table_id }}_scope_branch" class="form-select form-select-sm" aria-label="Filter users by branch">
                                    <option value="">All branches I can access</option>
                                    @foreach($scopeBranches as $scopeBranch)
                                        <option value="{{ $scopeBranch->id }}">{{ $scopeBranch->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <label class="user-scope-filter__toggle" title="Turn this on to see only your user record">
                            <input id="{{ $table_id }}_scope_self" type="checkbox" role="switch">
                            <span>Only my user record</span>
                        </label>
                        <button id="{{ $table_id }}_scope_reset" type="button" class="btn btn-sm btn-light">Reset</button>
                        </div>
                    </div>
                </div>
            </div>

            <!--begin::Enhanced Toolbar-->
            <div class="px-6 pb-6">
                <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                        <!--begin::Search Section-->
                        <div class="flex flex-col sm:flex-row sm:items-center space-y-3 sm:space-y-0 sm:space-x-4">
                            <!--begin::Search-->
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <div class="pl-10">
                                    @include('adminLte.components.modules.datatable.search', ['table_id' => $table_id])
                                </div>
                            </div>
                            <!--end::Search-->

                            <!--begin::Length selector-->
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-600 whitespace-nowrap">Show:</span>
                                @include('adminLte.components.modules.datatable.datatable_length', ['table_id' => $table_id])
                            </div>
                            <!--end::Length selector-->
                        </div>
                        <!--end::Search Section-->

                        <!--begin::Action Buttons-->
                        <div class="flex flex-wrap items-center gap-2" id="{{ $table_id }}_custom_filter">
                            <!--begin::Reload button-->
                            <div class="inline-flex items-center bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors duration-200">
                                @include('adminLte.components.modules.datatable.reload', ['table_id' => $table_id])
                            </div>
                            <!--end::Reload button-->

                            <!--begin::Filter-->
                            <div class="bg-gray-50 rounded-lg">
                                <x-table-filter :table_id="$table_id" :filters="$filters">
                                    {{-- Start Custom Filters --}}
                                    @include('users::adminLte.pages.users.table.filters.role', ['table_id' => $table_id, 'filters' => $filters])
                                    @if ((int) auth()->user()->role === \App\Models\User::ADMIN)
                                        @include('users::adminLte.pages.users.table.filters.branch', ['table_id' => $table_id, 'filters' => $filters])
                                    @endif
                                    @include('users::adminLte.pages.users.table.filters.name', ['table_id' => $table_id, 'filters' => $filters])
                                    {{-- End Custom Filters --}}
                                </x-table-filter>
                            </div>
                            <!--end::Filter-->

                            @can('export-table-users')
                                <!--begin::Export buttons-->
                                <div class="bg-green-50 rounded-lg">
                                    @include('adminLte.components.modules.datatable.export', ['table_id' => $table_id, 'btn_exports' => $btn_exports])
                                </div>
                                <!--end::Export buttons-->
                            @endcan
                        </div>
                        <!--end::Action Buttons-->
                    </div>

                    <!--begin::Group actions-->
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        @include('adminLte.components.modules.datatable.columns.checkbox-actions', [
                            'table_id' => $table_id,
                            'permission' => 'delete-users',
                            'url' => fr_route('users.multi-destroy'),
                            'callback' => 'reload-table',
                            'model_name' => __('users::view.selected_users')
                        ])
                    </div>
                    <!--end::Group actions-->
                </div>
            </div>
            <!--end::Enhanced Toolbar-->
        </div>
        <!--end::Card header-->

        <!--begin::Card body-->
        <div class="p-6">
            <!--begin::Table Container-->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <!--begin::Table-->
                {{ $dataTable->table() }}
                <!--end::Table-->
            </div>
            <!--end::Table Container-->
        </div>
        <!--end::Card body-->
    </div>
    <!--end::Enhanced Card-->

@endsection

@section('toolbar-btn')
    <!--begin::Button-->
    {{-- <a href="{{ fr_route('users.create') }}" class="btn btn-sm btn-primary">Create <i class="ms-2 fas fa-plus"></i> </a> --}}
    <!--end::Button-->
@endsection

{{-- Inject styles --}}
@section('styles')
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
    <link rel="stylesheet" href="{{ asset('assets/lte/plugins/custom/datatables/datatables.bundle.css') }}">
    
    <style>
        /* Enhanced DataTable styling */
        .dataTables_wrapper {
            font-family: 'Poppins', sans-serif;
        }
        
        .dataTables_filter input {
            @apply rounded-lg border-gray-200 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 transition-colors duration-200;
        }
        
        .dataTables_length select {
            @apply rounded-lg border-gray-200 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 transition-colors duration-200;
        }
        
        .dataTables_info {
            @apply text-sm text-gray-600;
        }
        
        .dataTables_paginate .paginate_button {
            @apply mx-1 px-3 py-2 text-sm rounded-lg border border-gray-200 bg-white hover:bg-gray-50 transition-colors duration-200;
        }
        
        .dataTables_paginate .paginate_button.current {
            @apply bg-yellow-500 text-white border-yellow-500 hover:bg-yellow-600;
        }
        
        table.dataTable thead th {
            @apply bg-gray-50 text-gray-700 font-semibold border-b-2 border-gray-200;
        }
        
        table.dataTable tbody tr {
            @apply hover:bg-gray-50 transition-colors duration-150;
        }
        
        table.dataTable tbody tr:nth-child(even) {
            @apply bg-gray-25;
        }
        
        /* Custom scrollbar for table */
        .dataTables_scrollBody::-webkit-scrollbar {
            height: 8px;
        }
        
        .dataTables_scrollBody::-webkit-scrollbar-track {
            @apply bg-gray-100 rounded-full;
        }
        
        .dataTables_scrollBody::-webkit-scrollbar-thumb {
            @apply bg-gray-400 rounded-full hover:bg-gray-500;
        }
        
        /* Button enhancements */
        .btn {
            @apply transition-all duration-200 transform hover:scale-105;
        }
        
        /* Loading animation */
        @keyframes pulse-subtle {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        .loading-pulse {
            animation: pulse-subtle 2s infinite;
        }

        /* Branch scope controls: mirrors the operational report filters. */
        .user-scope-filter { width: 100%; }
        .user-scope-filter__shell { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .user-scope-filter__title { color: #374151; font-size: .9rem; font-weight: 700; white-space: nowrap; }
        .user-scope-filter__card {
            display: flex; flex-wrap: wrap; align-items: end; justify-content: flex-end; gap: .65rem;
            padding: .65rem .8rem; border: 1px solid #e5e7eb; border-radius: .65rem;
            background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .user-scope-filter__field { margin: 0; min-width: 0; }
        .user-scope-filter__field > span { display: block; margin-bottom: .2rem; color: #6b7280; font-size: .72rem; font-weight: 600; }
        .user-scope-filter__field select { width: 13.5rem; max-width: 100%; font-size: .82rem; }
        .user-scope-filter__toggle { display: inline-flex; align-items: center; gap: .45rem; margin: 0; color: #374151; font-size: .8rem; font-weight: 600; white-space: nowrap; cursor: pointer; }
        .user-scope-filter__toggle input {
            appearance: none; -webkit-appearance: none; position: relative; width: 2.5rem; height: 1.35rem;
            margin: 0; border: 0; border-radius: 999px; background: #cbd5e1; cursor: pointer;
            transition: background .18s ease; outline: none; box-shadow: inset 0 0 0 1px rgba(15,23,42,.08);
        }
        .user-scope-filter__toggle input::after {
            content: ''; position: absolute; top: .18rem; left: .18rem; width: .99rem; height: .99rem;
            border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,.3); transition: transform .18s ease;
        }
        .user-scope-filter__toggle input:checked { background: #0d6efd; }
        .user-scope-filter__toggle input:checked::after { transform: translateX(1.15rem); }
        .user-scope-filter__toggle input:focus-visible { box-shadow: 0 0 0 3px rgba(13,110,253,.25); }
        @media (max-width: 575.98px) {
            .user-scope-filter__shell { align-items: stretch; flex-direction: column; gap: .5rem; }
            .user-scope-filter__card, .user-scope-filter__field, .user-scope-filter__field select { width: 100%; }
            .user-scope-filter__card { justify-content: stretch; }
            .user-scope-filter__card .btn { flex: 1; }
        }
    </style>
@endsection

{{-- Inject Scripts --}}
@section('scripts')
    <script src="{{ asset('assets/lte/plugins/custom/datatables/datatables.bundle.js') }}"></script>
    {{ $dataTable->scripts() }}
    
    <script>
        $(function () {
            const tableId = '{{ $table_id }}';
            const table = $('#' + tableId).DataTable();
            const branchSelect = $('#' + tableId + '_scope_branch');
            const selfToggle = $('#' + tableId + '_scope_self');
            const storageKey = 'nwc-user-scope-{{ auth()->id() }}';
            const viewerId = {{ (int) auth()->id() }};
            let scope = { branch_id: '', self: false };

            try { scope = { ...scope, ...JSON.parse(localStorage.getItem(storageKey) || '{}') }; } catch (e) {}
            if (branchSelect.length && scope.branch_id && !branchSelect.find('option').filter(function () {
                return this.value === String(scope.branch_id);
            }).length) {
                scope.branch_id = '';
            }
            branchSelect.val(scope.branch_id || '');
            selfToggle.prop('checked', !!scope.self);

            table.on('preXhr.dt', function (event, settings, data) {
                data.filter = data.filter || {};
                data.filter.branch_id = scope.branch_id || '';
                data.filter.user_id = scope.self ? viewerId : '';
            });

            const refreshScope = function () {
                localStorage.setItem(storageKey, JSON.stringify(scope));
                table.ajax.reload();
            };
            branchSelect.on('change', function () { scope.branch_id = this.value; refreshScope(); });
            selfToggle.on('change', function () { scope.self = this.checked; refreshScope(); });
            $('#' + tableId + '_scope_reset').on('click', function () {
                scope = { branch_id: '', self: false };
                branchSelect.val(''); selfToggle.prop('checked', false); refreshScope();
            });
            if (scope.branch_id || scope.self) { table.ajax.reload(); }
        });

        // Enhanced UX interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    if (!this.disabled) {
                        this.classList.add('loading-pulse');
                        setTimeout(() => {
                            this.classList.remove('loading-pulse');
                        }, 2000);
                    }
                });
            });
            
            // Smooth scroll for any internal links
            const internalLinks = document.querySelectorAll('a[href^="#"]');
            internalLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
            
            // Add tooltip-like behavior for truncated text
            const cells = document.querySelectorAll('td');
            cells.forEach(cell => {
                if (cell.scrollWidth > cell.clientWidth) {
                    cell.setAttribute('title', cell.textContent);
                }
            });
        });
    </script>
@endsection
