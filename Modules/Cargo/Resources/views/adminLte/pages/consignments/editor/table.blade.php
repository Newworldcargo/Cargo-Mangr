<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<style>
    .filter-hidden,
    .page-hidden {
        display: none !important;
    }
</style>
@php
    $consignmentCollection = $consignments instanceof \Illuminate\Support\Collection
        ? $consignments
        : (method_exists($consignments, 'items') ? collect($consignments->items()) : collect($consignments));
    $normalize = function ($value) {
        if (is_null($value) || $value === '') {
            return null;
        }
        $value = trim($value);
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    };
    $statusOptions = $consignmentCollection->map(function ($item) use ($normalize) {
        $statusValue = $item->status ?? $item->current_status ?? null;
        $normalized = $normalize($statusValue);
        return $normalized ? [
            'value' => $normalized,
            'label' => strtoupper($statusValue),
        ] : null;
    })->filter()->unique('value')->values();
    $sourceOptions = $consignmentCollection->map(function ($item) use ($normalize) {
        $sourceValue = $item->source ?? 'China';
        $normalized = $normalize($sourceValue);
        return $normalized ? [
            'value' => $normalized,
            'label' => $sourceValue,
        ] : null;
    })->filter()->unique('value')->values();
    $destinationOptions = $consignmentCollection->map(function ($item) use ($normalize) {
        $destinationValue = $item->destination ?? 'Zambia';
        $normalized = $normalize($destinationValue);
        return $normalized ? [
            'value' => $normalized,
            'label' => $destinationValue,
        ] : null;
    })->filter()->unique('value')->values();
