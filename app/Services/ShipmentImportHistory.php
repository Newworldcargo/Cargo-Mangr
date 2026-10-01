<?php

namespace App\Services;

use App\Models\{AuditLog, ConsignmentImportRow};
use Modules\Cargo\Entities\{Client, Shipment};

class ShipmentImportHistory
{
    public function record(Shipment $shipment, ConsignmentImportRow $row, Client $client): void
    {
        $snapshot = $this->snapshot($row);
        $snapshot['customer'] = $client->only(['id', 'name', 'responsible_mobile', 'responsible_mobile_2']);
        $snapshot['selection'] = $row->only(['selected_customer_id', 'customer_selected_by', 'customer_selected_at', 'phone_override', 'phone_override_2']);
        app(AuditLogService::class)->createLog('shipment_imported', $shipment, null,
            ['raw_values' => $row->raw_values], $snapshot,
            'Shipment created from a spreadsheet. Original values and customer assignment preserved.');
    }

    private function snapshot(ConsignmentImportRow $row): array
    {
        $batch = $row->batch;
        return [
            'row_id' => $row->id, 'filename' => $batch?->original_filename,
            'sheet' => $row->sheet_name, 'row' => $row->spreadsheet_row,
            'mappings' => $batch?->mappings ?? [], 'raw_values' => $row->raw_values ?? [],
            'imported_values' => $row->mapped_values ?? [],
            'warnings' => $row->validation_warnings ?? [],
        ];
    }

    public function forShipment(Shipment $shipment): array
    {
        $logs = AuditLog::with('user')->where('auditable_type', Shipment::class)
            ->where('auditable_id', $shipment->id)->where('event', 'shipment_imported')->orderBy('id')->get();
        $imports = $logs->map(fn ($log) => array_merge($log->new_values, [
            'date' => $log->created_at, 'actor' => $log->user?->name ?? 'Recorded import', 'snapshot' => true,
        ]));
        // Older imports have saved rows, but no immutable account-at-import snapshot.
        $rows = ConsignmentImportRow::with('batch')->where('shipment_id', $shipment->id)
            ->whereNotNull('import_action')->whereNotIn('id', $imports->pluck('row_id')->filter()->all())->orderBy('id')->get();
        $actors = \App\Models\User::whereIn('id', $rows->pluck('batch.created_by')->filter())->pluck('name', 'id');
        foreach ($rows as $row) {
            $imports->push(array_merge($this->snapshot($row), [
                'date' => $row->batch?->confirmed_at,
                'actor' => $actors[$row->batch?->created_by] ?? 'Recorded import', 'snapshot' => false,
            ]));
        }
        $mergeQuery = AuditLog::with('user')->where('event', 'customer_account_merged');
        // Laravel 8's SQLite grammar lacks JSON_CONTAINS; use its JSON table function.
        if ($mergeQuery->getModel()->getConnection()->getDriverName() === 'sqlite') {
            $mergeQuery->whereRaw("EXISTS (SELECT 1 FROM json_each(audit_logs.old_values, '$.shipment_ids') WHERE value = ?)", [(int) $shipment->id]);
        } else {
            $mergeQuery->whereJsonContains('old_values->shipment_ids', (int) $shipment->id);
        }
        $merges = $mergeQuery->orderBy('id')->get();
        return ['imports' => $imports, 'merges' => $merges];
    }
}
