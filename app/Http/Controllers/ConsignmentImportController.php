<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\ConsignmentImportBatch;
use App\Models\ConsignmentImportRow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Country;
use Modules\Cargo\Entities\Package;
use Modules\Cargo\Entities\PackageShipment;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\Staff;
use Modules\Cargo\Entities\State;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ConsignmentImportController extends Controller
{
    private const FIELDS = [
        'consignment_code' => ['label' => 'Consignment / container code', 'required' => true, 'aliases' => ['consignment code','container','container no','container number','job no','job number']],
        'hawb_number' => ['label' => 'HAWB / parcel code', 'required' => true, 'aliases' => ['hawb','hawb no','hawb number','parcel code','shipment code','tracking number']],
        'consignee_name' => ['label' => 'Consignee name', 'required' => true, 'aliases' => ['consignee','consignee name','receiver','receiver name','customer name']],
        'phone' => ['label' => 'Customer phone number', 'required' => true, 'aliases' => ['phone','phone number','mobile','mobile number','telephone','tel','customer phone']],
        'destination' => ['label' => 'Destination', 'required' => true, 'aliases' => ['destination','delivery destination','city']],
        'weight' => ['label' => 'Weight', 'required' => false, 'aliases' => ['weight','gross weight','kg','kgs']],
        'pieces' => ['label' => 'Number of pieces', 'required' => false, 'aliases' => ['pieces','piece','qty','quantity','no of pieces']],
        'description' => ['label' => 'Goods description', 'required' => false, 'aliases' => ['description','goods','contents','item description']],
        'amount' => ['label' => 'Shipping amount', 'required' => false, 'aliases' => ['amount','shipping cost','cost','charge']],
        'mawb_num' => ['label' => 'MAWB number', 'required' => false, 'aliases' => ['mawb','mawb no','mawb number']],
    ];

    public function upload(Request $request)
    {
        $this->authorizeImport();
        $request->validate(['shipment_type' => ['required', 'in:air,sea'], 'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);
        $file = $request->file('excel_file');
        $uuid = (string) Str::uuid();
        $path = $file->storeAs('consignment-imports/'.$uuid, 'source.'.$file->getClientOriginalExtension(), 'local');

        try {
            $book = IOFactory::load(Storage::disk('local')->path($path));
            $batch = ConsignmentImportBatch::create([
                'uuid' => $uuid, 'created_by' => $request->user()->id, 'original_filename' => $file->getClientOriginalName(),
                'storage_path' => $path, 'shipment_type' => $request->shipment_type,
            ]);
            $firstSheet = null;
            foreach ($book->getWorksheetIterator() as $sheet) {
                $firstSheet ??= $sheet->getTitle();
                $rows = $sheet->toArray('', true, true, true);
                if (count($rows) > 5000) throw new \RuntimeException('A sheet may contain at most 5,000 rows.');
                $insert = [];
                foreach ($rows as $number => $values) {
                    $insert[] = ['batch_id' => $batch->id, 'sheet_name' => $sheet->getTitle(), 'spreadsheet_row' => $number,
                        'raw_values' => json_encode($values), 'created_at' => now(), 'updated_at' => now()];
                }
                foreach (array_chunk($insert, 500) as $chunk) ConsignmentImportRow::insert($chunk);
            }
            $batch->update(['selected_sheet' => $firstSheet, 'header_row' => $this->guessHeaderRow($batch, $firstSheet), 'data_start_row' => 2]);
            $batch->update(['data_start_row' => $batch->header_row + 1, 'mappings' => $this->suggestMappings($batch)]);
            $this->validateRows($batch->fresh());
            return redirect()->route('consignment.import.preview', $batch->uuid);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            report($e);
            return back()->withInput()->with('error', 'The file could not be read: '.$e->getMessage());
        }
    }

    public function preview(Request $request, string $uuid)
    {
        $batch = $this->batch($uuid);
        return view('cargo::adminLte.pages.consignments.import-preview', $this->previewData($batch));
    }

    public function updatePreview(Request $request, string $uuid)
    {
        $batch = $this->batch($uuid);
        abort_if($batch->status === 'completed', 422, 'This import has already been completed.');
        $sheets = $batch->rows()->distinct()->pluck('sheet_name')->all();
        $request->validate([
            'selected_sheet' => ['required', Rule::in($sheets)],
            'header_row' => ['required', 'integer', 'min:1'], 'data_start_row' => ['required', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer'], 'from_country_id' => ['nullable', 'integer'], 'from_state_id' => ['nullable', 'integer'],
            'to_country_id' => ['nullable', 'integer'], 'to_state_id' => ['nullable', 'integer'], 'mapping' => ['array'],
        ]);
        $mappings = array_filter($request->input('mapping', []), fn($value) => $value !== '');
        $batch->update($request->only('selected_sheet','header_row','data_start_row','branch_id','from_country_id','from_state_id','to_country_id','to_state_id') + ['mappings' => $mappings]);
        $included = $request->input('included', []);
        $batch->rows()->where('sheet_name', $batch->selected_sheet)->where('spreadsheet_row', '>=', $batch->data_start_row)->update(['included' => false]);
        if ($included) $batch->rows()->whereIn('id', array_keys($included))->update(['included' => true]);
        $this->validateRows($batch->fresh());
        return redirect()->route('consignment.import.preview', $batch->uuid)->with('success', 'Preview updated. No records have been imported.');
    }

    public function confirm(Request $request, string $uuid)
    {
        $batch = $this->batch($uuid)->fresh();
        abort_if($batch->status === 'completed', 422, 'This import has already been completed.');
        foreach (['branch_id','from_country_id','from_state_id','to_country_id','to_state_id'] as $field) abort_unless($batch->{$field}, 422, 'Choose the branch, origin and destination settings before importing.');
        $this->assertBranchAllowed((int) $batch->branch_id);
        $this->validateRows($batch->fresh());
        $rows = $batch->rows()->where('included', true)->whereIn('status', ['valid','warning'])->get();
        abort_if($rows->isEmpty(), 422, 'There are no valid selected rows to import.');
        $first = $rows->first()->mapped_values;
        abort_if(Consignment::where('consignment_code', $first['consignment_code'])->exists(), 422, 'That consignment/container code already exists.');
        $package = Package::query()->orderBy('id')->first();
        abort_unless($package, 422, 'No package type is configured. Create a package type before importing.');

        DB::transaction(function () use ($batch, $rows, $first, $package) {
            $consignment = Consignment::create(['consignment_code' => $first['consignment_code'], 'name' => 'Imported consignment',
                'source' => null, 'destination' => $first['destination'], 'status' => 'pending', 'cargo_type' => $batch->shipment_type,
                'mawb_num' => $first['mawb_num'] ?? null]);
            $ids = [];
            foreach ($rows as $row) {
                $data = $row->mapped_values;
                $client = $this->findClient($data['phone']);
                if (!$client || Shipment::where('code', $data['hawb_number'])->exists()) throw new \RuntimeException('The import changed while it was being confirmed. Review the preview again.');
                $shipment = Shipment::create(['consignment_id' => $consignment->id, 'code' => $data['hawb_number'], 'status_id' => Shipment::PENDING_STATUS,
                    'type' => Shipment::PICKUP, 'branch_id' => $batch->branch_id, 'shipping_date' => now()->toDateString(), 'client_status' => Shipment::CLIENT_STATUS_CREATED,
                    'client_id' => $client->id, 'client_phone' => $data['phone'], 'reciver_name' => $data['consignee_name'], 'reciver_phone' => $data['phone'],
                    'reciver_address' => $data['destination'], 'from_country_id' => $batch->from_country_id, 'from_state_id' => $batch->from_state_id,
                    'to_country_id' => $batch->to_country_id, 'to_state_id' => $batch->to_state_id, 'payment_type' => Shipment::POSTPAID,
                    'shipping_cost' => (float) ($data['amount'] ?? 0), 'total_weight' => (float) ($data['weight'] ?? 0)]);
                PackageShipment::create(['package_id' => $package->id, 'shipment_id' => $shipment->id, 'description' => $data['description'] ?? null,
                    'weight' => $data['weight'] ?? 0, 'qty' => $data['pieces'] ?? 1]);
                $row->update(['status' => 'imported']); $ids[] = $shipment->id;
            }
            $batch->update(['status' => 'completed', 'confirmed_at' => now(), 'result' => ['consignment_id' => $consignment->id, 'shipment_ids' => $ids, 'imported' => count($ids)]]);
        });
        return redirect()->route('consignment.import.preview', $batch->uuid)->with('success', 'Import completed. '.$batch->fresh()->result['imported'].' shipment(s) were created.');
    }

    private function batch(string $uuid): ConsignmentImportBatch
    {
        $this->authorizeImport();
        return ConsignmentImportBatch::where('uuid', $uuid)->where('created_by', auth()->id())->firstOrFail();
    }
    private function authorizeImport(): void { abort_unless(auth()->check() && auth()->user()->can('import-consignments'), 403); }
    private function allowedBranches() { $u = auth()->user(); return (int) $u->role === User::ADMIN ? Branch::where('is_archived', 0)->orderBy('name')->get() : Branch::where('is_archived', 0)->whereIn('id', Staff::where('user_id', $u->id)->pluck('branch_id'))->get(); }
    private function assertBranchAllowed(int $id): void { abort_unless($this->allowedBranches()->contains('id', $id), 403, 'You cannot import into that branch.'); }
    private function previewData(ConsignmentImportBatch $batch): array
    {
        $this->assertBranchIfSelected($batch);
        $rows = $batch->rows()->where('sheet_name', $batch->selected_sheet)->orderBy('spreadsheet_row')->limit(1000)->get();
        $header = optional($rows->firstWhere('spreadsheet_row', $batch->header_row))->raw_values ?? [];
        return compact('batch','rows','header') + ['fields' => self::FIELDS, 'sheets' => $batch->rows()->distinct()->pluck('sheet_name'), 'branches' => $this->allowedBranches(), 'countries' => Country::orderBy('name')->get(), 'states' => State::orderBy('name')->get()];
    }
    private function assertBranchIfSelected(ConsignmentImportBatch $batch): void { if ($batch->branch_id) $this->assertBranchAllowed((int)$batch->branch_id); }
    private function guessHeaderRow(ConsignmentImportBatch $batch, string $sheet): int
    {
        $best = 1; $score = -1;
        foreach ($batch->rows()->where('sheet_name', $sheet)->orderBy('spreadsheet_row')->limit(30)->get() as $row) { $s = count(array_intersect($this->normaliseAll($row->raw_values), $this->aliases())); if ($s > $score) { $score = $s; $best = $row->spreadsheet_row; } }
        return $best;
    }
    private function suggestMappings(ConsignmentImportBatch $batch): array
    {
        $raw = optional($batch->rows()->where('sheet_name', $batch->selected_sheet)->where('spreadsheet_row', $batch->header_row)->first())->raw_values ?? []; $map=[];
        foreach (self::FIELDS as $field => $def) foreach ($raw as $column => $heading) if (in_array($this->normalise($heading), array_merge([$this->normalise($def['label'])], $def['aliases']), true)) { $map[$field]=$column; break; }
        return $map;
    }
    private function validateRows(ConsignmentImportBatch $batch): void
    {
        $mappings = $batch->mappings ?: []; $seen=[]; $counts=['detected'=>0,'valid'=>0,'warning'=>0,'invalid'=>0,'duplicate'=>0,'selected'=>0];
        $rows = $batch->rows()->where('sheet_name',$batch->selected_sheet)->where('spreadsheet_row','>=',$batch->data_start_row)->get();
        foreach ($rows as $row) {
            $mapped=[]; foreach ($mappings as $field=>$column) $mapped[$field]=trim((string)($row->raw_values[$column] ?? ''));
            if (!array_filter($mapped, fn($v) => $v !== '')) { $row->update(['included'=>false,'status'=>'excluded','mapped_values'=>$mapped,'validation_errors'=>[],'validation_warnings'=>[]]); continue; }
            $counts['detected']++; $errors=[]; $warnings=[];
            foreach (self::FIELDS as $field=>$def) if ($def['required'] && empty($mapped[$field])) $errors[$field] = $def['label'].' is required.';
            if (!empty($mapped['phone'])) { $mapped['phone']=$this->phone($mapped['phone']); if (!preg_match('/^\d{7,15}$/',$mapped['phone'])) $errors['phone']='Enter a valid phone number (7–15 digits).'; elseif (!$this->findClient($mapped['phone'])) $errors['phone']='No customer account matches this phone number. Create or verify the customer first.'; }
            if (!empty($mapped['weight']) && !is_numeric($mapped['weight'])) $errors['weight']='Weight must be a number.';
            if (!empty($mapped['pieces']) && (!ctype_digit($mapped['pieces']) || (int)$mapped['pieces'] < 1)) $errors['pieces']='Pieces must be a whole number.';
            $status = $errors ? 'invalid' : 'valid';
            if (!$errors && !empty($mapped['hawb_number']) && (isset($seen[$mapped['hawb_number']]) || Shipment::where('code',$mapped['hawb_number'])->exists())) { $status='duplicate'; $warnings['hawb_number']='This parcel code already exists or appears more than once in this file.'; }
            $seen[$mapped['hawb_number'] ?? $row->id] = true; $row->update(['mapped_values'=>$mapped,'validation_errors'=>$errors,'validation_warnings'=>$warnings,'status'=>$status]);
            $counts[$status]++; if ($row->included && in_array($status,['valid','warning'])) $counts['selected']++;
        }
        $batch->update(['summary'=>$counts]);
    }
    private function phone(string $value): string { return preg_replace('/\D+/', '', $value); }
    private function findClient(string $phone): ?Client { $digits=$this->phone($phone); return Client::whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(responsible_mobile, ' ', ''), '+', ''), '-', ''), '(', ''), ')', '') = ?", [$digits])->where('is_archived',0)->first(); }
    private function normalise($value): string { return strtolower(trim(preg_replace('/[^a-z0-9]+/i',' ',(string)$value))); }
    private function normaliseAll(array $values): array { return array_map(fn($v)=>$this->normalise($v),$values); }
    private function aliases(): array { return array_merge(...array_map(fn($v)=>$v['aliases'],self::FIELDS)); }
}
