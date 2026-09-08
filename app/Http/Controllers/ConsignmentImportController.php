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
use Illuminate\Validation\ValidationException;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Package;
use Modules\Cargo\Entities\PackageShipment;
use Modules\Cargo\Entities\Shipment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ConsignmentImportController extends Controller
{
    private const FIELDS = [
        'hawb_number' => ['label' => 'HAWB / parcel code', 'required' => true, 'aliases' => ['hawb','hawb no','hawb number','parcel code','shipment code','tracking number']],
        'consignee_name' => ['label' => 'Consignee name', 'required' => true, 'aliases' => ['consignee','consignee name','consignee name address','receiver','receiver name','customer name','mark']],
        'phone' => ['label' => 'Customer phone number', 'required' => true, 'aliases' => ['phone','phone number','mobile','mobile number','telephone','tel','customer phone']],
        'destination' => ['label' => 'Destination', 'required' => false, 'aliases' => ['destination','delivery destination','city']],
        'weight' => ['label' => 'Weight', 'required' => false, 'aliases' => ['weight','gross weight','g w kgs','g w kg','kg','kgs']],
        'pieces' => ['label' => 'Number of pieces', 'required' => false, 'aliases' => ['pieces','piece','qty','quantity','no of pieces']],
        'description' => ['label' => 'Goods description', 'required' => false, 'aliases' => ['description','description of goods','goods','contents','item description']],
        'amount' => ['label' => 'Shipping amount', 'required' => false, 'aliases' => ['amount','bill','shipping cost','cost','charge']],
        'mawb_num' => ['label' => 'MAWB number', 'required' => false, 'aliases' => ['mawb','mawb no','mawb number']],
    ];

    public function upload(Request $request)
    {
        $this->authorizeImport();
        $request->validate(['shipment_type' => ['required', 'in:air,sea'], 'excel_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);
        $file = $request->file('excel_file');
        $uuid = (string) Str::uuid();
        $path = $file->storeAs('consignment-imports/'.$uuid, 'source.'.$file->getClientOriginalExtension(), 'local');

        $batch = null;
        try {
            $book = $this->loadSpreadsheet(Storage::disk('local')->path($path));
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
            $batch->update(['selected_sheet' => $firstSheet, 'header_row' => $this->guessHeaderRow($batch, $firstSheet), 'data_start_row' => 2,
                'consignment_code' => $this->guessConsignmentCode($batch, $firstSheet)]);
            $this->syncTargetConsignment($batch);
            $batch->update(['data_start_row' => $batch->header_row + 1, 'mappings' => $this->suggestMappings($batch)]);
            $this->validateRows($batch->fresh());
            return redirect()->route('consignment.import.preview', $batch->uuid);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            if ($batch) {
                $batch->rows()->delete();
                $batch->delete();
            }
            report($e);
            return back()->withInput()->with('error', 'The file could not be read: '.$e->getMessage());
        }
    }

    /**
     * Load workbooks that use root-relative ZIP member paths while open_basedir
     * is enabled. PhpSpreadsheet probes those member names with file_exists(),
     * which raises a warning even though it subsequently reads them from the
     * workbook archive. Keep open_basedir active and suppress only that probe.
     */
    private function loadSpreadsheet(string $path): Spreadsheet
    {
        $previousHandler = null;
        $previousHandler = set_error_handler(function ($severity, $message, $file, $line) use (&$previousHandler) {
            $isSpreadsheetArchiveProbe = $severity === E_WARNING
                && strpos($message, 'file_exists(): open_basedir restriction in effect.') === 0
                && substr(str_replace('\\', '/', $file), -31) === '/PhpSpreadsheet/Shared/File.php';

            if ($isSpreadsheetArchiveProbe) {
                return true;
            }

            return $previousHandler
                ? $previousHandler($severity, $message, $file, $line)
                : false;
        });

        try {
            return IOFactory::load($path);
        } finally {
            restore_error_handler();
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
        if ($request->filled('selected_header_row')) {
            $selectedHeaderRow = max(1, (int) $request->input('selected_header_row'));
            $request->merge(['header_row' => $selectedHeaderRow, 'data_start_row' => $selectedHeaderRow + 1]);
        }
        $sheets = $batch->rows()->distinct()->pluck('sheet_name')->all();
        $request->validate([
            'selected_sheet' => ['required', Rule::in($sheets)],
            'header_row' => ['required', 'integer', 'min:1'], 'data_start_row' => ['required', 'integer', 'min:1'],
            'consignment_code' => ['nullable', 'string', 'max:255'],
            'pickup_branch_id' => ['nullable', 'integer', Rule::in($this->allowedBranches()->pluck('id')->all())],
            'destination_branch_id' => ['nullable', 'integer', Rule::in($this->allowedBranches()->pluck('id')->all())],
            'mapping' => ['array'],
        ]);
        $requestedMappings=array_filter($request->input('mapping',[]),fn($value)=>$value !== null && $value !== '');
        if (collect($requestedMappings)->duplicatesStrict()->isNotEmpty()) {
            throw ValidationException::withMessages(['mapping'=>'Each spreadsheet column can only be matched to one system field.']);
        }
        $headerChanged = $batch->selected_sheet !== $request->selected_sheet || (int) $batch->header_row !== (int) $request->header_row;
        $batchData = $request->only('selected_sheet','header_row','data_start_row','consignment_code','pickup_branch_id','destination_branch_id');
        if ($request->filled('pickup_branch_id') && $request->filled('destination_branch_id')) {
            $pickupBranch = Branch::findOrFail($request->pickup_branch_id);
            $destinationBranch = Branch::findOrFail($request->destination_branch_id);
            if (!$pickupBranch->country_id || !$pickupBranch->state_id || !$destinationBranch->country_id || !$destinationBranch->state_id) {
                throw ValidationException::withMessages(['pickup_branch_id' => 'The selected branches need a country and province/state configured in Branches before they can be used.']);
            }
            $batchData += [
                'branch_id' => $pickupBranch->id,
                'from_country_id' => $pickupBranch->country_id,
                'from_state_id' => $pickupBranch->state_id,
                'to_country_id' => $destinationBranch->country_id,
                'to_state_id' => $destinationBranch->state_id,
                'default_destination' => $destinationBranch->address ?: $destinationBranch->name,
            ];
        }
        $batch->update($batchData);
        $this->syncTargetConsignment($batch);
        $mappings = $headerChanged ? $this->suggestMappings($batch->fresh()) : $requestedMappings;
        $batch->update(['mappings' => $mappings]);
        if ($headerChanged) {
            $batch->rows()->where('sheet_name', $batch->selected_sheet)->update(['included' => false]);
            $batch->rows()->where('sheet_name', $batch->selected_sheet)->where('spreadsheet_row', '>=', $batch->data_start_row)->update(['included' => true]);
        }
        $this->validateRows($batch->fresh());
        return redirect()->route('consignment.import.preview', $batch->uuid)->with('success', 'Preview updated. No records have been imported.');
    }

    public function confirm(Request $request, string $uuid)
    {
        $batch = $this->batch($uuid)->fresh();
        abort_if($batch->status === 'completed', 422, 'This import has already been completed.');
        foreach (['consignment_code','pickup_branch_id','destination_branch_id','branch_id','from_country_id','from_state_id','to_country_id','to_state_id'] as $field) abort_unless($batch->{$field}, 422, 'Enter the consignment code and choose pickup and destination branches before importing.');
        $this->assertBranchAllowed((int) $batch->pickup_branch_id);
        $this->assertBranchAllowed((int) $batch->destination_branch_id);
        $eligibleRows=$batch->rows()->whereIn('status',['new','update','unchanged']);
        $eligibleIds=$eligibleRows->pluck('id')->all();
        $selectedIds=array_values(array_intersect($eligibleIds,array_map('intval',array_keys($request->input('included',[])))));
        $eligibleRows->update(['included'=>false]);
        if ($selectedIds) $batch->rows()->whereIn('id',$selectedIds)->update(['included'=>true]);
        $this->validateRows($batch->fresh());
        $invalidSelected = $batch->rows()->where('included', true)->whereIn('status', ['invalid','conflict'])->count();
        abort_if($invalidSelected > 0, 422, 'Fix or exclude every invalid and conflicting selected row before confirming.');
        $rows = $batch->rows()->where('included', true)->whereIn('status', ['new','update','unchanged'])->get();
        abort_if($rows->isEmpty(), 422, 'There are no valid selected rows to import.');
        $first = $rows->first()->mapped_values;
        $consignment = $batch->mode === 'update' ? Consignment::find($batch->target_consignment_id) : null;
        abort_if($batch->mode === 'update' && !$consignment, 422, 'The existing consignment could not be found. Refresh the preview.');
        abort_if($batch->mode === 'create' && Consignment::where('consignment_code', $batch->consignment_code)->exists(), 422, 'This consignment now exists. Refresh the preview to enter update mode.');
        $package = Package::query()->orderBy('id')->first();
        abort_unless($package, 422, 'No package type is configured. Create a package type before importing.');

        $pickupBranch = Branch::findOrFail($batch->pickup_branch_id);
        $destinationBranch = Branch::findOrFail($batch->destination_branch_id);
        DB::transaction(function () use ($batch, $rows, $first, $package, $pickupBranch, $destinationBranch, $consignment) {
            if (!$consignment) {
                $consignment = Consignment::create(['consignment_code' => $batch->consignment_code, 'name' => 'Imported consignment',
                    'source' => $pickupBranch->name, 'destination' => $destinationBranch->name, 'status' => 'pending', 'cargo_type' => $batch->shipment_type,
                    'mawb_num' => $first['mawb_num'] ?? null]);
            } else {
                $consignment->update(['source' => $pickupBranch->name, 'destination' => $destinationBranch->name,
                    'cargo_type' => $batch->shipment_type, 'mawb_num' => $first['mawb_num'] ?? $consignment->mawb_num]);
            }
            $ids = []; $created = 0; $updated = 0; $unchanged = 0;
            foreach ($rows as $row) {
                $data = $row->mapped_values;
                $client = $this->resolveClient($data, $batch);
                $shipment = Shipment::where('code', $data['hawb_number'])->first();
                if ($shipment && (int) $shipment->consignment_id !== (int) $consignment->id) throw new \RuntimeException('A parcel code now belongs to another consignment. Review the preview again.');
                $shipmentData = ['consignment_id' => $consignment->id, 'code' => $data['hawb_number'], 'branch_id' => $pickupBranch->id, 'next_destination' => $destinationBranch->name,
                    'client_id' => $client->id, 'client_phone' => $data['phone'], 'reciver_name' => $data['consignee_name'], 'reciver_phone' => $data['phone'],
                    'reciver_address' => $data['destination'], 'from_country_id' => $batch->from_country_id, 'from_state_id' => $batch->from_state_id,
                    'to_country_id' => $batch->to_country_id, 'to_state_id' => $batch->to_state_id, 'payment_type' => Shipment::POSTPAID,
                    'shipping_cost' => $this->number($data['amount'] ?? 0), 'total_weight' => $this->number($data['weight'] ?? 0)];
                if (!$shipment) {
                    $shipment = Shipment::create($shipmentData + ['status_id' => Shipment::PENDING_STATUS, 'type' => Shipment::PICKUP,
                        'shipping_date' => now()->toDateString(), 'client_status' => Shipment::CLIENT_STATUS_CREATED]);
                    PackageShipment::create(['package_id' => $package->id, 'shipment_id' => $shipment->id, 'description' => $data['description'] ?? null,
                        'weight' => $data['weight'] ?? 0, 'qty' => $data['pieces'] ?? 1]);
                    $created++;
                } elseif ($row->status === 'update') {
                    $shipment->update($shipmentData);
                    $packageRow = $shipment->packageShipments()->first();
                    $packageData = ['description' => $data['description'] ?? null, 'weight' => $data['weight'] ?? 0, 'qty' => $data['pieces'] ?? 1];
                    $packageRow ? $packageRow->update($packageData) : PackageShipment::create($packageData + ['package_id' => $package->id, 'shipment_id' => $shipment->id]);
                    $updated++;
                } else {
                    $unchanged++;
                }
                $row->update(['status' => 'imported']); $ids[] = $shipment->id;
            }
            $batch->update(['status' => 'completed', 'confirmed_at' => now(), 'result' => ['consignment_id' => $consignment->id,
                'shipment_ids' => $ids, 'imported' => count($ids), 'created' => $created, 'updated' => $updated, 'unchanged' => $unchanged]]);
        });
        $result = $batch->fresh()->result;
        return redirect()->route('consignment.import.preview', $batch->uuid)->with('success', 'Import completed: '.$result['created'].' added, '.$result['updated'].' updated, '.$result['unchanged'].' unchanged.');
    }

    private function batch(string $uuid): ConsignmentImportBatch
    {
        $this->authorizeImport();
        return ConsignmentImportBatch::where('uuid', $uuid)->where('created_by', auth()->id())->firstOrFail();
    }
    private function authorizeImport(): void { abort_unless(auth()->check() && auth()->user()->can('import-consignments'), 403); }
    private function allowedBranches() { return Branch::where('is_archived', 0)->orderBy('name')->get(); }
    private function assertBranchAllowed(int $id): void { abort_unless($this->allowedBranches()->contains('id', $id), 403, 'You cannot import into that branch.'); }
    private function previewData(ConsignmentImportBatch $batch): array
    {
        $this->assertBranchIfSelected($batch);
        $rows = $batch->rows()->where('sheet_name', $batch->selected_sheet)->orderBy('spreadsheet_row')->limit(1000)->get();
        $header = optional($rows->firstWhere('spreadsheet_row', $batch->header_row))->raw_values ?? [];
        $targetConsignment = $batch->target_consignment_id ? Consignment::withCount('shipments')->find($batch->target_consignment_id) : null;
        return compact('batch','rows','header','targetConsignment') + ['fields' => self::FIELDS, 'sheets' => $batch->rows()->distinct()->pluck('sheet_name'), 'branches' => $this->allowedBranches()];
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
        $raw = optional($batch->rows()->where('sheet_name', $batch->selected_sheet)->where('spreadsheet_row', $batch->header_row)->first())->raw_values ?? [];
        $map=[]; $used=[];
        foreach (self::FIELDS as $field => $def) {
            foreach ($raw as $column => $heading) {
                if (isset($used[$column])) continue;
                if (in_array($this->normalise($heading), array_merge([$this->normalise($def['label'])], $def['aliases']), true)) {
                    $map[$field]=$column; $used[$column]=true; break;
                }
            }
        }
        if (empty($map['phone']) && ($phoneColumn = $this->guessPhoneColumn($batch, array_keys($used)))) $map['phone']=$phoneColumn;
        return $map;
    }
    private function validateRows(ConsignmentImportBatch $batch): void
    {
        $mappings = $batch->mappings ?: []; $seen=[];
        $counts=['detected'=>0,'new'=>0,'update'=>0,'unchanged'=>0,'invalid'=>0,'conflict'=>0,'selected'=>0];
        $rows = $batch->rows()->where('sheet_name',$batch->selected_sheet)->where('spreadsheet_row','>=',$batch->data_start_row)->get();
        foreach ($rows as $row) {
            $mapped=[]; foreach ($mappings as $field=>$column) $mapped[$field]=trim((string)($row->raw_values[$column] ?? ''));
            if (!array_filter($mapped, fn($v) => $v !== '')) { $row->update(['included'=>false,'status'=>'excluded','mapped_values'=>$mapped,'validation_errors'=>[],'validation_warnings'=>[]]); continue; }
            if (empty($mapped['destination'])) $mapped['destination'] = $batch->default_destination ?? '';
            $counts['detected']++; $errors=[]; $warnings=[];
            foreach (self::FIELDS as $field=>$def) if ($def['required'] && empty($mapped[$field])) $errors[$field] = $def['label'].' is required.';
            if (empty($mapped['destination'])) $errors['destination'] = 'Map a destination column or enter one destination for the whole file.';
            if (!empty($mapped['phone'])) {
                $mapped['phone']=$this->phone($mapped['phone']);
                if (!preg_match('/^\d{7,15}$/',$mapped['phone'])) {
                    $errors['phone']='Enter a valid phone number (7–15 digits).';
                } else {
                    $client=$this->findClientForImport($mapped['phone'], $mapped['consignee_name'] ?? '');
                    if (!$client) $warnings['customer']='A new customer profile will be created when you confirm the import.';
                    elseif (!$this->phone((string)$client->responsible_mobile)) $warnings['customer']='This phone number will be added to the existing customer profile when you confirm.';
                }
            }
            if (!empty($mapped['hawb_number']) && !preg_match('/^[A-Za-z0-9]+(?:[-\/]?[A-Za-z0-9]+)*$/', $mapped['hawb_number'])) $errors['hawb_number']='Parcel code contains invalid characters.';
            if (!empty($mapped['weight']) && $this->number($mapped['weight']) === null) $errors['weight']='Weight must be a number.';
            if (!empty($mapped['pieces']) && (!ctype_digit($mapped['pieces']) || (int)$mapped['pieces'] < 1)) $errors['pieces']='Pieces must be a whole number.';
            $status = $errors ? 'invalid' : 'new';
            if (!$errors && isset($seen[$mapped['hawb_number']])) {
                $status='conflict'; $warnings['hawb_number']='This parcel code appears more than once in this file.';
            } elseif (!$errors) {
                $existing = Shipment::where('code', $mapped['hawb_number'])->first();
                if ($existing && (!$batch->target_consignment_id || (int) $existing->consignment_id !== (int) $batch->target_consignment_id)) {
                    $status='conflict'; $warnings['hawb_number']='This parcel code belongs to another consignment.';
                } elseif ($existing) {
                    $changes = $this->shipmentChanges($existing, $mapped, $batch);
                    $status = $changes ? 'update' : 'unchanged';
                    if ($changes) $warnings['changes'] = 'Will update: '.implode(', ', $changes).'.';
                }
            }
            $seen[$mapped['hawb_number'] ?? $row->id] = true; $row->update(['mapped_values'=>$mapped,'validation_errors'=>$errors,'validation_warnings'=>$warnings,'status'=>$status]);
            if (in_array($status,['invalid','conflict'],true)) $row->update(['included'=>false]);
            $counts[$status]++; if ($row->included && in_array($status,['new','update','unchanged'])) $counts['selected']++;
        }
        $batch->update(['summary'=>$counts]);
    }

    private function shipmentChanges(Shipment $shipment, array $data, ConsignmentImportBatch $batch): array
    {
        $client = $this->findClientForImport($data['phone'], $data['consignee_name'] ?? '');
        $checks = [
            'customer' => [(int) $shipment->client_id, (int) optional($client)->id],
            'phone' => [$this->phone((string) $shipment->client_phone), $data['phone']],
            'receiver phone' => [$this->phone((string) $shipment->reciver_phone), $data['phone']],
            'consignee' => [trim((string) $shipment->reciver_name), trim((string) $data['consignee_name'])],
            'destination' => [trim((string) $shipment->reciver_address), trim((string) $data['destination'])],
            'amount' => [(float) $shipment->shipping_cost, (float) ($this->number($data['amount'] ?? 0) ?? 0)],
            'weight' => [(float) $shipment->total_weight, (float) ($this->number($data['weight'] ?? 0) ?? 0)],
        ];
        if ($batch->pickup_branch_id && $batch->destination_branch_id) {
            $destinationBranch = Branch::find($batch->destination_branch_id);
            $checks += [
                'pickup branch' => [(int) $shipment->branch_id, (int) $batch->pickup_branch_id],
                'destination branch' => [trim((string) $shipment->next_destination), trim((string) optional($destinationBranch)->name)],
                'origin country' => [(int) $shipment->from_country_id, (int) $batch->from_country_id],
                'origin province/state' => [(int) $shipment->from_state_id, (int) $batch->from_state_id],
                'destination country' => [(int) $shipment->to_country_id, (int) $batch->to_country_id],
                'destination province/state' => [(int) $shipment->to_state_id, (int) $batch->to_state_id],
            ];
        }
        $package = $shipment->packageShipments()->first();
        $checks += [
            'goods description' => [trim((string) optional($package)->description), trim((string) ($data['description'] ?? ''))],
            'package weight' => [(float) (optional($package)->weight ?? 0), (float) ($this->number($data['weight'] ?? 0) ?? 0)],
            'pieces' => [(float) (optional($package)->qty ?? 0), (float) ($data['pieces'] ?? 1)],
        ];
        return array_keys(array_filter($checks, fn ($values) => $values[0] !== $values[1]));
    }
    private function phone(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\+?\d+\.0+$/', $value)) $value = preg_replace('/\.0+$/', '', $value);
        return preg_replace('/\D+/', '', $value);
    }
    private function number($value): ?float { $clean = preg_replace('/[^0-9.\-]/', '', (string) $value); return $clean !== '' && is_numeric($clean) ? (float) $clean : null; }
    private function findClientForImport(string $phone, string $name): ?Client
    {
        $phone=$this->phone($phone);
        $name=$this->normalise($name);
        if ($name === '' || $phone === '') return null;
        $nameMatches=Client::whereRaw('LOWER(TRIM(name)) = ?', [$name])->where('is_archived',0)->limit(10)->get();
        $samePhone=$nameMatches->filter(fn($client)=>$this->phonesMatch($client->responsible_mobile,$phone));
        if ($samePhone->count() === 1) return $samePhone->first();
        $withoutPhone=$nameMatches->filter(fn($client)=>$this->phone((string)$client->responsible_mobile) === '');
        if ($nameMatches->count() === 1 && $withoutPhone->count() === 1) return $withoutPhone->first();

        $normalisedPhone="REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(responsible_mobile, ' ', ''), '+', ''), '-', ''), '(', ''), ')', ''), '.', '')";
        $phoneMatches=Client::whereRaw("$normalisedPhone LIKE ?", ['%'.substr($phone,-9)])->where('is_archived',0)->limit(20)->get()
            ->filter(fn($client)=>$this->phonesMatch($client->responsible_mobile,$phone) && $this->normalise($client->name) === $name);
        return $phoneMatches->count() === 1 ? $phoneMatches->first() : null;
    }
    private function phonesMatch($left, $right): bool
    {
        $left=$this->phone((string)$left); $right=$this->phone((string)$right);
        if ($left === '' || $right === '') return false;
        return $left === $right || (strlen($left) >= 9 && strlen($right) >= 9 && substr($left,-9) === substr($right,-9));
    }
    private function resolveClient(array $data, ConsignmentImportBatch $batch): Client
    {
        $phone=$this->phone($data['phone']);
        $name=trim($data['consignee_name']);
        $client=$this->findClientForImport($phone,$name);
        if ($client) {
            if (!$this->phone((string)$client->responsible_mobile)) {
                $client->update(['responsible_mobile'=>$phone,'responsible_name'=>$client->responsible_name ?: $name,'branch_id'=>$client->branch_id ?: $batch->pickup_branch_id,'updated_by'=>auth()->id()]);
                if ($client->user_id && ($user=User::find($client->user_id)) && !$this->phone((string)$user->responsible_mobile)) $user->update(['responsible_mobile'=>$phone]);
            }
            return $client;
        }
        $email='imported+'.$phone.'.'.substr(sha1($this->normalise($name)),0,10).'@newworldcargo.invalid';
        $user=User::firstOrCreate(['email'=>$email], ['name'=>$name,'password'=>bcrypt(Str::random(40)),'responsible_mobile'=>$phone,'role'=>4,'verified'=>0]);
        $client=Client::firstOrCreate(['user_id'=>$user->id], ['code'=>0,'name'=>$name,'email'=>$email,'responsible_name'=>$name,'responsible_mobile'=>$phone,'branch_id'=>$batch->pickup_branch_id,'is_archived'=>0,'created_by'=>auth()->id()]);
        if (!$client->code) $client->update(['code'=>$client->id]);
        return $client;
    }
    private function guessPhoneColumn(ConsignmentImportBatch $batch, array $usedColumns): ?string
    {
        $rows=$batch->rows()->where('sheet_name',$batch->selected_sheet)->where('spreadsheet_row','>',$batch->header_row)->orderBy('spreadsheet_row')->limit(30)->get();
        $columns=array_keys(optional($batch->rows()->where('sheet_name',$batch->selected_sheet)->where('spreadsheet_row',$batch->header_row)->first())->raw_values ?? []);
        $best=null; $bestScore=0;
        foreach ($columns as $column) {
            if (in_array($column,$usedColumns,true)) continue;
            $values=$rows->pluck('raw_values')->map(fn($values)=>trim((string)($values[$column] ?? '')))->filter(fn($value)=>$value !== '');
            if ($values->count() < 2) continue;
            $valid=$values->filter(fn($value)=>preg_match('/^\d{7,15}$/',$this->phone($value)))->count();
            $score=$valid/$values->count();
            if ($valid >= 2 && $score > $bestScore) { $best=$column; $bestScore=$score; }
        }
        return $bestScore >= .7 ? $best : null;
    }
    private function normalise($value): string { return strtolower(trim(preg_replace('/[^a-z0-9]+/i',' ',(string)$value))); }
    private function normaliseAll(array $values): array { return array_map(fn($v)=>$this->normalise($v),$values); }
    private function aliases(): array { return array_merge(...array_values(array_map(fn($v)=>$v['aliases'], self::FIELDS))); }
    private function syncTargetConsignment(ConsignmentImportBatch $batch): void
    {
        $target = $batch->consignment_code ? Consignment::where('consignment_code', trim($batch->consignment_code))->first() : null;
        $batch->update(['mode' => $target ? 'update' : 'create', 'target_consignment_id' => optional($target)->id]);
    }
    private function guessConsignmentCode(ConsignmentImportBatch $batch, string $sheet): ?string
    {
        foreach ($batch->rows()->where('sheet_name', $sheet)->orderBy('spreadsheet_row')->limit(30)->get() as $row) {
            $values = $row->raw_values; $columns = array_keys($values);
            foreach ($columns as $index => $column) {
                if (!in_array($this->normalise($values[$column]), ['job no','job number','consignment code','container','container no','container number'], true)) continue;
                for ($next = $index + 1; $next < count($columns); $next++) if (trim((string) $values[$columns[$next]]) !== '') return trim((string) $values[$columns[$next]]);
            }
        }
        return null;
    }
}
