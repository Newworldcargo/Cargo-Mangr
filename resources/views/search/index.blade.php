@extends('cargo::adminLte.layouts.master')
@section('pageTitle', 'Search Results')
@section('content')

<div class="search-results-page">
    <!-- Compact Header -->
    <div class="search-header py-3 border-bottom">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-12">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-search text-primary me-2"></i>
                        <h4 class="mb-0 text-muted">
                            @if(request('user_id'))
                                User Shipments
                                @if($query)
                                    <span class="text-dark">for "{{ $query }}"</span>
                                @endif
                            @else
                                Search Results
                                @if($query)
                                    <span class="text-dark">for "{{ $query }}"</span>
                                @endif
                            @endif
                        </h4>
                    </div>
                </div>
            </div>
            <form method="get" action="{{ route('search.index') }}" class="d-flex mt-3" style="gap:8px">
                <label for="search-page-query" class="sr-only">Search shipments and consignments</label>
                <input id="search-page-query" name="q" type="search" value="{{ $query }}" maxlength="100" minlength="2" required class="form-control" placeholder="Reference, phone, customer or receipt">
                <button class="btn btn-primary" type="submit"><i class="fas fa-search" aria-hidden="true"></i><span class="sr-only">Search</span></button>
            </form>
        </div>
    </div>

    <!-- Search Results Content -->
    <div class="search-content py-4">
        <div class="container-fluid">
            @if($query || request('user_id'))
                @if(empty($results))
                    <div class="text-center py-4">
                        <div class="empty-state">
                            <i class="fas fa-search fa-2x text-muted mb-3"></i>
                            <h5 class="text-muted mb-2">No results found</h5>
                            <p class="text-muted small">Try searching with different keywords or check your spelling.</p>
                        </div>
                    </div>
                @else
                    <!-- Results Summary -->
                    <div class="results-summary mb-4">
                        @php
                            $totalResults = 0;
                            foreach($results as $section) {
                                $totalResults += count($section['data']);
                            }
                        @endphp
                        <div class="d-flex align-items-center">
                            <span class="badge bg-light text-dark me-2">{{ $totalResults }}</span>
                            <small class="text-muted">
                                result{{ $totalResults !== 1 ? 's' : '' }} shown across {{ count($results) }} categor{{ count($results) !== 1 ? 'ies' : 'y' }}
                            </small>
                        </div>
                    </div>

                    <!-- Results by Category -->
                    @foreach($results as $category => $section)
                        <div class="result-category mb-4">
                            <div class="category-header mb-3 category-{{ $section['color'] }}">
                                <div class="d-flex align-items-center">
                                    <div class="category-icon me-2">
                                        <i class="{{ $section['icon'] }} text-{{ $section['color'] }}"></i>
                                    </div>
                                    <h6 class="mb-0 text-dark">{{ $section['title'] }}</h6>
                                    <span class="badge bg-{{ $section['color'] }} ms-2">{{ count($section['data']) }}</span>
                                </div>
                            </div>
                            
                            <div class="results-grid">
                                @foreach($section['data'] as $item)
                                    <div class="result-item">
                                        <div class="result-card result-card-{{ $section['color'] }}">
                                            <div class="result-header">
                                                <div class="result-icon result-icon-{{ $section['color'] }}">
                                                    <i class="{{ $item['icon'] }}"></i>
                                                </div>
                                                <div class="result-title">
                                                    <a href="{{ $item['url'] }}" class="result-link result-link-{{ $section['color'] }}">
                                                        {{ $item['title'] }}
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="result-body">
                                                <div class="result-subtitle">{{ $item['subtitle'] }}</div>
                                                {{-- <div class="result-description">{{ $item['description'] }}</div> --}}
                                            </div>
                                            <div class="result-footer">
                                                <span class="result-type result-type-{{ $section['color'] }}">{{ $item['type'] }}</span>
                                                <span class="result-date">Added {{ $item['date'] }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-3">{{ $section['pagination']->links('pagination::simple-bootstrap-4') }}</div>
                        </div>
                    @endforeach
                @endif
            @else
                <div class="text-center py-4">
                    <div class="empty-state">
                        <i class="fas fa-search fa-2x text-muted mb-3"></i>
                        <h5 class="text-muted mb-2">Start Searching</h5>
                        <p class="text-muted small">Enter a reference, phone number, customer name or receipt number.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
.search-results-page {
    background-color: #f8f9fa;
    min-height: calc(100vh - 60px);
}

.search-header {
    background: white;
    border-bottom: 1px solid #e9ecef;
}

.search-header h4 {
    font-size: 1.1rem;
    font-weight: 500;
    overflow-wrap: anywhere;
    min-width: 0;
}

.search-content {
    background: white;
    margin: 0 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.results-summary {
    padding: 0 20px;
}

.results-summary .badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.result-category {
    padding: 0 20px;
}

.category-header {
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: 0.75rem;
}

.category-header h6 {
    font-size: 0.9rem;
    font-weight: 600;
}

.category-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 4px;
}

.category-icon i {
    font-size: 0.8rem;
}

.results-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.result-item {
    transition: transform 0.2s ease;
}

.result-item:hover {
    transform: translateY(-2px);
}

.result-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 1rem;
    transition: box-shadow 0.2s ease;
}

