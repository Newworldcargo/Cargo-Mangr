<?php

namespace App\Http\Controllers;

use App\Exports\ShipmentExport;
use App\Http\Controllers\Controller;
use App\Models\Consignment;
use App\Models\Transxn;
use App\Models\User;
use App\Traits\Twilio;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\PackageShipment;
use Modules\Cargo\Entities\Client;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;

class ConsignmentController extends Controller
{
    use Twilio;

    /**
     * A consignment is shared across the company and can contain shipments
     * from more than one branch. Until it has a branch-safe ownership model,
     * only a top admin may alter it or import/delete its linked records.
     */
    private function authorizeConsignmentMutation(): void
    {
        abort_unless(auth()->check() && (int) auth()->user()->role === User::ADMIN, 403);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $consignments = Consignment::query()
            ->withCount('shipments')
            ->orderByDesc('created_at')
            ->paginate(200);

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.consignments.index', compact('consignments'));
    }

    public function import(Request $request)
    {
        $this->authorizeConsignmentMutation();
        // dd('here');
        try {
            switch ($request->shipment_type) {
                case 'sea':
                    $this->importSea($request);
                    break;
                case 'air':
                    $this->importAir($request);
                    break;
                default:
                    break;
            }
            return redirect()->back()->with('success', 'Excel data imported successfully!');
        } catch (\Throwable $th) {
            dd('Entry Error'.$th);
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function importSea($request)
    {
        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Step 3: Extract and create shipments
            $xlsSize = count($rows[8]);
            // dd($rows[8]);
            // dd($xlsSize);
            switch($xlsSize){
                case 12:
                    $this->manifest_sea($rows);
                    break;
                default: //14,15,16
                    $this->manifest_sea_default($rows);
                    break;
            }
            return true;
        } catch (\Throwable $th) {
            dd('Excel Format Error: '. $th->getMessage());
            return false;
        }
    }

    //size 12
    public function manifest_sea($rows){
        $code = $rows[2][0] ?? null;
        $dateRaw = $rows[2][5] ?? null;
        $date = $this->extractDate($dateRaw);

        $destAgent = $rows[5][0] ?? null;
        $voyageRaw = $rows[5][4] ?? '';
        preg_match('/Vessel \/ Voyage No ?: (.*)/', $voyageRaw, $voyageMatch);
        $voyage_no = $voyageMatch[1] ?? null;

        $departureRaw = $rows[5][8] ?? '';
        $departure_date = $this->extractDate($departureRaw);

        $shippingRaw = $rows[6][0] ?? '';
        $shipping_line = trim(str_replace('Shipping line :', '', $shippingRaw));

        $destinationRaw = $rows[6][4] ?? $rows[6][4];
        $destination = trim(str_replace('Destination:', '', $destinationRaw));

        $arrivalRaw = $rows[6][8] ?? '';
        $arrival_date = $this->extractDate($arrivalRaw);

        // Step 2: Create consignment
        $consignment = Consignment::create([
            'consignment_code' => $code,
            'name' => 'NEWWORLD INVESTMENT LIMITED',
            'voyage_no' => $voyage_no,
            'date' => $date,
            'departure_date' => $departure_date,
            'shipping_line' => $shipping_line,
            'arrival_date' => $arrival_date,
            'destination' => $destination,
            'cargo_type' => 'sea'
        ]);

        for ($i = 9; $i < count($rows); $i++) {
            $row = $rows[$i];

            if($row[2] !== null){
                if (empty($row[0]) || Str::startsWith($row[0], 'HB')) {
                    continue; // Skip empty rows or header
                }
                $email = strtolower(str_replace(' ', '', $row[2])) . '@mail.com';
                $username = strtolower(str_replace(' ', '', $row[2]));
                $clientCode = rand(100000, 999999);
                $clientPhone = preg_replace('/\D+/', '', $row[6]);

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $row[2],
                        'password' => bcrypt('password123'),
                        'role' => 4,
                        'verified' => 1
                    ]
                );

                $client = Client::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'code' => $clientCode,
                        'name' => $row[2],
                        'email' => $email,
                        'address' => $email.' '.$row[2],
                    ]
                );