@endphp
<div class="w-full bg-white px-3 shadow-sm rounded-lg overflow-hidden">
  <div class="bg-gradient-to-r text-dark py-4 flex justify-between items-center">
    {{-- <h2 class="text-lg font-bold text-primary tracking-tight">Consignment Tracking System</h2> --}}
    <div class="flex space-x-2">
      @can('delete-consignments')
        <button id="bulk-delete-btn"
            class="btnclicky btn-sm d-flex item-center justify-content-center bg-red-500 text-white rounded hover:bg-red-600 transition disabled:opacity-50"
            disabled>
            <span> <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
            <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
            <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
            </svg></span>
        </button>
      @endcan
    </div>
  </div>

  <div class="px-3 py-4 bg-gray-50 border-b border-gray-200 flex flex-wrap gap-4 items-end justify-between">
    <div class="flex items-center space-x-2">
      <span class="text-sm font-medium text-gray-600">View:</span>
      <button type="button" data-view="table"
        class="view-toggle inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 text-gray-600 bg-white hover:bg-gray-100 transition focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1"
        aria-pressed="true">
        Table
      </button>
      <button type="button" data-view="list"
        class="view-toggle inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 text-gray-600 bg-white hover:bg-gray-100 transition focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1"
        aria-pressed="false">
        List
      </button>
      <button type="button" data-view="grid"
        class="view-toggle inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 text-gray-600 bg-white hover:bg-gray-100 transition focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1"
        aria-pressed="false">
        Grid
      </button>
    </div>

    <div class="flex flex-wrap gap-3">
      <div>
        <label for="filter-status" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Status</label>
        <select id="filter-status" class="consignment-filter block w-40 text-sm border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary">
          <option value="all">All Statuses</option>
          @foreach($statusOptions as $statusOption)
            <option value="{{ $statusOption['value'] }}">{{ $statusOption['label'] }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label for="filter-source" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Source</label>
        <select id="filter-source" class="consignment-filter block w-40 text-sm border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary">
          <option value="all">All Sources</option>
          @foreach($sourceOptions as $sourceOption)
            <option value="{{ $sourceOption['value'] }}">{{ $sourceOption['label'] }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label for="filter-destination" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Destination</label>
        <select id="filter-destination" class="consignment-filter block w-44 text-sm border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary">
          <option value="all">All Destinations</option>
          @foreach($destinationOptions as $destinationOption)
            <option value="{{ $destinationOption['value'] }}">{{ $destinationOption['label'] }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  <!-- Table Container -->
  <div id="table-view-container" class="overflow-x-auto">
    <table id="consignments-table" class="w-full table-auto">
      <thead>
        <tr class="bg-gray-100 text-gray-700 uppercase text-xs border-b border-gray-200">
            <th class="px-6 py-3">
                <input type="checkbox" class="bulk-checkbox" id="select-all">
            </th>
            <th class="px-6 py-3 font-semibold tracking-wider text-left">CODE</th>
            <th class="px-6 py-3 font-semibold tracking-wider text-left">TYPE</th>
        <th class="px-6 py-3 font-semibold tracking-wider text-left">SHIPMENT PARCELS</th>
            <th class="px-6 py-3 font-semibold tracking-wider text-left">CONSIGNEE</th>
            <th class="px-6 py-3 font-semibold tracking-wider text-left">SOURCE</th>
            <th class="px-6 py-3 font-semibold tracking-wider text-left">DESTINATION</th>
            <th class="px-6 py-3 font-semibold tracking-wider text-left">CONSIGNMENT DATE</th>
            <th class="px-6 py-3 font-semibold tracking-wider text-left">STATUS</th>
            <th class="px-6 py-3 font-semibold tracking-wider text-center">ACTIONS</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        @forelse($consignments as $consignment)
        @php
            $statusValue = $normalize($consignment->status ?? $consignment->current_status ?? '');
            $sourceDisplay = $consignment->source ?? 'China';
            $destinationDisplay = $consignment->destination ?? 'Zambia';
            $sourceValue = $normalize($sourceDisplay);
            $destinationValue = $normalize($destinationDisplay);
            $cargoType = $normalize($consignment->cargo_type ?? '');
        @endphp
        <tr class="hover:bg-gray-50 transition-colors duration-150"
            data-status="{{ $statusValue }}"
            data-source="{{ $sourceValue }}"
            data-destination="{{ $destinationValue }}"
            data-cargo-type="{{ $cargoType }}">
            <td class="px-6 py-4 text-sm">
                <input type="checkbox" class="row-checkbox" value="{{ $consignment->id }}">
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                {{ $consignment->consignment_code ?? 'Unspecified' }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full
                        @if($cargoType === 'air') bg-sky-100 text-sky-600
                        @elseif($cargoType === 'sea') bg-indigo-100 text-indigo-600
                        @else bg-gray-100 text-gray-500 @endif">
                        @if($cargoType === 'air')
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                                <path d="M21 14.5a1 1 0 0 1-1.32.95L13 13.8v3.45l1.62 1.62a1 1 0 0 1-1.41 1.41l-1.21-1.21-1.21 1.21a1 1 0 0 1-1.41-1.41L11 17.25V13.8l-6.68 1.65A1 1 0 0 1 3 14.5V13a1 1 0 0 1 .68-.95L11 9.8V5.41a1 1 0 0 1 2 0V9.8l7.32 2.25a1 1 0 0 1 .68.95z"/>
                            </svg>
                        @elseif($cargoType === 'sea')
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                                <path d="M4 15v-4a1 1 0 0 1 .74-.97l7-2a1 1 0 0 1 .52 0l7 2A1 1 0 0 1 20 11v4c0 3.19-2.34 5-8 5s-8-1.81-8-5zm2 0c0 1.56 1.4 3 6 3s6-1.44 6-3v-3.19l-6-1.72-6 1.72z"/>
                                <path d="M5 18.5a1 1 0 0 1 .76-.45 1 1 0 0 1 .82.25C7.35 18.8 8.74 19.5 12 19.5s4.65-.7 5.42-1.2a1 1 0 0 1 .82-.25A1 1 0 0 1 19 18.5c0 1.09-1.02 2.09-2.58 2.86-1.11.57-2.55.89-4.42.89s-3.31-.32-4.42-.89C6.02 20.59 5 19.59 5 18.5z"/>
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                                <path d="M12 3a1 1 0 0 1 1 1v4l6.6 2.2A1 1 0 0 1 20 11v4c0 3.19-2.34 5-8 5s-8-1.81-8-5v-4a1 1 0 0 1 .68-.95L11 8V4a1 1 0 0 1 1-1z"/>
                            </svg>
                        @endif
                    </span>
                    <span class="text-xs font-semibold uppercase tracking-wide">
                        {{ $cargoType ? strtoupper($cargoType) : 'N/A' }}
                    </span>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                {{ $consignment->shipment_count ?? 'Unspecified' }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                {{ $consignment->name }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                {{ $sourceDisplay }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                {{ $destinationDisplay }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                {{ ($consignment->cargo_date ?: $consignment->created_at)->toFormattedDateString() }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                @if($consignment->status == 'delivered')
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                    DELIVERED
                </span>
                @elseif($consignment->status == 'in_transit')
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                    IN TRANSIT
                </span>
                @else
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                    {{-- {{ strtoupper($consignment->status) }} --}}
                    {{ strtoupper($consignment->current_status) }}
                </span>
                @endif
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-center">
                <div class="flex justify-center space-x-2">

                @can('edit-consignments')
                <a href="{{ route('consignment.edit', $consignment->id) }}"
                    class="p-1.5 bg-yellow-500 text-white rounded-md shadow-sm hover:bg-yellow-600 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </a>
                @endcan

                @can('delete-consignments')
                <form action="{{ route('consignment.destroy', $consignment->id) }}" method="POST" class="inline-block">
                    @csrf
                    @method('DELETE')
                    <button type="submit" onclick="return confirm('Are you sure?')"
                            class="btnclicky p-1.5 bg-red-500 text-white rounded-md shadow-sm hover:bg-red-600 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    </button>
                </form>
                @endcan

                @can('view-consignments')
                <a href="{{ route('consignment.show', $consignment->id) }}"
                    class="p-1.5 bg-gray-200 text-gray-700 rounded-md shadow-sm hover:bg-gray-300 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </a>
                @endcan

                @can('update-consignment-tracker')
                <button type="button" class="btn btn-sm btn-warning update-tracker-btn" 
                    data-toggle="modal" 
                    data-target="#updateTrackerModal"
                    data-id="{{ $consignment->id }}"
                    data-checkpoint="{{ $consignment->checkpoint }}"
                    data-cargo_type="{{ $consignment->cargo_type }}"
                    data-consignee_name="{{ $consignment->consignee_name }}"
                    data-consignment_code="{{ $consignment->consignment_code }}"
                    data-source="{{ $consignment->source }}"
                    data-destination="{{ $consignment->destination }}"
                    data-status="{{ $consignment->status }}"
                    data-updated_at="{{ $consignment->updated_at }}">
                    <i class="fas fa-shipping-fast"></i> Update Tracker
                </button>
                @endcan
                </div>
            </td>
        </tr>
        @empty
        @endforelse

      </tbody>
    </table>
  </div>

  <div id="consignment-list-view" class="hidden divide-y divide-gray-200">
    @forelse($consignments as $consignment)
      @php
          $statusValue = $normalize($consignment->status ?? $consignment->current_status ?? '');
          $sourceDisplay = $consignment->source ?? 'China';
          $destinationDisplay = $consignment->destination ?? 'Zambia';
          $sourceValue = $normalize($sourceDisplay);
          $destinationValue = $normalize($destinationDisplay);
          $cargoType = $normalize($consignment->cargo_type ?? '');
          $consignmentDateFormatted = ($consignment->cargo_date ?: $consignment->created_at)
              ? ($consignment->cargo_date ?: $consignment->created_at)->toFormattedDateString()
              : 'N/A';
      @endphp
      <div class="consignment-visual-item flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 py-4 px-2"
        data-status="{{ $statusValue }}"
        data-source="{{ $sourceValue }}"
        data-destination="{{ $destinationValue }}"
        data-cargo-type="{{ $cargoType }}">
        <div class="space-y-3">
          <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm font-semibold text-gray-900">{{ $consignment->consignment_code ?? 'Unspecified' }}</span>
            <span class="inline-flex items-center space-x-2 text-xs font-semibold uppercase tracking-wide">
              <span class="inline-flex items-center justify-center w-7 h-7 rounded-full
                @if($cargoType === 'air') bg-sky-100 text-sky-600
                @elseif($cargoType === 'sea') bg-indigo-100 text-indigo-600
                @else bg-gray-100 text-gray-500 @endif">
                @if($cargoType === 'air')
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                    <path d="M21 14.5a1 1 0 0 1-1.32.95L13 13.8v3.45l1.62 1.62a1 1 0 0 1-1.41 1.41l-1.21-1.21-1.21 1.21a1 1 0 0 1-1.41-1.41L11 17.25V13.8l-6.68 1.65A1 1 0 0 1 3 14.5V13a1 1 0 0 1 .68-.95L11 9.8V5.41a1 1 0 0 1 2 0V9.8l7.32 2.25a1 1 0 0 1 .68.95z"/>
                  </svg>
                @elseif($cargoType === 'sea')
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                    <path d="M4 15v-4a1 1 0 0 1 .74-.97l7-2a1 1 0 0 1 .52 0l7 2A1 1 0 0 1 20 11v4c0 3.19-2.34 5-8 5s-8-1.81-8-5zm2 0c0 1.56 1.4 3 6 3s6-1.44 6-3v-3.19l-6-1.72-6 1.72z"/>
                    <path d="M5 18.5a1 1 0 0 1 .76-.45 1 1 0 0 1 .82.25C7.35 18.8 8.74 19.5 12 19.5s4.65-.7 5.42-1.2a1 1 0 0 1 .82-.25A1 1 0 0 1 19 18.5c0 1.09-1.02 2.09-2.58 2.86-1.11.57-2.55.89-4.42.89s-3.31-.32-4.42-.89C6.02 20.59 5 19.59 5 18.5z"/>
                  </svg>
                @else
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                    <path d="M12 3a1 1 0 0 1 1 1v4l6.6 2.2A1 1 0 0 1 20 11v4c0 3.19-2.34 5-8 5s-8-1.81-8-5v-4a1 1 0 0 1 .68-.95L11 8V4a1 1 0 0 1 1-1z"/>
                  </svg>
                @endif
              </span>
              <span>{{ $cargoType ? strtoupper($cargoType) : 'N/A' }}</span>
            </span>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm text-gray-600">
            <div><span class="font-semibold">Consignee:</span> {{ $consignment->name }}</div>
            <div><span class="font-semibold">Parcels:</span> {{ $consignment->shipment_count ?? 'Unspecified' }}</div>
            <div><span class="font-semibold">Consignment date:</span> {{ $consignmentDateFormatted }}</div>
            <div><span class="font-semibold">Source:</span> {{ $sourceDisplay }}</div>
            <div><span class="font-semibold">Destination:</span> {{ $destinationDisplay }}</div>
          </div>
        </div>
        <div class="flex flex-col sm:items-end gap-2">
          <div>
            @if($consignment->status == 'delivered')
              <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">DELIVERED</span>
            @elseif($consignment->status == 'in_transit')
              <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">IN TRANSIT</span>
            @else
              <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">{{ strtoupper($consignment->current_status) }}</span>
            @endif
          </div>
          <div class="text-xs text-gray-400">Code: {{ $consignment->consignment_code ?? 'N/A' }}</div>
          <div class="flex flex-wrap gap-2 justify-end">
            @can('edit-consignments')
              <a href="{{ route('consignment.edit', $consignment->id) }}"
                class="p-1.5 bg-yellow-500 text-white rounded-md shadow-sm hover:bg-yellow-600 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 4h4a2 2 0 0 1 2 2v2M5 19h14M5 11L16 4l4 4-11 7-4 4 1-5z" />
                </svg>
              </a>
            @endcan
            <a href="{{ route('consignment.show', $consignment->id) }}"
                class="p-1.5 bg-sky-500 text-muted rounded-md shadow-sm hover:bg-sky-600 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
            </a>
            @can('update-consignment-tracker')
              <button type="button"
                class="p-1.5 bg-orange-500 text-white rounded-md shadow-sm hover:bg-orange-600 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 update-tracker-btn"
                data-toggle="modal"
                data-target="#updateTrackerModal"
                data-id="{{ $consignment->id }}"
                data-checkpoint="{{ $consignment->checkpoint }}"
                data-cargo_type="{{ $consignment->cargo_type }}"
                data-consignee_name="{{ $consignment->consignee_name }}"
                data-consignment_code="{{ $consignment->consignment_code }}"
                data-source="{{ $consignment->source }}"
                data-destination="{{ $consignment->destination }}"
                data-status="{{ $consignment->status }}"
                data-updated_at="{{ $consignment->updated_at }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 8h18M3 16h18M5 12h14M9 4l-2 4m8-4l2 4m-8 12l-2-4m8 4l2-4" />
                </svg>
              </button>
            @endcan
            @can('delete-consignments')
              <button type="button"
                data-action="{{ route('consignment.destroy', $consignment->id) }}"
                class="delete-consignment p-1.5 bg-red-500 text-white rounded-md shadow-sm hover:bg-red-600 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            @endcan
          </div>
        </div>
      </div>
    @empty
      <div class="py-6 text-center text-sm text-gray-500">No consignments available.</div>
    @endforelse
    @if($consignmentCollection->count())
      <div id="list-view-empty" class="hidden py-6 text-center text-sm text-gray-500">No consignments match the selected filters.</div>
    @endif
  </div>

  {{-- @if($consignmentCollection->count())
    <div id="list-pagination" data-pagination-view="list" class="consignment-pagination hidden pt-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white border border-gray-200 rounded-lg shadow-sm px-4 py-3">
        <div class="pagination-summary text-sm text-gray-600"></div>
        <div class="pagination-buttons flex items-center gap-1"></div>
      </div>
    </div>
  @endif --}}

  <div id="consignment-grid-view" class="hidden px-2 pb-4">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      @forelse($consignments as $consignment)
        @php
            $statusValue = $normalize($consignment->status ?? $consignment->current_status ?? '');
            $sourceDisplay = $consignment->source ?? 'China';
            $destinationDisplay = $consignment->destination ?? 'Zambia';
            $sourceValue = $normalize($sourceDisplay);
            $destinationValue = $normalize($destinationDisplay);
            $cargoType = $normalize($consignment->cargo_type ?? '');
            $consignmentDateFormatted = ($consignment->cargo_date ?: $consignment->created_at)
                ? ($consignment->cargo_date ?: $consignment->created_at)->toFormattedDateString()
                : 'N/A';
        @endphp
        <div class="consignment-visual-item consignment-grid-card border border-gray-200 rounded-lg shadow-sm p-4 bg-white"
          data-status="{{ $statusValue }}"
          data-source="{{ $sourceValue }}"
          data-destination="{{ $destinationValue }}"
          data-cargo-type="{{ $cargoType }}">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="text-sm font-semibold text-gray-900">{{ $consignment->consignment_code ?? 'Unspecified' }}</div>
              <div class="flex items-center gap-2 mt-2 text-xs font-semibold uppercase tracking-wide">
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full
                  @if($cargoType === 'air') bg-sky-100 text-sky-600
                  @elseif($cargoType === 'sea') bg-indigo-100 text-indigo-600
                  @else bg-gray-100 text-gray-500 @endif">
                  @if($cargoType === 'air')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                      <path d="M21 14.5a1 1 0 0 1-1.32.95L13 13.8v3.45l1.62 1.62a1 1 0 0 1-1.41 1.41l-1.21-1.21-1.21 1.21a1 1 0 0 1-1.41-1.41L11 17.25V13.8l-6.68 1.65A1 1 0 0 1 3 14.5V13a1 1 0 0 1 .68-.95L11 9.8V5.41a1 1 0 0 1 2 0V9.8l7.32 2.25a1 1 0 0 1 .68.95z"/>
                    </svg>
                  @elseif($cargoType === 'sea')
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                      <path d="M4 15v-4a1 1 0 0 1 .74-.97l7-2a1 1 0 0 1 .52 0l7 2A1 1 0 0 1 20 11v4c0 3.19-2.34 5-8 5s-8-1.81-8-5zm2 0c0 1.56 1.4 3 6 3s6-1.44 6-3v-3.19l-6-1.72-6 1.72z"/>
                      <path d="M5 18.5a1 1 0 0 1 .76-.45 1 1 0 0 1 .82.25C7.35 18.8 8.74 19.5 12 19.5s4.65-.7 5.42-1.2a1 1 0 0 1 .82-.25A1 1 0 0 1 19 18.5c0 1.09-1.02 2.09-2.58 2.86-1.11.57-2.55.89-4.42.89s-3.31-.32-4.42-.89C6.02 20.59 5 19.59 5 18.5z"/>
                    </svg>
                  @else
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
                      <path d="M12 3a1 1 0 0 1 1 1v4l6.6 2.2A1 1 0 0 1 20 11v4c0 3.19-2.34 5-8 5s-8-1.81-8-5v-4a1 1 0 0 1 .68-.95L11 8V4a1 1 0 0 1 1-1z"/>
                    </svg>
                  @endif
                </span>
                <span>{{ $cargoType ? strtoupper($cargoType) : 'N/A' }}</span>
              </div>
            </div>
            <div>
              @if($consignment->status == 'delivered')
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">DELIVERED</span>
              @elseif($consignment->status == 'in_transit')
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">IN TRANSIT</span>
              @else
                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">{{ strtoupper($consignment->current_status) }}</span>
              @endif
            </div>
          </div>
          <div class="mt-4 space-y-2 text-sm text-gray-600">
            <div class="flex justify-between"><span class="font-semibold">Consignee:</span><span>{{ $consignment->name }}</span></div>
            <div class="flex justify-between"><span class="font-semibold">Parcels:</span><span>{{ $consignment->shipment_count ?? 'Unspecified' }}</span></div>
            <div class="flex justify-between"><span class="font-semibold">Source:</span><span>{{ $sourceDisplay }}</span></div>
            <div class="flex justify-between"><span class="font-semibold">Destination:</span><span>{{ $destinationDisplay }}</span></div>
            <div class="flex justify-between"><span class="font-semibold">Consignment date:</span><span>{{ $consignmentDateFormatted }}</span></div>
          </div>
          <div class="mt-4 flex flex-wrap gap-2">
            @can('edit-consignments')
              <a href="{{ route('consignment.edit', $consignment->id) }}"
                class="px-3 py-1.5 bg-yellow-500 text-white rounded-md text-xs font-medium hover:bg-yellow-600 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                Edit
              </a>
            @endcan
            <a href="{{ route('consignment.show', $consignment->id) }}"
                class="px-3 py-1.5 bg-sky-500 text-muted rounded-md text-xs font-medium hover:bg-sky-600 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500">
                View
            </a>
            @can('update-consignment-tracker')
              <button type="button"
                class="px-3 py-1.5 bg-orange-500 text-white rounded-md text-xs font-medium hover:bg-orange-600 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 update-tracker-btn"
                data-toggle="modal"
                data-target="#updateTrackerModal"
                data-id="{{ $consignment->id }}"
                data-checkpoint="{{ $consignment->checkpoint }}"
                data-cargo_type="{{ $consignment->cargo_type }}"
                data-consignee_name="{{ $consignment->consignee_name }}"
                data-consignment_code="{{ $consignment->consignment_code }}"
                data-source="{{ $consignment->source }}"
                data-destination="{{ $consignment->destination }}"
                data-status="{{ $consignment->status }}"
                data-updated_at="{{ $consignment->updated_at }}">
                Update Tracker
              </button>
            @endcan
            @can('delete-consignments')
              <button type="button"
                data-action="{{ route('consignment.destroy', $consignment->id) }}"
                class="delete-consignment px-3 py-1.5 bg-red-500 text-white rounded-md text-xs font-medium hover:bg-red-600 transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                Delete
              </button>
            @endcan
          </div>
        </div>
      @empty
        <div class="col-span-full py-6 text-center text-sm text-gray-500">No consignments available.</div>
      @endforelse
    </div>
    @if($consignmentCollection->count())
      <div id="grid-view-empty" class="hidden py-6 text-center text-sm text-gray-500">No consignments match the selected filters.</div>
    @endif
  </div>

  {{-- @if($consignmentCollection->count())
    <div id="grid-pagination" data-pagination-view="grid" class="consignment-pagination hidden pt-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white border border-gray-200 rounded-lg shadow-sm px-4 py-3">
        <div class="pagination-summary text-sm text-gray-600"></div>
        <div class="pagination-buttons flex items-center gap-1"></div>
      </div>
    </div>
  @endif --}}

</div>
<script>
  $(function () {
    const tableElement = $('#consignments-table');
    const table = tableElement.length ? tableElement.DataTable() : null;

    const tableContainer = $('#table-view-container');
    const listView = $('#consignment-list-view');
    const gridView = $('#consignment-grid-view');

    const paginationElements = {
      list: {
        container: listView,
        emptyMessage: $('#list-view-empty'),
        paginationBox: $('#list-pagination'),
        summary: $('#list-pagination .pagination-summary'),
        buttons: $('#list-pagination .pagination-buttons'),
      },
      grid: {
        container: gridView,
        emptyMessage: $('#grid-view-empty'),
        paginationBox: $('#grid-pagination'),
        summary: $('#grid-pagination .pagination-summary'),
        buttons: $('#grid-pagination .pagination-buttons'),
      },
    };

    const paginationState = {
      list: { currentPage: 1 },
      grid: { currentPage: 1 },
    };

    const FILTER_HIDDEN_CLASS = 'filter-hidden';
    const PAGE_HIDDEN_CLASS = 'page-hidden';
    const ITEMS_PER_PAGE = 10;

    const filterSelectors = {
      status: $('#filter-status'),
      source: $('#filter-source'),
      destination: $('#filter-destination'),
    };

    const viewButtons = $('.view-toggle');
    const bulkDeleteBtn = $('#bulk-delete-btn');
    const selectAll = $('#select-all');
    const rowCheckboxSelector = '.row-checkbox';

    function getActiveFilters() {
      return {
        status: (filterSelectors.status.val() || 'all'),
        source: (filterSelectors.source.val() || 'all'),
        destination: (filterSelectors.destination.val() || 'all'),
      };
    }

    function matchesFilter(value, filter) {
      if (!filter || filter === 'all') {
        return true;
      }
      return (value || '') === filter;
    }

    function matchesAllFilters(data) {
      const filters = getActiveFilters();
      return (
        matchesFilter(data.status, filters.status) &&
        matchesFilter(data.source, filters.source) &&
        matchesFilter(data.destination, filters.destination)
      );
    }

    function applyViewPagination(view, resetPage = false) {
      const config = paginationElements[view];
      if (!config || !config.container.length) {
        return;
      }

      const state = paginationState[view];
      const items = config.container.find('.consignment-visual-item');
      const filteredItems = items.filter(function () {
        return !$(this).hasClass(FILTER_HIDDEN_CLASS);
      });

      if (resetPage) {
        state.currentPage = 1;
      }

      const totalItems = filteredItems.length;
      const totalPages = totalItems > 0 ? Math.ceil(totalItems / ITEMS_PER_PAGE) : 1;

      if (state.currentPage > totalPages) {
        state.currentPage = totalPages;
      }
      if (state.currentPage < 1) {
        state.currentPage = 1;
      }

      items.each(function () {
        const $el = $(this);
        if ($el.hasClass(FILTER_HIDDEN_CLASS)) {
          $el.addClass(PAGE_HIDDEN_CLASS);
        } else {
          $el.removeClass(PAGE_HIDDEN_CLASS);
        }
      });

      const startIndex = (state.currentPage - 1) * ITEMS_PER_PAGE;
      const endIndex = startIndex + ITEMS_PER_PAGE;

      filteredItems.each(function (index, element) {
        const shouldShow = index >= startIndex && index < endIndex;
        $(element).toggleClass(PAGE_HIDDEN_CLASS, !shouldShow);
      });

      const hasItems = totalItems > 0;
      if (config.emptyMessage.length) {
        config.emptyMessage.toggleClass('hidden', hasItems);
      }

      if (!config.paginationBox.length) {
        return;
      }

      if (!hasItems) {
        config.paginationBox.addClass('hidden');
        if (config.summary.length) {
          config.summary.text('');
        }
        if (config.buttons.length) {
          config.buttons.empty();
        }
        return;
      }

      const displayStart = startIndex + 1;
      const displayEnd = Math.min(endIndex, totalItems);

      if (config.summary.length) {
        config.summary.text(`Showing ${displayStart} to ${displayEnd} of ${totalItems} consignments`);
      }

      if (totalPages <= 1) {
        config.paginationBox.removeClass('hidden');
        if (config.buttons.length) {
          config.buttons.empty();
        }
        return;
      }

      config.paginationBox.removeClass('hidden');
      renderPaginationButtons(view, totalPages, state.currentPage);
    }

    function buildPageList(totalPages, currentPage) {
      if (totalPages <= 5) {
        return Array.from({ length: totalPages }, (_, idx) => idx + 1);
      }

      const pages = [1];
      let start = Math.max(2, currentPage - 1);
      let end = Math.min(totalPages - 1, currentPage + 1);

      if (start > 2) {
        pages.push('ellipsis');
      }

      for (let page = start; page <= end; page += 1) {
        pages.push(page);
      }

      if (end < totalPages - 1) {
        pages.push('ellipsis');
      }

      pages.push(totalPages);
      return pages;
    }

    function renderPaginationButtons(view, totalPages, currentPage) {
      const config = paginationElements[view];
      if (!config || !config.buttons.length) {
        return;
      }

      const buttonsContainer = config.buttons;
      buttonsContainer.empty();

      const createNavButton = (label, targetPage, disabled, ariaLabel) => {
        const button = $('<button type="button"></button>')
          .addClass('px-3 py-1.5 text-sm font-medium rounded-md border focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 transition');
        button.attr('aria-label', ariaLabel || label);
        if (disabled) {
          button
            .addClass('border-gray-200 text-gray-400 bg-gray-100 cursor-not-allowed')
            .prop('disabled', true);
        } else {
          button
            .addClass('border-gray-300 text-gray-600 bg-white hover:bg-sky-50 hover:border-sky-400')
            .attr('data-page', targetPage);
        }
        button.text(label);
        return button;
      };

      const createPageButton = (page, isActive) => {
        const button = $('<button type="button"></button>')
          .addClass('px-3 py-1.5 text-sm font-medium rounded-md border focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 transition');
        if (isActive) {
          button
            .addClass('border-sky-500 bg-sky-500 text-white shadow cursor-default')
            .prop('disabled', true);
        } else {
          button
            .addClass('border-gray-300 text-gray-600 bg-white hover:bg-sky-50 hover:border-sky-400')
            .attr('data-page', page);
        }
        button.text(page);
        return button;
      };

      const createEllipsis = () =>
        $('<span class="px-2 text-gray-400 select-none">…</span>');

      buttonsContainer.append(
        createNavButton('Prev', currentPage - 1, currentPage === 1, 'Previous page')
      );

      const pageList = buildPageList(totalPages, currentPage);
      pageList.forEach((page) => {
        if (page === 'ellipsis') {
          buttonsContainer.append(createEllipsis());
        } else {
          buttonsContainer.append(createPageButton(page, page === currentPage));
        }
      });

      buttonsContainer.append(
        createNavButton('Next', currentPage + 1, currentPage === totalPages, 'Next page')
      );
    }

    function filterAlternateViews(resetPagination = true) {
      const items = $('.consignment-visual-item');
      items.each(function () {
        const $item = $(this);
        const rowData = {
          status: ($item.data('status') || '').toString(),
          source: ($item.data('source') || '').toString(),
          destination: ($item.data('destination') || '').toString(),
        };
        const shouldShow = matchesAllFilters(rowData);
        $item.toggleClass(FILTER_HIDDEN_CLASS, !shouldShow);
      });

      applyViewPagination('list', resetPagination);
      applyViewPagination('grid', resetPagination);
    }

    function applyFilters() {
      if (table) {
        table.draw();
      }
      filterAlternateViews(true);
    }

    const filterFunction = function (settings, data, dataIndex) {
      if (!table || settings.nTable !== tableElement.get(0)) {
        return true;
      }
      const rowNode = table.row(dataIndex).node();
      if (!rowNode) {
        return true;
      }
      const $row = $(rowNode);
      const rowData = {
        status: ($row.data('status') || '').toString(),
        source: ($row.data('source') || '').toString(),
        destination: ($row.data('destination') || '').toString(),
      };
      return matchesAllFilters(rowData);
    };

    if ($.fn && $.fn.dataTable && $.fn.dataTable.ext) {
      $.fn.dataTable.ext.search.push(filterFunction);
    }

    $('.consignment-filter').on('change', applyFilters);

    function updateBulkDeleteState() {
      if (!bulkDeleteBtn.length) {
        return;
      }
      const selected = $(rowCheckboxSelector + ':checked').length;
      bulkDeleteBtn.prop('disabled', selected === 0);
    }

    if (selectAll.length) {
      selectAll.on('click', function () {
        const isChecked = this.checked;
        $(rowCheckboxSelector).each(function () {
          this.checked = isChecked;
        });
        updateBulkDeleteState();
      });
    }

    $(document).on('change', rowCheckboxSelector, updateBulkDeleteState);

    if (bulkDeleteBtn.length) {
      bulkDeleteBtn.on('click', function () {
        const ids = $(rowCheckboxSelector + ':checked')
          .map(function () {
            return this.value;
          })
          .get();
        if (!ids.length) {
          return;
        }
        if (!confirm(`Delete ${ids.length} selected consignments?`)) {
          return;
        }
        $.ajax({
          url: "{{ route('consignment.bulkDelete') }}",
          method: 'POST',
          data: {
            _token: "{{ csrf_token() }}",
            ids: ids,
          },
          success: function () {
            location.reload();
          },
          error: function () {
            alert('An error occurred while deleting.');
          },
        });
      });
    }

    function resetTableSelection() {
      if (selectAll.length) {
        selectAll.prop('checked', false);
      }
      $(rowCheckboxSelector).prop('checked', false);
      updateBulkDeleteState();
    }

    $('.consignment-pagination').on('click', 'button[data-page]', function () {
      const $button = $(this);
      if ($button.prop('disabled')) {
        return;
      }
      const host = $button.closest('.consignment-pagination');
      const view = host.data('pagination-view');
      if (!view || !paginationState[view]) {
        return;
      }
      const targetPage = parseInt($button.data('page'), 10);
      if (!Number.isInteger(targetPage) || targetPage === paginationState[view].currentPage) {
        return;
      }
      paginationState[view].currentPage = targetPage;
      applyViewPagination(view);
    });

    let currentView = 'table';
    function setView(view) {
      currentView = view;
      viewButtons.each(function () {
        const $button = $(this);
        const isActive = $button.data('view') === view;
        if (isActive) {
          $button
            .removeClass('border-gray-300 text-gray-600 bg-white hover:bg-gray-100')
            .addClass('border-sky-500 bg-sky-100 text-gray-900 hover:bg-sky-200 hover:text-gray-900');
        } else {
          $button
            .removeClass('border-sky-500 bg-sky-100 text-gray-900 hover:bg-sky-200 hover:text-gray-900')
            .addClass('border-gray-300 text-gray-600 bg-white hover:bg-gray-100');
        }
        $button.attr('aria-pressed', isActive);
      });

      tableContainer.toggleClass('hidden', view !== 'table');
      listView.toggleClass('hidden', view !== 'list');
      gridView.toggleClass('hidden', view !== 'grid');

      if (view === 'table') {
        if (table) {
          table.columns.adjust().draw(false);
        }
      } else {
        resetTableSelection();
        filterAlternateViews(false);
        applyViewPagination(view);
      }

      if (bulkDeleteBtn.length) {
        const selected = $(rowCheckboxSelector + ':checked').length;
        bulkDeleteBtn.prop('disabled', view !== 'table' || selected === 0);
      }
    }

    viewButtons.on('click', function () {
      const targetView = $(this).data('view');
      if (targetView && targetView !== currentView) {
        setView(targetView);
      }
    });

    applyFilters();
    setView('table');
    updateBulkDeleteState();
  });
</script>
