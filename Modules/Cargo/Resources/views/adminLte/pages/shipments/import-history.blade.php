@if(app(\Modules\Cargo\Services\ShipmentOperationAccessService::class)->canViewAuditTrail(auth()->user(), $shipment))
    @php
        $importHistory = app(\App\Services\ShipmentImportHistory::class)->forShipment($shipment);
    @endphp
    @if($importHistory['imports']->isNotEmpty() || $importHistory['merges']->isNotEmpty())
        <section class="border-top py-4 mb-4" aria-labelledby="import-history-heading" style="overflow-wrap:anywhere">
            <h2 id="import-history-heading" class="h5 font-weight-bold">Import history</h2>
            @php
                $currentImportCustomer = \Modules\Cargo\Entities\Client::find($shipment->client_id);
            @endphp
            <p>Current customer account: <strong>{{ $currentImportCustomer?->name ?? 'Account unavailable' }}</strong> (profile #{{ $shipment->client_id }}).</p>
            @foreach($importHistory['imports'] as $entry)
                <div class="py-3 border-bottom">
                    <p class="mb-1 font-weight-bold">{{ $entry['filename'] }} · {{ $entry['sheet'] }} · Row {{ $entry['row'] }}</p>
                    <p class="text-muted mb-2">{{ $entry['actor'] }} · {{ $entry['date'] ?? 'Import date not recorded' }}</p>
                    @if(!empty($entry['customer']))
                        <p>Account assigned at import: <strong>{{ $entry['customer']['name'] }}</strong> (profile #{{ $entry['customer']['id'] }}).
                            {{ !empty($entry['selection']['selected_customer_id']) ? 'Selected by staff.' : 'Assigned during import.' }}</p>
                        @php
                            $originalName = $entry['raw_values'][$entry['mappings']['consignee_name'] ?? ''] ?? '';
                        @endphp
                        @if(trim(mb_strtolower((string) $originalName)) !== trim(mb_strtolower((string) $entry['customer']['name'])))
                            <p class="font-weight-bold" style="color:#854d0e">Different names: the spreadsheet lists {{ $originalName ?: '(blank)' }}; the assigned account is {{ $entry['customer']['name'] }}.</p>
                        @endif
                    @else
                        <p class="text-muted">Historical import: original spreadsheet values are available; the account assigned at import was not recorded separately.</p>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-sm mb-2">
                            <thead><tr><th scope="col">Field</th><th scope="col">Original spreadsheet</th><th scope="col">Imported value</th></tr></thead>
                            <tbody>
                            @foreach($entry['mappings'] as $field => $column)
                                @if($column)
                                    @php
                                        $original = $entry['raw_values'][$column] ?? '';
                                        $imported = $entry['imported_values'][$field] ?? '';
                                    @endphp
                                    <tr><th scope="row">{{ ucfirst(str_replace('_', ' ', $field)) }}</th><td>{{ $original }}</td><td>{{ $imported }}@if((string) $original !== (string) $imported) <span class="text-muted">(changed during import)</span>@endif</td></tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @foreach($entry['warnings'] as $warning)
                        <p class="mb-1">{{ $warning }}</p>
                    @endforeach
                    <details class="mt-2">
                        <summary class="py-2" style="cursor:pointer">All original spreadsheet columns</summary>
                        <dl class="mb-0">
                            @foreach($entry['raw_values'] as $column => $value)
                                <dt>Column {{ $column }}</dt><dd>{{ is_scalar($value) || is_null($value) ? $value : json_encode($value) }}</dd>
                            @endforeach
                        </dl>
                    </details>
                </div>
            @endforeach
            @foreach($importHistory['merges'] as $merge)
                <p class="mt-3 mb-1"><strong>Customer accounts merged</strong> · {{ $merge->created_at }}<br>
                    Shipment moved from profile #{{ $merge->old_values['client_id'] ?? '' }} to profile #{{ $merge->new_values['client_id'] ?? '' }}.
                    {{ $merge->description }}</p>
            @endforeach
        </section>
    @endif
@endif