.result-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #007bff;
}

.result-header {
    display: flex;
    align-items: center;
    margin-bottom: 0.75rem;
}

.result-icon {
    width: 32px;
    height: 32px;
    background: #f8f9fa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
}

.result-icon i {
    font-size: 0.8rem;
    color: #6c757d;
}

.result-title {
    flex: 1;
    min-width: 0;
    overflow-wrap: anywhere;
}

.result-link {
    color: #007bff;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
}

.result-link:hover {
    color: #0056b3;
    text-decoration: underline;
}

.result-body {
    margin-bottom: 0.75rem;
}

.result-subtitle {
    font-size: 0.8rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.result-description {
    font-size: 0.75rem;
    color: #495057;
    line-height: 1.4;
}

.result-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.5rem;
    border-top: 1px solid #f0f0f0;
}

.result-type {
    font-size: 0.7rem;
    background: #e9ecef;
    color: #495057;
    padding: 0.2rem 0.5rem;
    border-radius: 3px;
    font-weight: 500;
}

.result-date {
    font-size: 0.7rem;
    color: #6c757d;
}

.empty-state {
    padding: 2rem;
}

.empty-state i {
    opacity: 0.5;
}

.empty-state h5 {
    font-size: 1rem;
}

.empty-state p {
    font-size: 0.85rem;
}

/* Color Themes for Categories */

/* Consignments - Blue Theme */
.category-primary {
    border-left: 4px solid #007bff;
    padding-left: 1rem;
}

.result-card-primary {
    border-left: 3px solid #007bff;
}

.result-card-primary:hover {
    border-color: #0056b3;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
}

.result-icon-primary {
    background: rgba(0, 123, 255, 0.1);
}

.result-icon-primary i {
    color: #007bff;
}

.result-link-primary {
    color: #007bff;
}

.result-link-primary:hover {
    color: #0056b3;
}

.result-type-primary {
    background: rgba(0, 123, 255, 0.1);
    color: #007bff;
}

/* Shipments - Green Theme */
.category-success {
    border-left: 4px solid #28a745;
    padding-left: 1rem;
}

.result-card-success {
    border-left: 3px solid #28a745;
}

.result-card-success:hover {
    border-color: #1e7e34;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
}

.result-icon-success {
    background: rgba(40, 167, 69, 0.1);
}

.result-icon-success i {
    color: #28a745;
}

.result-link-success {
    color: #28a745;
}

.result-link-success:hover {
    color: #1e7e34;
}

.result-type-success {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

/* Users - Purple Theme */
.category-info {
    border-left: 4px solid #17a2b8;
    padding-left: 1rem;
}

.result-card-info {
    border-left: 3px solid #17a2b8;
}

.result-card-info:hover {
    border-color: #117a8b;
    box-shadow: 0 4px 12px rgba(23, 162, 184, 0.15);
}

.result-icon-info {
    background: rgba(23, 162, 184, 0.1);
}

.result-icon-info i {
    color: #17a2b8;
}

.result-link-info {
    color: #17a2b8;
}

.result-link-info:hover {
    color: #117a8b;
}

.result-type-info {
    background: rgba(23, 162, 184, 0.1);
    color: #17a2b8;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .search-content {
        margin: 0 10px;
    }
    
    .results-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .result-category {
        padding: 0 15px;
    }
    
    .results-summary {
        padding: 0 15px;
    }
}

@media (max-width: 576px) {
    .search-header h4 {
        font-size: 1rem;
    }
    
    .result-card {
        padding: 0.75rem;
    }
    
    .result-header {
        margin-bottom: 0.5rem;
    }
    
    .result-body {
        margin-bottom: 0.5rem;
    }
}
</style>

@endsection
