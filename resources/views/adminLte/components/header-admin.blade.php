<!-- Left navbar links -->
<ul class="navbar-nav d-flex align-items-center">
    <!-- Toggle Menu Button -->
    <li class="nav-item">
        <a class="nav-link px-2 text-primary-hover transition-all" data-widget="pushmenu" href="#" role="button">
            <i class="fas fa-bars fa-fw"></i>
        </a>
    </li>

    <!-- Divider -->
    <li class="nav-item d-none d-sm-block">
        <div class="border-end h-75 mx-2 opacity-25"></div>
    </li>

    <!-- Website Link -->
    <li class="nav-item d-sm-inline-block mobile_section">
        <a href="https://www.newworldcargo.com"
           target="_blank"
           class="nav-link px-2 {{ active_route('/') }} d-flex align-items-center">
            <i class="fas fa-globe fa-fw me-1"></i>
            <span class="font-weight-medium">Website</span>
        </a>
    </li>
</ul>

<!-- Center Search Bar -->
@auth
<div class="navbar-nav mx-auto flex-grow-1 d-lg-flex">
    <div class="search-container w-100" style="max-width: 600px;">
        <div class="search-input-container">
            <input type="text" 
                   id="globalSearchInput"
                   class="form-control search-input" 
                   placeholder="Search consignments, shipments, users..."
                   autocomplete="off">
        </div>
        
        <!-- Live Search Results Dropdown -->
        <div id="searchResults" class="search-results-dropdown" style="display: none;">
            <div class="search-results-header">
                <h6 class="mb-0">Search Results</h6>
                <button type="button" class="btn-close" id="closeSearchResults"></button>
            </div>
            <div id="searchResultsContent" class="search-results-content">
                <!-- Results will be populated here -->
            </div>
            <div class="search-results-footer">
                <a href="#" id="viewAllResults" class="btn btn-primary btn-sm w-100">
                    View All Results
                </a>
            </div>
        </div>
    </div>
</div>
@endauth

