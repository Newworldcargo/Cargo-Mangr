<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newworld Cargo - Track Your Shipment</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#012642',
                            300: '#012642',
                            400: '#f7c600',
                            500: '#012642',
                            600: '#f7c600',
                            700: '#f7c600',
                            800: '#075985',
                            900: '#012642',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .content-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        .progress-bar {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #012642 0%, #f7c600 100%);
            transition: width 0.3s ease;
        }
        .stage-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e5e7eb;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #e5e7eb;
            position: relative;
        }
        .stage-dot.completed {
            background: #f7c600;
            box-shadow: 0 0 0 2px #f7c600;
        }
        .stage-dot.completed::after {
            content: '✓';
            position: absolute;
            top: -8px;
            left: -8px;
            right: -8px;
            bottom: -8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 12px;
            font-weight: bold;
        }
        .stage-dot.active {
            background: #012642;
            box-shadow: 0 0 0 2px #012642;
        }
        .tracking-bg {
            background: 
                linear-gradient(135deg, rgba(68, 65, 52, 0.66) 0%, rgba(0, 0, 0, 0.75) 100%),
                url('https://images.pexels.com/photos/3140204/pexels-photo-3140204.jpeg') no-repeat center center;
            background-size: cover;
            min-height: 100vh;
        }

        .ad-container {
            background: rgb(255, 255, 255);
            backdrop-filter: blur(10px);
            /* border: 1px solid rgba(255, 255, 255, 0.1); */
            /* border-radius: 12px; */
            padding: 20px;
            color: white;
        }
        .sticky-sidebar {
            position: sticky;
            top: 20px;
        }
        .scrollable-content {
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }
        .scrollable-content::-webkit-scrollbar {
            width: 6px;
        }
        .scrollable-content::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        .scrollable-content::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 10px;
        }
        .nav-gradient {
            background: linear-gradient(90deg, #012642 0%, #011c33 100%);
        }
        .timeline-item {
            position: relative;
            padding-left: 30px;
            margin-bottom: 20px;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 2px;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
        }
        .timeline-item:last-child:before {
            height: 50%;
        }
        .timeline-dot {
            position: absolute;
            left: -6px;
            top: 0;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #f7c600;
            z-index: 1;
        }

        .nav-gradient {
            background: linear-gradient(90deg, #012642 0%, #011c33 100%);
        }
        .search-container {
            transition: all 0.3s ease;
        }
        .search-container.expanded {
            width: 100%;
            max-width: 800px;
        }
        .search-input {
            transition: all 0.3s ease;
        }
        .search-input:focus {
            box-shadow: 0 0 0 3px rgba(247, 198, 0, 0.2);
        }
        .mobile-menu {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        .mobile-menu.open {
            transform: translateX(0);
        }
        .overlay {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .overlay.active {
            opacity: 1;
            visibility: visible;
        }
    </style>
</head>
<body class="tracking-bg">
    <!-- Navigation -->
    <nav class="nav-gradient shadow-lg sticky top-0 z-50">

        <!-- Search Bar Section -->
        <div class="border-t border-blue-800/30">
            <div class="container mx-auto px-4 py-4">
                <div class="flex flex-col md:flex-row items-center justify-between space-y-4 md:space-y-0">
                    <!-- Logo and Mobile Menu Button -->
                    <div class="">
                        <img width="110" src="https://admin.newworldcargo.com/assets/lte/cargo-logo.svg">
                    </div>
                    <!-- Search Bar -->
                    <div class="w-full md:w-2/3 lg:w-3/4">
                        <form action="{{ route('shipments.tracking') }}" method="GET" class="relative">
                            <div class="flex items-center bg-white/10 backdrop-blur-sm rounded-lg border border-white/20 overflow-hidden">
                                <div class="pl-4 pr-2 py-3 text-white">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input
                                    type="text"
                                    name="code"
                                    class="search-input w-full bg-transparent border-none text-white placeholder-white/70 focus:ring-0 py-3 px-2"
                                    placeholder="Enter tracking number (e.g. {{ __('cargo::view.example_SH00001') }})"
                                >
                                <button
                                    type="submit"
                                    class="bg-yellow-500 hover:bg-yellow-600 text-gray-900 font-semibold py-3 px-6 transition-colors flex items-center"
                                >
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    {{ __('cargo::view.search') }}
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="flex items-center space-x-4">
                        <button class="text-white hover:text-yellow-400 transition-colors flex items-center text-sm">
                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Track History
                        </button> --}}
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content with 3-Column Layout -->
    <div class="mx-2 px-0 py-2">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-1">
            <!-- Left Sidebar - Ads -->
            <div class="lg:col-span-2 sticky-sidebar">
                <!-- Ad 2 -->
                <div class="ad-container mb-4" style="background: linear-gradient(rgba(1, 38, 66, 0.11), rgba(1, 28, 51, 0.9)), url('https://i.makeagif.com/media/8-10-2023/Fmt7A9.gif'); background-size: cover; background-position: center;">
                    {{-- <div class="absolute top-3 right-3 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">LIMITED</div> --}}
                    <h3 class="text-lg font-bold mb-2 text-white pt-8">Sea Freight Special</h3>
                    <p class="text-sm mb-4 text-white/90">Get your biggest sea shipments this month</p>
                    {{-- <button class="w-full bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded-lg font-semibold transition-all transform hover:scale-105">
                        Book Now
                    </button> --}}
                </div>

                <!-- Ad 3 -->
                <div class="ad-container mb-4" style="background: linear-gradient(rgba(255, 255, 255, 0), rgba(1, 28, 51, 0.21)), url('https://images.unsplash.com/photo-1566576721346-d4a3b4eaeb55?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80'); background-size: cover; background-position: center;">
                    <div class="absolute top-3 right-3 bg-green-500 text-white text-xs font-bold px-2 py-1 rounded">NEW</div>
                    <h3 class="text-lg font-bold mb-2 text-white pt-8">Warehouse Solutions</h3>
                    <p class="text-sm mb-4 text-white/90">Secure storage with automated inventory management</p>
                    <button class="w-full bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-lg font-semibold transition-all transform hover:scale-105">
                        Learn More
                    </button>
                </div>
                <div class="ad-container mb-4 relative overflow-hidden" style="background: linear-gradient(rgba(255, 255, 255, 0), rgba(1, 28, 51, 0.21)), url('{{ asset('parcel.png') }}'); background-size: cover; background-position: center;">
                    <div class="absolute top-3 right-3 bg-green-500 text-white text-xs font-bold px-2 py-1 rounded">NEW</div>
                    <h3 class="text-lg font-bold mb-2 text-white pt-8">Warehouse Solutions</h3>
                    <p class="text-sm mb-4 text-white/90">Secure storage with automated inventory management</p>
                    <button class="w-full bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-lg font-semibold transition-all transform hover:scale-105">
                        Learn More
                    </button>
                </div>
            </div>

            <!-- Middle Column - Tracking Content (Scrollable) -->
            <div class="lg:col-span-8">
                <div class="scrollable-content">
                    @if(!isset($track_map))
                        <div class="content-card rounded-lg shadow-md overflow-hidden">
                            <div class="p-8">
                                <div class="text-center mb-8">
                                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-600 rounded-lg mb-6">
                                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                        </svg>
                                    </div>
                                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Track Your Shipment</h1>
                                    <p class="text-gray-600">Enter your tracking number to get started</p>
                                </div>
                                <form action="{{ route('shipments.tracking') }}" method="GET" class="space-y-6">
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <input
                                            type="text"
                                            name="code"
                                            class="block w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-400 focus:border-primary-400 transition-all text-gray-800 bg-white"
                                            placeholder="{{ __('cargo::view.example_SH00001') }}"
                                        >
                                    </div>
                                    <button
                                        type="submit"
                                        class="w-full bg-primary-600 hover:bg-primary-700 text-white py-3 px-6 rounded-lg font-semibold transition-all focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                                    >
                                        <span class="flex items-center justify-center">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            {{ __('cargo::view.search') }}
                                        </span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @elseif(isset($error))
                        <div class="content-card rounded-lg shadow-md overflow-hidden">
                            <div class="p-8">
                                <div class="text-center mb-8">
                                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-600 rounded-lg mb-6">
                                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                        </svg>
                                    </div>
                                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Track Your Shipment</h1>
                                    <p class="text-gray-600">Enter your tracking number to get started</p>
                                </div>
                                @if($error)
                                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            <p class="text-red-700 font-medium">{{ $error }}</p>
                                        </div>
                                    </div>
                                @endif
                                <form action="{{ route('shipments.tracking') }}" method="GET" class="space-y-6">
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <input
                                            type="text"
                                            name="code"
                                            class="block w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-400 focus:border-primary-400 transition-all text-gray-800 bg-white"
                                            placeholder="{{ __('cargo::view.example_SH00001') }}"
                                        >
                                    </div>
                                    <button
                                        type="submit"
                                        class="w-full bg-primary-600 hover:bg-primary-700 text-white py-3 px-6 rounded-lg font-semibold transition-all focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                                    >
                                        <span class="flex items-center justify-center">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            {{ __('cargo::view.search') }}
                                        </span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        @php
                            // $allStages = [ ... ]; // Get all stages from DB or config
                            $completedCount = 0;
                            foreach ($track_map as $log) {
                                if ($log[1] !== null) $completedCount++;
                            }
                            $stages = \App\Models\TrackingStage::where('cargo_type', $container->cargo_type)
                                ->orderBy('order')
                                ->get();
                        @endphp
                        
                        <!-- Header Section -->
                        <div class="text-center mb-8">
                            <h1 class="text-3xl font-bold text-white mb-2">Tracking Information</h1>
                            <p class="text-xl text-white">#{{ $model->code ?? 'Unknown' }}</p>

                            @if($model)
                                <div class="inline-flex items-center space-x-2 bg-green-50/90 backdrop-blur-sm px-4 py-2 rounded-full border border-green-200 mt-4">
                                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                    <span class="text-green-700 font-medium uppercase"> Active Tracking | {{ $container->cargo_type }} Cargo</span>
                                    <span class="text-yellow-900"> * </small>
                                    <span class="text-yellow-700"> {{ $model->client->name }}</small>
                                </div>
                            @endif
                        </div>

                        <!-- Tracking Information -->
                        @if($model && isset($track_map))
                            <div class="content-card rounded-lg p-8 mb-8">
                                <!-- Progress Bar -->
                                <div class="mb-8">
                                    <div class="flex justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-600">Delivery Progress</span>
                                        <span class="text-sm font-medium text-primary-600">{{ count(array_filter($track_map, function($item) { return $item[1] !== null; })) }}/6 Stages</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-bar-fill" style="width: {{ (count(array_filter($track_map, function($item) { return $item[1] !== null; })) / 6) * 100 }}%"></div>
                                    </div>
                                </div>
                                
                                <!-- Tracking Timeline -->
                                <div class="space-y-8">
                                    @foreach(array_reverse($track_map) as $index => $log)
                                        @php
                                            $isCompleted = $log[1] !== null;
                                            $date = $log[1];
                                            $formattedDate = $date instanceof \Carbon\Carbon ? $date->format('M j, Y g:i A') : ($date ? \Carbon\Carbon::parse($date)->format('M j, Y g:i A') : null);
                                            $timeAgo = $date instanceof \Carbon\Carbon ? $date->diffForHumans() : ($date ? \Carbon\Carbon::parse($date)->diffForHumans() : null);
                                        @endphp
                                
                                        @if($isCompleted)
                                        <div class="timeline-item">
                                            <div class="timeline-dot"></div>
                                            <div class="bg-blue-900 rounded-lg p-6  @if($loop->first) slow-pulse @endif">
                                                <p class="font-medium text-yellow-500 text-lg mb-1">
                                                    {{ $container->consignment_code }} @if ($container->cargo_type == 'sea')
                                                    Container
                                                    @endif, 
                                                    {{ $log[0] }}
                                                </p>
                                
                                                <div class="text-white text-sm mb-3">
                                                    @if ($container->cargo_type == 'sea')
                                                        <div>Container: {{ $container->consignment_code }}</div>
                                                        <div>Shipment (Parcel) Code: {{$model->code}}</div>
                                                    @else
                                                        <div>Shipment (Parcel) Code: {{$model->code}}</div>
                                                    @endif
                                                </div>
                                
                                                @if($isCompleted)
                                                    <div class="flex items-center text-sm text-white">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        {{ $formattedDate ?? 'Not Yet' }}
                                                        <span class="mx-2">•</span>
                                                        <span>{{ $timeAgo }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                                
                                <style>
                                @keyframes slowPulse {
                                  0%, 100% {
                                    transform: scale(1);
                                    box-shadow: 0 0 0 rgba(255, 215, 0, 0);
                                  }
                                  50% {
                                    transform: scale(1.03);
                                    box-shadow: 0 0 20px rgba(255, 215, 0, 0.6); /* glowing highlight */
                                  }
                                }
                                
                                .slow-pulse {
                                  animation: slowPulse 3s ease-in-out infinite;
                                }
                                </style>                                
                                
                            </div>

                            <!-- Search Another Shipment -->
                            <div class="content-card rounded-lg p-8">
                                <div class="text-center mb-6">
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">Track Another Shipment</h3>
                                    <p class="text-gray-600">Enter a different tracking code to search</p>
                                </div>

                                <form action="{{ route('shipments.tracking') }}" method="GET" class="flex flex-col sm:flex-row gap-4">
                                    <div class="flex-1 relative">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <input
                                            type="text"
                                            name="code"
                                            class="block w-full pl-12 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-400 focus:border-primary-400 transition-all text-gray-700 bg-white"
                                            placeholder="{{ __('cargo::view.example_SH00001') }}"
                                        >
                                    </div>
                                    <button
                                        type="submit"
                                        class="px-8 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-lg transition-all focus:outline-none focus:ring-2 focus:ring-primary-500 whitespace-nowrap"
                                    >
                                        <span class="flex items-center">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            {{ __('cargo::view.search') }}
                                        </span>
                                    </button>
                                </form>
                            </div>
                        @else
                            <div class="content-card rounded-lg p-12 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-lg mb-6">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-800 mb-2">No Tracking Data Available</h3>
                                <p class="text-gray-600">We couldn't find any tracking information for this shipment</p>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Right Sidebar - Ads -->
            <div class="lg:col-span-2 sticky-sidebar">
                <!-- Ads -->
                

                <div class="ad-container" id="adBox" 
                        style="background: linear-gradient(rgba(255, 255, 255, 0), rgba(255, 255, 255, 0)), 
                            url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSqQmuGK9v5W6wXMkK0PwIvfxtBK6yI3AW2aQ&s'); 
                            background-size: cover; 
                            background-position: center;">
                    <div style="height:30vh"></div>
                </div>

                <div class="ad-container mb-4" style="background: #f7c600; border-left: 4px solid #012642;">
                    <div class="absolute top-3 right-3 bg-white text-yellow-700 text-xs font-bold px-2 py-1 rounded-full shadow">NEW</div>
                    <h3 class="text-lg font-bold mb-2 text-gray-900">Download Our App</h3>
                    <p class="text-sm mb-4 text-gray-700">Track shipments on the go with real-time notifications</p>
                    <div class="flex space-x-2">
                        <div class="flex-1 bg-gray-900 hover:bg-gray-800 text-white py-2 px-3 rounded-lg text-xs font-semibold transition-all transform hover:scale-105 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.05 12.04C17.05 9.09 19.43 7.5 19.5 7.5C18.5 5.5 16.57 5.37 15.75 5.37C14.27 5.37 13.05 6.3 12.25 6.3C11.41 6.3 10.32 5.38 9.03 5.38C6.63 5.38 4.5 7 4.5 10.5C4.5 14.5 8.5 18.5 12.25 18.5C13.5 18.5 14.5 17.5 15.75 17.5C17 17.5 17.75 18.5 19.25 18.5C22.5 18.5 23.5 14.5 23.5 12.5C23.5 12.04 23.45 11.58 23.35 11.12C22.6 11.04 20.5 9.5 20.5 7.5C20.5 6.5 20.95 5.5 21.5 4.5C20.15 3.5 18.5 3.5 17.5 3.5C16.05 3.5 14.75 4.5 14 4.5C13.2 4.5 12.05 3.5 10.75 3.5C8.65 3.5 6.5 5 6.5 8.5C6.5 9.5 6.75 10.5 7.25 11.5C7.75 12.5 8.5 13.5 9.25 14.5C10 15.5 10.75 16.5 11.25 17.5C11.75 18.5 12.25 19.5 12.75 20.5C13.25 21.5 13.75 22.5 14.25 23.5H17.05V12.04Z"/>
                            </svg>
                            Coming Soon
                        </div>
                    </div>
                </div>

                <div class="ad-container mb-2" style="background: linear-gradient(rgba(255, 255, 255, 0), rgba(255, 255, 255, 0)), url('https://www.bringeraircargo.com/wp-content/uploads/2020/05/ezgif.com-video-to-gif-3.gif'); background-size: cover; background-position: center;">
                    <div style="height:24vh">
                    </div>
                </div>
                
                <script>
                    // List of image URLs
                    const adImages = [
                        "/ads/ad1.png",
                        "/ads/111.jpg"
                    ];
                
                    const adBox = document.getElementById("adBox");
                
                    // Function to set random image
                    function setRandomAd() {
                    const randomImg = adImages[Math.floor(Math.random() * adImages.length)];
                    adBox.style.backgroundImage = `linear-gradient(rgba(255, 255, 255, 0), rgba(255, 255, 255, 0)), url('${randomImg}')`;
                    }
                
                    // Change on load
                    setRandomAd();
                
                    // Change every 5 seconds (optional)
                    setInterval(setRandomAd, 5000);
                </script>
           
            </div>
        </div>
    </div>
</body>
</html>