                $shipment = Shipment::updateOrCreate(
                    [
                        'consignment_id' => $consignment->id,
                        'code' => $row[0], // hbl No
                    ],
                    [
                        'client_id' => $client->id,
                        'branch_id' => 1,
                        'type' => 1,
                        'status_id' => 1,
                        'client_status' => 1,
                        'from_country_id' => 1,
                        'from_state_id' => 1,
                        'to_country_id' => 1,
                        'to_state_id' => 1,
                        'shipping_cost' => (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $row[10])),
                        'return_cost' => 0,
                        'amount_to_be_collected' => (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $row[10])),
                        'shipping_date' => Carbon::now(),
                        'total_weight' => (float)($row[6] ?? 0),
                        'client_address' => $username,
                        'client_phone' => $clientPhone,
                        'salesman' => $row[9],
                        'dest_port' => $row[8],
                    ]
                );

                PackageShipment::updateOrCreate(
                    [
                        'shipment_id' => $shipment->id,
                        'package_id' => 1,
                    ],
                    [
                        'description' => $row[3],
                        'qty' => $row[5],
                        'weight' => $row[6],
                        'length' => 1,
                        'width' => 1,
                        'height' => 1,
                    ]
                );

            }
        }
    }

    //Size 15
    public function manifest_sea14($rows){
        $code = $rows[2][0] ?? null;
        // Debug the initial code value
        \Log::info('Initial consignment code value:', ['code' => $code, 'row_2' => $rows[2] ?? 'empty']);

        // dd($code);
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Date:')) {
                    $rawDate = str_replace('Date:', '', $cell);
                    $date = date('Y-m-d', strtotime($rawDate));
                    break 2; // stop once found
                }
            }
        }

        $destAgent = $rows[5][0] ?? null;

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Dest Agent :')) {
                    $destAgentRaw = str_replace('Dest Agent :', '', $cell);
                    $destAgent = ltrim(trim($destAgentRaw), '/-.: ');
                    break 2;
                }
            }
        }

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Vessel / Voyage No :')) {
                    $voyageRaw = str_replace('Vessel / Voyage No :', '', $cell);
                    $voyage_no = ltrim(trim($voyageRaw), '/-.: ');
                    break 2;
                }
            }
        }
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Departure Date :')) {
                    $rawDate = str_replace('Departure Date :', '', $cell);
                    $rawDate = ltrim(trim($rawDate), '/-.: ');
                    $departure_date = date('Y-m-d', strtotime($rawDate));
                    break 2;
                }
            }
        }
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Shipping line :')) {
                    $line = str_replace('Shipping line :', '', $cell);
                    $shipping_line = ltrim(trim($line), '/-.: ');
                    break 2;
                }
            }
        }

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Destination:')) {
                    $destinationRaw = str_replace('Destination:', '', $cell);
                    $destination = ltrim(trim($destinationRaw), '/-.: ');
                    break 2;
                }
            }
        }
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Arrive Date :')) {
                    $arrivalRaw = str_replace('Arrive Date :', '', $cell);
                    $arrivalRaw = ltrim(trim($arrivalRaw), '/-.: ');
                    $arrival_date = date('Y-m-d', strtotime($arrivalRaw));
                    break 2;
                }
            }
        }

        $arrivalDate = !empty($arrivalRaw) ? date('Y-m-d', strtotime($arrivalRaw)) : null;

        // Debug the $code value
        \Log::info('Consignment code value:', ['code' => $code]);

        if (empty($code)) {
            throw new \Exception('Consignment code is required but was empty');
        }

        $consignment = Consignment::create([
            'consignment_code' => $code,
            'name' => 'NEWWORLD INVESTMENT LIMITED',
            'voyage_no' => $voyage_no,
            'date' => $date,
            'departure_date' => $departure_date,
            'shipping_line' => $shipping_line,
            'arrival_date' => $arrivalDate,
            'eta_dar' => $arrivalDate,
            'destination' => $destination,
            'cargo_type' => 'sea',
        ]);

        for ($i = 9; $i < count($rows); $i++) {
            $row = $rows[$i];
            if($row[2] !== null){
                if (empty($row[0]) || Str::startsWith($row[0], 'HB')) {
                    continue; // Skip empty rows or header
                }
                $email = strtolower(str_replace(' ', '', $row[2])) . '@mail.com';
                $username = strtolower(str_replace(' ', '', $row[2]));
                $clientCode = rand(100000, 999999);
                $clientPhone = preg_replace('/\D+/', '', $row[9]);

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $row[2],
                        'password' => bcrypt('password123'),
                        'role' => 4,
                        'verified' => 1
                    ]
                );

                $client = Client::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'code' => $clientCode,
                        'name' => $row[2],
                        'email' => $email,
                        'address' => $email.' '.$row[2],
                    ]
                );

                $shipment = Shipment::updateOrCreate(
                    [
                        'consignment_id' => $consignment->id,
                        'code' => $row[0], // hbl No
                    ],
                    [
                        'client_id' => $client->id,
                        'branch_id' => 1,
                        'type' => 1,
                        'status_id' => 1,
                        'client_status' => 1,
                        'from_country_id' => 1,
                        'from_state_id' => 1,
                        'to_country_id' => 1,
                        'to_state_id' => 1,
                        'shipping_cost' => (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $row[13])),
                        'return_cost' => 0,
                        'amount_to_be_collected' => (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $row[13])),
                        'shipping_date' => Carbon::now(),
                        'volume' => (float)$row[7],
                        'total_weight' => (float)($row[8] ?? 0),
                        'client_address' => $username,
                        'client_phone' => $clientPhone,
                        'salesman' => $row[11],
                        'dest_port' => $row[10],
                    ]
                );

                PackageShipment::updateOrCreate(
                    [
                        'shipment_id' => $shipment->id,
                        'package_id' => 1,
                    ],
                    [
                        'description' => $row[4],
                        'qty' => $row[6],
                        'weight' => $row[8] ?? 0,
                        'length' => 1,
                        'width' => 1,
                        'height' => 1,
                    ]
                );

            }
        }
    }


    //Size 15
    public function manifest_sea_default($rows){
        $code = $rows[2][0] ?? null;
        // Debug the initial code value
        \Log::info('Initial consignment code value:', ['code' => $code, 'row_2' => $rows[2] ?? 'empty']);

        // dd($code);
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Date:')) {
                    $rawDate = str_replace('Date:', '', $cell);
                    $date = date('Y-m-d', strtotime($rawDate));
                    break 2; // stop once found
                }
            }
        }

        $destAgent = $rows[5][0] ?? null;

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Dest Agent :')) {
                    $destAgentRaw = str_replace('Dest Agent :', '', $cell);
                    $destAgent = ltrim(trim($destAgentRaw), '/-.: ');
                    break 2;
                }
            }
        }

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Vessel / Voyage No :')) {
                    $voyageRaw = str_replace('Vessel / Voyage No :', '', $cell);
                    $voyage_no = ltrim(trim($voyageRaw), '/-.: ');
                    break 2;
                }
            }
        }
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Departure Date :')) {
                    $rawDate = str_replace('Departure Date :', '', $cell);
                    $rawDate = ltrim(trim($rawDate), '/-.: ');
                    $departure_date = date('Y-m-d', strtotime($rawDate));
                    break 2;
                }
            }
        }
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Shipping line :')) {
                    $line = str_replace('Shipping line :', '', $cell);
                    $shipping_line = ltrim(trim($line), '/-.: ');
                    break 2;
                }
            }
        }

        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Destination:')) {
                    $destinationRaw = str_replace('Destination:', '', $cell);
                    $destination = ltrim(trim($destinationRaw), '/-.: ');
                    break 2;
                }
            }
        }
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if ($cell && str_starts_with($cell, 'Arrive Date :')) {
                    $arrivalRaw = str_replace('Arrive Date :', '', $cell);
                    $arrivalRaw = ltrim(trim($arrivalRaw), '/-.: ');
                    $arrival_date = date('Y-m-d', strtotime($arrivalRaw));
                    break 2;
                }
            }
        }

        $arrivalDate = !empty($arrivalRaw) ? date('Y-m-d', strtotime($arrivalRaw)) : null;

        // Debug the $code value
        \Log::info('Consignment code value:', ['code' => $code]);

        if (empty($code)) {
            throw new \Exception('Consignment code is required but was empty');
        }

        $consignment = Consignment::create([
            'consignment_code' => $code,
            'name' => 'NEWWORLD INVESTMENT LIMITED',
            'voyage_no' => $voyage_no,
            'date' => $date,
            'departure_date' => $departure_date,
            'shipping_line' => $shipping_line,
            'arrival_date' => $arrivalDate,
            'eta_dar' => $arrivalDate,
            'destination' => $destination,
            'cargo_type' => 'sea',
        ]);

        // dd($consignment);

        for ($i = 9; $i < count($rows); $i++) {
            $row = $rows[$i];

            if($row[2] !== null){
                if (empty($row[0]) || Str::startsWith($row[0], 'HB')) {
                    continue; // Skip empty rows or header
                }
                $email = strtolower(str_replace(' ', '', $row[2])) . '@mail.com';
                $username = strtolower(str_replace(' ', '', $row[2]));
                $clientCode = rand(100000, 999999);
                $clientPhone = preg_replace('/\D+/', '', $row[9]);

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $row[2],
                        'password' => bcrypt('password123'),
                        'role' => 4,
                        'verified' => 1
                    ]
                );

                $client = Client::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'code' => $clientCode,
                        'name' => $row[2],
                        'email' => $email,
                        'address' => $email.' '.$row[2],
                    ]
                );

                $shipment = Shipment::updateOrCreate(
                    [
                        'consignment_id' => $consignment->id,
                        'code' => $row[0], // hbl No
                    ],
                    [
                        'client_id' => $client->id,
                        'branch_id' => 1,
                        'type' => 1,
                        'status_id' => 1,
                        'client_status' => 1,
                        'from_country_id' => 1,
                        'from_state_id' => 1,
                        'to_country_id' => 1,
                        'to_state_id' => 1,
                        'shipping_cost' => (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $row[13])),
                        'return_cost' => 0,
                        'amount_to_be_collected' => (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $row[13])),
                        'shipping_date' => Carbon::now(),
                        'volume' => (float)$row[7],
                        'total_weight' => (float)($row[8] ?? 0),
                        'client_address' => $username,
                        'client_phone' => $clientPhone,
                        'salesman' => $row[11],
                        'dest_port' => $row[10],
                    ]
                );

                PackageShipment::updateOrCreate(
                    [
                        'shipment_id' => $shipment->id,
                        'package_id' => 1,
                    ],
                    [
                        'description' => $row[4],
                        'qty' => $row[6],
                        'weight' => $row[8] == null ? 0 : (float)$row[8],
                        'length' => 1,
                        'width' => 1,
                        'height' => 1,
                    ]
                );

            }
        }
    }

    private function extractDate($text)
    {
        if (preg_match('/(\d{4}-\d{1,2}-\d{1,2})/', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function importAir($request){
        // dd('here');
        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            $consigneeRow = null;
            $mawbRow = null;
            $headerRow = null;



            // Step 1: Find the row containing both "Consignee" and "Job No"
            foreach ($rows as $rowIndex => $row) {
                $rowText = implode(' ', array_map('trim', $row));
                if (stripos($rowText, 'consignee') !== false && stripos($rowText, 'job no') !== false) {
                    $consigneeRow = $rowIndex;
                    break;
                }
            }

            if (is_null($consigneeRow)) {
                throw new \Exception("Row with both 'Consignee' and 'Job No' not found.");
            }

            // Step 2: Find Mawb No in the next few rows
            $mawbKeywords = ['Mawb No', 'Mawb No.', 'Mawb No :', 'Mawb No.:'];
            $mawbNum = null;

            for ($i = $consigneeRow + 1; $i <= $consigneeRow + 5 && $i < count($rows); $i++) {
                foreach ($rows[$i] as $k => $cell) {
                    if (!$cell) continue;
                    foreach ($mawbKeywords as $keyword) {
                        if (stripos($cell, $keyword) !== false) {
                            $mawbNum = $rows[$i][$k + 1] ?? null;
                            break 3;
                        }
                    }
                }
            }

            // dd($mawbNum);
            // if (!$mawbNum) {
            //     throw new \Exception("Mawb No. not found below 'Consignee' row.");
            // }

            // Step 3: Find Job No from same row as Consignee
            $jobNum = null;
            $jobKeywords = ['Job No', 'Job No.', 'Job No :', 'Job No.:'];

            foreach ($rows[$consigneeRow] as $k => $cell) {
                $cleanedCell = preg_replace('/\s+/', ' ', trim($cell)); // Normalize all whitespace to single space
                foreach ($jobKeywords as $keyword) {
                    if (stripos($cleanedCell, $keyword) !== false) {
                        // Check next non-empty cell for actual Job No value
                        for ($j = $k + 1; $j < count($rows[$consigneeRow]); $j++) {
                            $nextCell = trim($rows[$consigneeRow][$j]);
                            if (!empty($nextCell)) {
                                $jobNum = $nextCell;
                                break 2; // Break out of both loops
                            }
                        }
                    }
                }
            }

            // dd($jobNum);
            if (!$jobNum) {
                throw new \Exception("Job No. not found in the same row as 'Consignee'.");
            }

            $consignmentCode = $jobNum;
            $consignment = Consignment::where('consignment_code', $consignmentCode)->first();
            // if(empty($consignment)){
            //     $consignment = Consignment::where('mawb_num', $mawbNum)
            //     ->first();
            // }

            if (empty($consignment)) {
                // Create a new record if it doesn't exist
                $consignment = Consignment::create([
                    'job_num' => $jobNum,
                    'mawb_num' => $mawbNum,
                    'consignment_code' => $consignmentCode,
                    'name' => 'NWC',
                    'desc' => 'Consignment shipments',
                    'consignee' => 'Nwc',
                    'cargo_type' => 'air'
                ]);
            }

            // dd($consignment);

            // Step 4: Find the row with "Hawb No"
            foreach ($rows as $i => $row) {
                foreach ($row as $cell) {
                    if (stripos($cell, 'hawb no') !== false) {
                        $headerRow = $i;
                        break 2;
                    }
                }
            }

            // dd($headerRow);
            if (is_null($headerRow)) {
                throw new \Exception("Header row containing 'Hawb No' not found.");
            }

            //Different excel sheet having different size of array length / format
            $xlsSize = count($rows[8]);
            // dd($xlsSize);
            switch ($xlsSize) {
                case 8:
                        // Step 5: Loop through data starting after headerRow
                        $res = $this->loopCreateShipment($headerRow, $rows, $consignment);
                        if ($res !== 1) {
                            $res = $this->loopCreateShipmentII($headerRow, $rows, $consignment);
                        }
                    break;
                case 9:
                        // Step 5: Loop through data starting after headerRow
                        $this->loopCreateShipmentSize9($headerRow, $rows, $consignment);
                    break;
                case 10:
                        // Step 5: Loop through data starting after headerRow
                        $this->loopCreateShipmentSize9($headerRow, $rows, $consignment);
                    break;
                case 12:
                        // Step 5: Loop through data starting after headerRow
                        $this->loopCreateShipmentSize9($headerRow, $rows, $consignment);
                    break;

                default:
                        // Step 5: Loop through data starting after headerRow
                        $res = $this->loopCreateShipment($headerRow, $rows, $consignment);

                        // dd('loopCreateShipment: '.(boolean)$res);
                        if ($res !== 1) {
                            $res = $this->loopCreateShipmentII($headerRow, $rows, $consignment);
                        }
                    break;
            }


            DB::commit();
            return redirect()->back()->with('success', 'Excel data imported successfully!');
        } catch (\Exception $e) {
            dd('Oops');
            dd('General Error '.$e);
            DB::rollback();
            return redirect()->back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function loopCreateShipment($headerRow, $rows, $consignment)
    {
        try {

            for ($i = $headerRow + 1; $i < count($rows) - 1; $i++) {
                // $rowText = implode(' ', array_map('trim', $rows[$i]));
                // if (stripos($rowText, 'total') !== false) {
                //     break;
                // }

                $data = $rows[$i];

                if (!empty($data[2])) {
                    $userName = $data[1] ?? 'customer' . rand(100000, 999999);
                    $userEmail = strtolower(str_replace(' ', '', $userName)) . '@mail.com';
                    $clientCode = rand(100000, 999999);
                    $clientPhone = preg_replace('/\D+/', '', (strlen($data[5] ?? '') > 5 ? $data[5] : ($data[6] ?? '')));


                    // Avoid duplicate User by email
                    $user = User::firstOrCreate(
                        ['email' => $userEmail],
                        [
                            'name' => $userName,
                            'password' => bcrypt('password123'),
                            'role' => 4,
                            'verified' => 1
                        ]
                    );

                    // Avoid duplicate Client by user_id (or use another unique key if better)
                    $client = Client::firstOrCreate(
                        ['user_id' => $user->id],
                        [
                            'code' => $clientCode,
                            'name' => $userName,
                            'email' => $userEmail,
                            'address' => preg_replace('/[0-9\+\s]+/', '', $clientPhone)
                        ]
                    );

                    $shipment = Shipment::updateOrCreate(
                        [
                            'consignment_id' => $consignment->id,
                            'code' => $data[0], // job num
                        ],
                        [
                            'client_id' => $client->id,
                            'branch_id' => 1,
                            'type' => 1,
                            'status_id' => 1,
                            'client_status' => 1,
                            'from_country_id' => 1,
                            'from_state_id' => 1,
                            'to_country_id' => 1,
                            'to_state_id' => 1,
                            'shipping_cost' => (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $data[8])),
                            'return_cost' => 0,
                            'amount_to_be_collected' => (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $data[8])),
                            'shipping_date' => Carbon::now(),
                            'total_weight' => (float)($data[4] ?? 0),
                            'client_address' => $userName.''.$clientPhone,
                            'client_phone' => $clientPhone,
                        ]
                    );

                    PackageShipment::updateOrCreate(
                        [
                            'shipment_id' => $shipment->id,
                            'package_id' => 1,
                        ],
                        [
                            'description' => $data[2].' '.$data[3],
                            'qty' => is_string($data[3]) ? ($data[4] ?? 1) : 1,
                            'weight' => (strpos($data[5], '.') || is_numeric($data[5]) !== false) ? $data[5] : ($data[4] ?? 0),
                            'length' => 1,
                            'width' => 1,
                            'height' => 1,
                        ]
                    );
                }

            }
            // dd('loopCreateShipment completed successfully');
            DB::commit();
            return true;
        } catch (\Throwable $th) {
            dd('Loop Error'.$th->getMessage());
            return false;
        }
    }

    public function loopCreateShipmentII($headerRow, $rows, $consignment){
        try {
            for ($i = 7 + 1; $i < count($rows) - 1; $i++) {
                $data = $rows[$i];

                // dd($data);
                if (!empty($data[2])) {
                    // Extract user and client-related information
                    $userName = $data[3] ?? 'customer' . rand(100000, 999999); // Assuming Mark column represents user/client name
                    $userEmail = strtolower(str_replace(' ', '', $userName)) . '@mail.com'; // Generate a placeholder email
                    $clientCode = rand(100000, 999999); // Random client code
                    $clientContact = $data[8]; // Assuming consignee_info column represents address
                    // Create or find User
                    $user = User::where('email', $userEmail)->first();
                    if (!$user) {
                        $user = new User();
                        $user->email = $userEmail;
                        $user->name = $userName;
                        $user->password = bcrypt('password123');
                        $user->role = 4;
                        $user->verified = 1;
                        $user->save();
                    }
                    $client = Client::where('user_id', $user->id)->first();
                    if (!$client) {
                        $client = new Client();
                        $client->user_id = $user->id;
                        $client->code = $clientCode;
                        $client->name = $userName;
                        $client->email = $userEmail;
                        $client->save();
                    }

                    $shipmentPayload = [
                        'client_id' => $client->id,
                        'branch_id' => 1,
                        'type' => 1,
                        'status_id' => 1,
                        'client_status' => 1,
                        'from_country_id' => 1,
                        'from_state_id' => 1,
                        'to_country_id' => 1,
                        'to_state_id' => 1,
                        'shipping_date' => Carbon::now(),
                        'total_weight' => (float)($data[6] ?? 0),
                        'client_phone' => preg_replace('/\D+/', '', $clientContact),
                    ];

                    if (!empty($data[10])) {
                        $shipmentPayload = array_merge(
                            $shipmentPayload,
                            [
                                'shipping_cost' => (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $data[10])),
                                'return_cost' => 0,
                                'amount_to_be_collected' => (float)preg_replace('/\D+/', '', $data[10]),
                            ]
                        );
                    }

                    // Create or update Shipment
                    $shipment = Shipment::updateOrCreate(
                        [
                            'consignment_id' => $consignment->id,
                            'code' => $data[2],
                        ],
                        $shipmentPayload
                    );


                    $package['description'] = $data[4].'. Parcel items including: ('.preg_replace('/[0-9\+\s]+/', '', $data[5]) .')';
                    $package['qty'] = $data[6];
                    $package['weight'] = $data[7];
                    $package['length'] = 1;
                    $package['width'] = 1;
                    $package['height'] = 1;
                    $total_weight = $package['weight'];

                    $package_shipment = new PackageShipment();
                    $package_shipment->fill($package);
                    $package_shipment->shipment_id = $shipment->id;
                    DB::commit();
                }
            }
        } catch (\Throwable $th) {
            dd('Excel Format Error'.$th->getMessage());
        }
    }

    public function loopCreateShipmentSize9($headerRow, $rows, $consignment)
    {
        try {

            for ($i = $headerRow + 1; $i < count($rows) - 1; $i++) {
                $data = $rows[$i];
                // dd($rows);

                if (!empty($data[1])) {
                    $userName = $data[1] ?? 'customer' . rand(100000, 999999);
                    $userEmail = strtolower(str_replace(' ', '', $userName)) . '@mail.com';
                    $clientCode = rand(100000, 999999);
                    $clientPhone = preg_replace('/\D+/', '', (strlen($data[6] ?? '')));


                    // Avoid duplicate User by email
                    $user = User::firstOrCreate(
                        ['email' => $userEmail],
                        [
                            'name' => $userName,
                            'password' => bcrypt('password123'),
                            'role' => 4,
                            'verified' => 1
                        ]
                    );

                    // Avoid duplicate Client by user_id (or use another unique key if better)
                    $client = Client::firstOrCreate(
                        ['user_id' => $user->id],
                        [
                            'code' => $clientCode,
                            'name' => $userName,
                            'email' => $userEmail,
                            'address' => preg_replace('/[0-9\+\s]+/', '', $clientPhone)
                        ]
                    );

                    $shipment = Shipment::updateOrCreate(
                        [
                            'consignment_id' => $consignment->id,
                            'code' => $data[0], // job num
                        ],
                        [
                            'client_id' => $client->id,
                            'branch_id' => 1,
                            'type' => 1,
                            'status_id' => 1,
                            'client_status' => 1,
                            'from_country_id' => 1,
                            'from_state_id' => 1,
                            'to_country_id' => 1,
                            'to_state_id' => 1,
                            'shipping_cost' => (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $data[8])),
                            'return_cost' => 0,
                            'amount_to_be_collected' => (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $data[8])),
                            'shipping_date' => Carbon::now(),
                            'total_weight' => (float)($data[5] ?? 0),
                            'client_address' => $userName.''.$clientPhone,
                            'client_phone' => $clientPhone,
                        ]
                    );

                    PackageShipment::updateOrCreate(
                        [
                            'shipment_id' => $shipment->id,
                            'package_id' => 1,
                        ],
                        [
                            'description' => $data[2].' '.$data[3],
                            'qty' => is_string($data[4]) ? ($data[4] ?? 1) : 1,
                            'weight' => (strpos($data[5], '.') || is_numeric($data[5]) !== false) ? $data[5] : ($data[5] ?? 0),
                            'length' => 1,
                            'width' => 1,
                            'height' => 1,
                        ]
                    );
                }else{
                    dd($data);
                }

            }
            // dd('loopCreateShipment completed successfully');
            DB::commit();
            return true;
        } catch (\Throwable $th) {
            dd('Loop Error'.$th->getMessage());
            return false;
        }
    }



    public function export(Request $request)
    {
        $this->authorizeConsignmentMutation();

        return Excel::download(
            new ShipmentExport($request),
            'shipments_export_' . now()->format('Ymd_His') . '.xlsx'
        );
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorizeConsignmentMutation();

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.consignments.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreConsignmentRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authorizeConsignmentMutation();
        try {

            $request->validate([
                'consignment_code' => 'required|unique:consignments',
                'name' => 'required|string|max:255',
                'source' => 'required|string',
                'destination' => 'required|string',
                'status' => 'required|in:pending,in_transit,delivered,canceled',
                'consignee' => 'nullable|string|max:255',
                'job_num' => 'nullable|string|max:255',
                'mawb_num' => 'nullable|string|max:255',
            ]);

            Consignment::create($request->all());
            return redirect()->route('consignment.index')->with('success', 'Consignment created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while creating the consignment: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Consignment  $consignment
     * @return \Illuminate\Http\Response
     */
    public function show(Consignment $cons, $id)
    {

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        $consignment = $cons::where('id',$id)->with([
            'shipments.client',
            'shipments.consignment' // optional, only if needed
        ])->first();
        return view('cargo::' . $adminTheme . '.pages.consignments.show', compact('consignment'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Consignment  $consignment
     * @return \Illuminate\Http\Response
     */
    public function edit(Consignment $cons, $id)
    {
        $this->authorizeConsignmentMutation();
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        $consignment = $cons::where('id', $id)->first();
        return view('cargo::' . $adminTheme . '.pages.consignments.edit', compact('consignment'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateConsignmentRequest  $request
     * @param  \App\Models\Consignment  $consignment
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Consignment $consignment)
    {
        $this->authorizeConsignmentMutation();
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'source' => 'required|string',
                'destination' => 'required|string',
                'status' => 'required|string',
                'consignment_code' => 'nullable|string',
                'consignee' => 'nullable|string',
                'mawb_num' => 'nullable|string',
                'eta' => 'nullable|date',
                'cargo_date' => 'nullable|date',
                'job_num' => 'nullable|string|nullable',
                'cargo_type' => 'nullable|string',
                'eta_dar' => 'nullable|date',
                'eta_lun' => 'nullable|date',
            ]);
            $consignment->update($validated);
            return redirect()->back()->with('success', 'Consignment updated successfully.');
        } catch (\Throwable $th) {
            report($th);
            return redirect()->back()->withErrors(['error' => 'Failed to update consignment.']);
        }
    }

    public function editTracker($id)
    {
        $this->authorizeConsignmentMutation();
        $consignment = Consignment::findOrFail($id);
        return response()->json($consignment);
    }

    public function updateTracker(Request $request, $id)
    {
        $this->authorizeConsignmentMutation();

        // dd($request);
        try {
            $consignment = Consignment::findOrFail($id);

            // Validate the request
            $request->validate([
                'status' => "required|integer|min:1",
            ]);

            $currentStage = $consignment->getCurrentStage();
            $targetStage = $request->status;
            $now = Carbon::now();

            // Get the tracking stage to determine the status
            $trackingStage = DB::table('tracking_stages')
                ->where('id', $targetStage)
                ->first();

            if (!$trackingStage) {
                throw new \Exception('Invalid tracking stage');
            }

            // If moving to an earlier stage, delete tracking history entries for stages being undone
            if ($targetStage < $currentStage) {
                $consignment->trackingHistory()
                    ->where('stage_id', '>', $targetStage)
                    ->delete();
            }
            // If skipping ahead, create entries for intermediate stages
            else if ($targetStage > $currentStage + 1) {
                // Create entries for all intermediate stages
                for ($stage = $currentStage + 1; $stage < $targetStage; $stage++) {
                    $consignment->updateTrackingStage($stage, [
                        'completed_at' => $now,
                        'notes' => 'Stage completed automatically during stage skip',
                        'location' => 'System'
                    ]);
                }
            }

            // Update the target stage
            if (!$consignment->updateTrackingStage($targetStage, [
                'completed_at' => $now,
                'notes' => 'Stage updated via tracker',
                'location' => 'System'
            ])) {
                throw new \Exception('Failed to update tracking stage');
            }

            // Update the consignment status based on the tracking stage status
            $consignment->status = strtolower($trackingStage->status);
            $consignment->save();

            return redirect()->back()->with('success', 'Tracker updated successfully.');
        } catch (\Exception $e) {
            // dd($e);
            return redirect()->back()->with('error', 'Failed to update shipment tracker: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Consignment  $consignment
     * @return \Illuminate\Http\Response
     */
    public function destroy(Consignment $consignment, $id)
    {
        $this->authorizeConsignmentMutation();
        try {
            $c = $consignment->where('id', $id)->first();
            if (!$c) {
                return redirect()->back()->with('error', 'Consignment not found.');
            }

            // Delete all shipments with this consignment_id
            $shipments = Shipment::where('consignment_id', $c->id)->get();

            foreach ($shipments as $shipment) {
                // Delete related PackageShipment records
                PackageShipment::where('shipment_id', $shipment->id)->delete();
                Transxn::where('shipment_id', $shipment->id)->delete();

                // Delete the shipment itself
                $shipment->delete();
            }

            // Delete the consignment
            $c->delete();

            $consignments = Consignment::get();

            return redirect()->route('consignment.index', compact('consignments'))
                ->with('success', 'Consignment and related data deleted successfully.');
        } catch (\Throwable $th) {
            return redirect()->route('consignment.index', compact('consignments'))
                ->with('error', $th->getMessage());
        }
    }
    public function bulkDelete(Request $request)
    {
        $this->authorizeConsignmentMutation();
        try {
            $consignments = Consignment::whereIn('id', $request->ids)->get();

            foreach ($consignments as $consignment) {
                // Get all related shipments
                $shipments = Shipment::where('consignment_id', $consignment->id)->get();

                foreach ($shipments as $shipment) {
                    // Delete related PackageShipment records
                    PackageShipment::where('shipment_id', $shipment->id)->delete();
                    Transxn::where('shipment_id', $shipment->id)->delete();

                    // Delete the shipment
                    $shipment->delete();
                }

                // Delete the consignment itself
                $consignment->delete();
            }

            return response()->json(['status' => 'success']);
        } catch (\Throwable $th) {
            return response()->json(['status' => 'failed', 'msg' => $th->getMessage()]);
        }
    }

    public function getCurrentStage(Request $request)
    {
        $consignmentId = $request->input('consignment_id');
        if (!$consignmentId) {
            return response()->json(['error' => 'consignment_id is required'], 400);
        }

        $history = \App\Models\ConsignmentTrackingHistory::where('consignment_id', $consignmentId)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();


        if ($history) {
            $stage = \App\Models\TrackingStage::find($history->stage_id);
            return response()->json([
                'stage_name' => $stage ? $stage->name : null,
                'stage_description' => $stage ? $stage->description : null,
                'status' => $history->status,
                'notes' => $history->notes,
                'location' => $history->location,
                'completed_at' => $history->completed_at,
            ]);
        } else {
            return response()->json([
                'stage_name' => null,
                'stage_description' => null,
                'status' => null,
                'notes' => null,
                'location' => null,
                'completed_at' => null,
            ]);
        }
    }

}