<!-- Right navbar links -->
<ul class="navbar-nav ml-auto">
    @php($impersonator = app(\Modules\Users\Services\ImpersonationService::class)->impersonator())
    @if ($impersonator)
        <li class="nav-item d-flex align-items-center mr-2">
            <span class="badge badge-warning px-3 py-2">
                {{ __('Logged in as emulated') }} {{ auth()->user()->name }}
            </span>
            <form method="POST" action="{{ fr_route('users.impersonation.leave') }}" class="ml-2">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Exit emulation') }}</button>
            </form>
        </li>
    @endif
    <!-- Currency Conversion Button -->

    @if ($defcurrency->code == 'ZMW')
    <li class="nav-item dropdown">
        <a class="nav-link d-flex align-items-center bg-light rounded-pill px-3 py-2 border-0" href="#" data-toggle="modal" data-target="#currencyModal">
            <span class="text-primary mr-2">{{ number_format(current_x_rate(), 2) }}</span>
            <i class="fas fa-exchange-alt fa-fw text-info animated-icon"></i>
        </a>
    </li>
    @endif
    <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
            <i class="far fa-bell"></i>
            <span
                class="badge badge-warning navbar-badge">{{ \Auth::user()->unreadNotifications->count() }}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
            @if (\Auth::user()->unreadNotifications->count() > 0)
                <span class="dropdown-item dropdown-header">{{ \Auth::user()->unreadNotifications->count() }}
                    @lang('view.notifications')</span>
                <div class="dropdown-divider"></div>
                @foreach (\Auth::user()->unreadNotifications as $key => $item)
                    <a href="{{ route('notification.view', ['id' => $item->id]) }}" class="dropdown-item">
                        <i
                            class="@if ($item->icon) {{ $item->icon }} @else fas fa-bell @endif mr-2"></i>
                        {{ $item->data['message']['subject'] }}
                        <span
                            class="float-right text-muted text-sm ml-2">{{ $item->created_at->diffForHumans(null, null, true) }}</span>
                    </a>
                    <div class="dropdown-divider"></div>
                @endforeach
                <a href="#" class="dropdown-item dropdown-footer">@lang('view.see_all_notifications')</a>
            @else
                <span class="dropdown-item dropdown-header">@lang('view.no_new_notifications')</span>
                <div class="dropdown-divider"></div>
            @endif
        </div>
    </li>

    <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
            <i class="fas fa-user"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
            <span class="dropdown-item dropdown-header">{{ auth()->user()->name }}</span>
            <div class="dropdown-divider"></div>
            @checkModule('users')

            {{-- Admin --}}
            @if ($user_role == $admin)
                <a href="{{ fr_route('users.show', ['id' => auth()->id()]) }}" class="dropdown-item">
                    @lang('users::view.my_profile')
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ fr_route('users.edit', ['id' => auth()->id()]) }}" class="dropdown-item">
                    @lang('users::view.account_settings')
                </a>
                <div class="dropdown-divider"></div>
            @endif

            {{-- client --}}
            @if ($user_role == $auth_client)
                @php
                    $item_id = Modules\Cargo\Entities\Client::where('user_id', auth()->user()->id)
                        ->pluck('id')
                        ->first();
                @endphp
                <a href="{{ fr_route('clients.show', ['client' => $item_id]) }}" class="dropdown-item">
                    @lang('users::view.my_profile')
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ fr_route('clients.profile', ['id' => $item_id]) }}" class="dropdown-item">
                    @lang('users::view.account_settings')
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ fr_route('clients.manage-address') }}" class="dropdown-item">
                    @lang('cargo::view.manage_address')
                </a>
                <div class="dropdown-divider"></div>
            @endif

            {{-- branch --}}
            @if ($user_role == $auth_branch)
                @php
                    $item_id = Modules\Cargo\Entities\Branch::where('user_id', auth()->user()->id)
                        ->pluck('id')
                        ->first();
                @endphp
                <a href="{{ fr_route('branches.show', ['branch' => $item_id]) }}" class="dropdown-item">
                    @lang('users::view.my_profile')
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ fr_route('branches.profile', ['id' => $item_id]) }}" class="dropdown-item">
                    @lang('users::view.account_settings')
                </a>
                <div class="dropdown-divider"></div>
            @endif


            {{-- driver --}}
            @if ($user_role == $auth_dilver)
                @php
                    $item_id = Modules\Cargo\Entities\Driver::where('user_id', auth()->user()->id)
                        ->pluck('id')
                        ->first();
                @endphp
                <a href="{{ fr_route('drivers.show', ['driver' => $item_id]) }}" class="dropdown-item">
                    @lang('users::view.my_profile')
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ fr_route('drivers.profile', ['id' => $item_id]) }}" class="dropdown-item">
                    @lang('users::view.account_settings')
                </a>
                <div class="dropdown-divider"></div>
            @endif


            {{-- staff --}}
            @if ($user_role == $auth_staff)
                @php
                    $item_id = Modules\Cargo\Entities\Staff::where('user_id', auth()->user()->id)
                        ->pluck('id')
                        ->first();
                @endphp
                <a href="{{ fr_route('staffs.show', ['staff' => $item_id]) }}" class="dropdown-item">
                    @lang('users::view.my_profile')
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ fr_route('staffs.profile', ['id' => $item_id]) }}" class="dropdown-item">
                    @lang('users::view.account_settings')
                </a>
                <div class="dropdown-divider"></div>
            @endif
            @endcheckModule
            <form id="formLogout" method="POST" action="{{ fr_route('logout') }}">
                @csrf
                <button type="submit" class="dropdown-item">@lang('view.sign_out')</button>
            </form>
            <div class="dropdown-divider"></div>
        </div>
    </li>

    <!-- Language Dropdown Menu -->
    <li class="nav-item dropdown">
        @if (check_module('Localization'))
            <a class="nav-link" data-toggle="dropdown" href="#">
                @if (Config::get('current_lang_image'))
                    <img src="{{ Config::get('current_lang_image') }}" alt="" class="flag-icon mx-1" />
                @endif{{ LaravelLocalization::getCurrentLocaleName() }}
            </a>
            <div class="dropdown-menu dropdown-menu-right p-0">
                @foreach (Modules\Localization\Entities\Language::all() as $key => $language  )
                {{-- {{ dd($language) }} --}}
                    <a href="{{ LaravelLocalization::getLocalizedURL($language->code) }}" class="dropdown-item">
                        @if ($language->imageUrl)
                            <img class="flag-icon mr-2" src="{{ $language->imageUrl }}" alt="" />
                        @endif {{ $language->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </li>

    <li class="nav-item">
        <a class="nav-link" data-widget="fullscreen" href="#" role="button">
            <i class="fas fa-expand-arrows-alt"></i>
        </a>
    </li>
</ul>

<!-- Search Styles and Scripts -->
<style>
.search-container {
    position: relative;
}

.search-input {
    border-radius: 25px;
    border: 1px solid #ddd;
    padding: 8px 20px;
    font-size: 14px;
    height: 42px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.search-input:focus {
    border-color: #007bff;
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.15);
    outline: none;
}

.search-input-container {
    position: relative;
}



.search-results-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1050;
    max-height: 500px;
    overflow: hidden;
}

.search-results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
}

.search-results-content {
    max-height: 400px;
    overflow-y: auto;
}

.search-result-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background-color 0.2s;
}

.search-result-item:hover {
    background-color: #f8f9fa;
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
}

.search-result-content h6 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}

.search-result-content p {
    margin: 0;
    font-size: 12px;
    color: #666;
}

.search-results-footer {
    padding: 12px 16px;
    border-top: 1px solid #eee;
    background: #f8f9fa;
}

.search-category {
    padding: 8px 16px;
    background: #f8f9fa;
    font-weight: 600;
    font-size: 12px;
    color: #666;
    border-bottom: 1px solid #eee;
}

.loading-spinner {
    text-align: center;
    padding: 20px;
    color: #666;
}

.no-results {
    text-align: center;
    padding: 20px;
    color: #666;
}

@media (max-width: 991.98px) {
    .search-container {
        display: none;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('searchResults');
    const searchResultsContent = document.getElementById('searchResultsContent');
    const closeSearchResults = document.getElementById('closeSearchResults');
    const viewAllResults = document.getElementById('viewAllResults');
    
    // Only initialize search if elements exist (user is authenticated)
    if (!searchInput || !searchResults) {
        return;
    }
    
    let searchTimeout;
    let currentQuery = '';

    // Search input event
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        currentQuery = query;
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            hideSearchResults();
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performLiveSearch(query);
        }, 300);
    });



    // Enter key press
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const query = this.value.trim();
            if (query) {
                window.location.href = `{{ route('search.index') }}?q=${encodeURIComponent(query)}`;
            }
        }
    });

    // Close search results
    closeSearchResults.addEventListener('click', hideSearchResults);

    // View all results
    viewAllResults.addEventListener('click', function(e) {
        e.preventDefault();
        const query = searchInput.value.trim();
        if (query) {
            window.location.href = `{{ route('search.index') }}?q=${encodeURIComponent(query)}`;
        }
    });

    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            hideSearchResults();
        }
    });

    function performLiveSearch(query) {
        if (query !== currentQuery) return; // Prevent race conditions
        
        searchResultsContent.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
        showSearchResults();

        fetch(`{{ route('search.live') }}?q=${encodeURIComponent(query)}`)
            .then(response => {
                if (response.status === 401) {
                    // User not authenticated, redirect to login
                    window.location.href = '{{ route("signin") }}';
                    return;
                }
                return response.json();
            })
            .then(data => {
                if (query !== currentQuery) return; // Prevent race conditions
                
                if (data && data.success) {
                    displaySearchResults(data.results, query);
                } else {
                    searchResultsContent.innerHTML = '<div class="no-results">Error loading results</div>';
                }
            })
            .catch(error => {
                if (query !== currentQuery) return;
                searchResultsContent.innerHTML = '<div class="no-results">Error loading results</div>';
            });
    }

    function displaySearchResults(results, query) {
        if (Object.keys(results).length === 0) {
            searchResultsContent.innerHTML = '<div class="no-results">No results found</div>';
            return;
        }

        let html = '';
        
        Object.keys(results).forEach(category => {
            const section = results[category];
            const items = section.data.slice(0, 3); // Show max 3 items per category
            
            html += `<div class="search-category">
                <i class="${section.icon} me-1"></i> ${section.title} (${section.data.length})
            </div>`;
            
            items.forEach(item => {
                html += `
                <div class="search-result-item" onclick="window.location.href='${item.url}'">
                    <div class="d-flex align-items-center">
                        <div class="search-result-icon bg-${section.color} bg-opacity-10">
                            <i class="${item.icon} text-${section.color}"></i>
                        </div>
                        <div class="search-result-content flex-grow-1">
                            <h6>${item.title}</h6>
                            <p>${item.subtitle}</p>
                        </div>
                    </div>
                </div>`;
            });
        });

        searchResultsContent.innerHTML = html;
        viewAllResults.href = `{{ route('search.index') }}?q=${encodeURIComponent(query)}`;
    }

    function showSearchResults() {
        searchResults.style.display = 'block';
    }

    function hideSearchResults() {
        searchResults.style.display = 'none';
    }
});
</script>
