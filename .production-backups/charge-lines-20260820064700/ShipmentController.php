<?php

namespace Modules\Cargo\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

use Modules\Cargo\Http\DataTables\ShipmentsDataTable;
use Modules\Cargo\Http\Requests\ShipmentRequest;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\ShipmentSetting;
use Modules\Cargo\Entities\ClientPackage;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Package;
use Modules\Cargo\Entities\Cost;
use Modules\Cargo\Http\Helpers\ShipmentPRNG;
use Modules\Cargo\Http\Helpers\MissionPRNG;
use Modules\Cargo\Entities\PackageShipment;
use Modules\Cargo\Http\Helpers\ShipmentActionHelper;
use Modules\Cargo\Http\Helpers\StatusManagerHelper;
use Modules\Cargo\Http\Helpers\TransactionHelper;
use Modules\Cargo\Entities\Mission;
use Modules\Cargo\Entities\ShipmentMission;
use Modules\Cargo\Entities\ShipmentReason;
use Modules\Cargo\Entities\Country;
use Modules\Cargo\Entities\State;
use Modules\Cargo\Entities\Area;
use Modules\Cargo\Entities\ClientAddress;
use Modules\Cargo\Entities\DeliveryTime;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\BusinessSetting;
use Modules\Cargo\Utility\CSVUtility;
use DB;
use Modules\Cargo\Http\Helpers\UserRegistrationHelper;
use app\Http\Helpers\ApiHelper;
use App\Models\Consignment;
use App\Models\CurrencyExchangeRate;
use App\Models\NwcReceipt;
use App\Models\ShipmentPaymentReceipt;
use App\Models\RefundRequest;
use App\Models\User;
use App\Models\Transxn;
use App\Models\GeneralSettings;
use App\Traits\Tracker;
use App\Traits\HandlesCurrencyExchange;
use App\Services\RefundService;
use Modules\Cargo\Events\AddShipment;
use Modules\Cargo\Events\CreateMission;
use Modules\Cargo\Events\ShipmentAction;
use Modules\Cargo\Events\UpdateMission;
use Modules\Cargo\Events\UpdateShipment;
use Modules\Acl\Repositories\AclRepository;
use Modules\Cargo\Http\Controllers\ClientController;
use Modules\Cargo\Http\Requests\RegisterRequest;
use Carbon\Carbon;
use Auth;
use Illuminate\Support\Facades\DB as FDB;
use Modules\Cargo\Entities\Payment;
use Modules\Cargo\Entities\ShipmentLog;
use App\Services\AuditLogService;

class ShipmentController extends Controller
{
    use Tracker, HandlesCurrencyExchange;
    private $aclRepo;

    public function __construct(AclRepository $aclRepository)
    {
        $this->aclRepo = $aclRepository;
        // check on permissions
        $this->middleware('user_role:1|0|3|4')->only('index', 'shipmentsReport', 'create');
        $this->middleware('user_role:4')->only('ShipmentApis');
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(ShipmentsDataTable $dataTable, $status = 'all', $type = null)
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.shipments')
            ]
        ]);
        $actions = new ShipmentActionHelper();
        if ($status == 'all') {
            $actions = $actions->get('all');
        } else {
            $actions = $actions->get($status, $type);
        }

        $data_with = ['actions' => $actions, 'status' => $status];
        $share_data = array_merge(get_class_vars(ShipmentsDataTable::class), $data_with);

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return $dataTable->render('cargo::' . $adminTheme . '.pages.shipments.index', $share_data);
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.shipments'),
                'path' => fr_route('shipments.index')
            ],
            [
                'name' => __('cargo::view.add_shipment'),
            ],
        ]);

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.shipments.create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $order_id_validation = 'nullable|unique:shipments,order_id';
        $request->validate([
            'Shipment.type'            => 'required',
            'Shipment.branch_id'       => 'required',
            'Shipment.consignment_id'  => 'nullable',
            'Shipment.shipping_date'   => 'nullable',
            'Shipment.collection_time' => 'nullable',
            'Shipment.client_id'       => 'required',
            'Shipment.client_phone'    => 'required|min:5',
            'Shipment.follow_up_country_code'    => 'nullable',
            'Shipment.client_address'  => 'required',
            'Shipment.reciver_name'    => 'required|string|min:3|max:50',
            'Shipment.reciver_phone'   => 'required|min:5',
            'Shipment.country_code'    => 'nullable',
            'Shipment.reciver_address' => 'required|string|min:8',
            'Shipment.from_country_id' => 'required',
            'Shipment.to_country_id'   => 'required',
            'Shipment.from_state_id'   => 'required',
            'Shipment.to_state_id'     => 'required',
            'Shipment.from_area_id'    => 'required',
            'Shipment.to_area_id'      => 'required',
            'Shipment.payment_type'    => 'required',
            // 'Shipment.payment_method_id' => 'required',
            'Shipment.order_id'          => $order_id_validation,
            'Shipment.attachments_before_shipping' => 'nullable',
            'Shipment.amount_to_be_collected'      => 'required',
            'Shipment.delivery_time'    => 'nullable',
            'Shipment.total_weight'     => 'required',
        ]);


        // dd($request);
        // Calculating "delivery time"  for The shipment is automatic
        // $shippingDate = $request->Shipment['collection_time'];
        // $collectionTime = $request->Shipment['shipping_date'];

        // $shippingDate = date("H:i:s", strtotime($shippingDate));
        // $collectionTime = $collectionTime;
        // $shippingDateTime = Carbon::parse($shippingDate . ' ' . $collectionTime);
        // $currentDateTime = Carbon::now();

        // $deliveryTime = $currentDateTime->diffForHumans($shippingDateTime, true);

        // $request->merge(['Shipment' => array_merge($request->Shipment, ['delivery_time' => $deliveryTime])]);

        try {
            // DB::beginTransaction();
            $model = $this->storeShipment($request);
            $model->addFromMediaLibraryRequest($request->image)->toMediaCollection('attachments');
            event(new AddShipment($model));
            // DB::commit();
            return redirect()->route('shipments.show', $model->id)->with(['message_alert' => __('cargo::messages.created')]);
        } catch (\Exception $e) {
            // DB::rollback();
            dd($e);
            print_r($e->getMessage());
            exit;

            flash(translate("Error"))->error();
            return back();
        }
    }

    private function storeShipment($request, $token = null)
    {
        // dd((int)$request->input('consignment_id'));
        $model = new Shipment();
        $model->fill($request->Shipment);
        $model->code = -1;
        $model->status_id = Shipment::SAVED_STATUS;

        // // Update the shipments table
        // FDB::statement("
        //     UPDATE shipments
        //     SET consignment_id = ?
        //     WHERE id = ?
        // ", [$request->input('consignment_id'), (int)$request->input('consignment_id')]);

        $date = date_create();
        $today = date("Y-m-d");

        if (isset($token)) {

            $user = User::where('remember_token', $token)->first();
            $userClient = Client::where('user_id', $user->id)->first();

            if (isset($user)) {
                $model->client_id = $userClient->id;

                // Validation
                if (!isset($request->Shipment['type']) || !isset($request->Shipment['branch_id']) || !isset($request->Shipment['shipping_date']) || !isset($request->Shipment['client_address']) || !isset($request->Shipment['reciver_name']) || !isset($request->Shipment['reciver_phone']) || !isset($request->Shipment['reciver_address']) || !isset($request->Shipment['from_country_id']) || !isset($request->Shipment['to_country_id']) || !isset($request->Shipment['from_state_id']) || !isset($request->Shipment['to_state_id']) || !isset($request->Shipment['from_area_id']) || !isset($request->Shipment['to_area_id']) || !isset($request->Shipment['payment_method_id']) || !isset($request->Shipment['payment_type']) || !isset($request->Package)) {
                    $message = 'Please make sure to add all required fields';
                    return $message;
                } else {
                    if ($request->Shipment['type'] != Shipment::POSTPAID && $request->Shipment['type'] != Shipment::PREPAID) {
                        return 'Invalid Type';
                    }

                    if (!Branch::find($request->Shipment['branch_id'])) {
                        return 'Invalid Branch';
                    }

                    if (!ClientAddress::where('client_id', $userClient->id)->where('id', $request->Shipment['client_address'])->first()) {
                        return 'Invalid Client Address';
                    }

                    if (!Country::where('covered', 1)->where('id', $request->Shipment['from_country_id'])->first() || !Country::where('covered', 1)->where('id', $request->Shipment['to_country_id'])->first()) {
                        return 'Invalid Country';
                    }

                    if (!State::where('covered', 1)->where('id', $request->Shipment['from_state_id'])->first() || !State::where('covered', 1)->where('id', $request->Shipment['to_state_id'])->first()) {
                        return 'Invalid State';
                    }

                    if (!Area::where('state_id', $request->Shipment['from_state_id'])->where('id', $request->Shipment['from_area_id'])->first() || !Area::where('state_id', $request->Shipment['to_state_id'])->where('id', $request->Shipment['to_area_id'])->first()) {
                        return 'Invalid Area';
                    }

                    if (isset($request->Shipment['payment_method_id'])) {
                        $paymentSettings = resolve(\Modules\Payments\Entities\PaymentSetting::class)->toArray();
                        if (!isset($paymentSettings[$request->Shipment['payment_method_id']])) {
                            return 'Invalid Payment Method Id';
                        }
                    }

                    if ($request->Shipment['payment_type'] != Shipment::POSTPAID && $request->Shipment['payment_type'] != Shipment::PREPAID) {
                        return 'Invalid Payment Type';
                    }

                    // if(isset($request->Shipment['delivery_time'])){
                    //     $delivery_time = DeliveryTime::where('id', $request->Shipment['delivery_time'] )->first();
                    //     if(!$delivery_time){
                    //         return 'Invalid Delivery Time';
                    //     }
                    // }

                }

                if (!isset($request->Shipment['client_phone'])) {
                    $model->client_phone = $userClient->responsible_mobile;
                }

                if (!isset($request->Shipment['amount_to_be_collected'])) {
                    $model->amount_to_be_collected = 0;
                }
            } else {
                return response()->json(['message' => 'invalid or Expired Api Key']);
            }
        }

        if (!$model->save()) {
            return response()->json(['message' => new \Exception()]);
        }

        if (ShipmentSetting::getVal('def_shipment_code_type') == 'random') {
            $barcode = ShipmentPRNG::get();
        } else {
            $code = '';
            for ($n = 0; $n < ShipmentSetting::getVal('shipment_code_count'); $n++) {
                $code .= '0';
            }
            $code       =   substr($code, 0, -strlen($model->id));
            $barcode    =   $code . $model->id;
        }
        $model->barcode = $barcode;
        $model->code = ShipmentSetting::getVal('shipment_prefix') . $barcode;

        if (auth()->user() && auth()->user()->role == 4) { // IF IN AUTH USER == CLIENT
            $client = Client::where('user_id', auth()->user()->id)->first();
            $model->client_id = $client->id;
        }

        if (!$model->save()) {
            return response()->json(['message' => new \Exception()]);
        }

        $costs = $this->applyShipmentCost($model, $request->Package);

        $model->fill($costs);
        if (!$model->save()) {
            return response()->json(['message' => new \Exception()]);
        }

        $counter = 0;
        if (isset($request->Package)) {
            if (!empty($request->Package)) {

                if (isset($request->Package[$counter]['package_id'])) {

                    if (isset($token)) {
                        $total_weight = 0;
                    }

                    foreach ($request->Package as $package) {
                        if (isset($token)) {
                            if (!Package::find($package['package_id'])) {
                                return 'Package invalid';
                            }

                            if (!isset($package['qty'])) {
                                $package['qty'] = 1;
                            }

                            if (!isset($package['weight'])) {
                                $package['weight'] = 1;
                            }
                            if (!isset($package['length'])) {
                                $package['length'] = 1;
                            }
                            if (!isset($package->width)) {
                                $package['width'] = 1;
                            }
                            if (!isset($package['height'])) {
                                $package['height'] = 1;
                            }

                            $total_weight = $total_weight + $package['weight'];
                        }
                        $package_shipment = new PackageShipment();
                        $package_shipment->fill($package);
                        $package_shipment->shipment_id = $model->id;
                        if (!$package_shipment->save()) {
                            throw new \Exception();
                        }
                    }

                    if (isset($token)) {
                        $model->total_weight = $total_weight;
                        if (!$model->save()) {
                            return response()->json(['message' => new \Exception()]);
                        }
                    }
                }
            }
        }

        if (isset($token)) {
            $message = 'Shipment added successfully';
            return $message;
        } else {
            return $model;
        }
    }

    public function storeAPI(Request $request)
    {
        try {
            $apihelper = new ApiHelper();
            $user = $apihelper->checkUser($request);

            if ($user) {
                DB::beginTransaction();
                $message = $this->storeShipment($request, $request->header('token'));
                DB::commit();
                return response()->json(['message' => $message]);
            } else {
                return response()->json(['message' => 'Not Authorized']);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
    }
    public function getShipmentsAPI(Request $request)
    {
        try {
            $apihelper = new ApiHelper();
            $user = $apihelper->checkUser($request);

            if ($user) {
                $userClient = Client::where('user_id', $user->id)->first();
                $shipments = new Shipment();

                $shipments = $shipments->where('client_id', $userClient->id);
                if (isset($request->code) && !empty($request->code)) {
                    $shipments = $shipments->where('code', $request->code);
                }
                if (isset($request->type) && !empty($request->type)) {
                    $shipments = $shipments->where('type', $request->type);
                }
                $shipments = $shipments->with(['pay', 'from_address'])->orderBy('client_id')->orderBy('id', 'DESC')->paginate(20);
                return response()->json($shipments);
            } else {
                return response()->json(['message' => 'Not Authorized']);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return $e->getMessage();
        }
    }

    public function show($id)
    {
        $shipment = Shipment::with(['nwcReceipt.user', 'receipt', 'paymentReceipts.user'])->find($id);
        if (!$shipment) {
            abort(404, 'Shipment not found');
        }
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.shipments'),
                'path' => fr_route('shipments.index')
            ],
            [
                'name' => __('cargo::view.shipment') . ' | ' . $shipment->code,
            ],
        ]);
        $auditLogService = app(AuditLogService::class);
        $auditLogs = $auditLogService->getLogsFor($shipment);
        $pendingRefundRequest = RefundRequest::where('shipment_id', $shipment->id)
            ->where('status', RefundRequest::STATUS_PENDING)
            ->latest()
            ->first();
        $remainingRefundAmount = null;
        if ($shipment->receipt) {
            $refundedAmount = (float) ($shipment->receipt->refunded_amount ?? 0);
            $remainingRefundAmount = max(((float) $shipment->receipt->total) - $refundedAmount, 0.0);
        }
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.shipments.show', compact('shipment', 'auditLogs', 'pendingRefundRequest', 'remainingRefundAmount'));
    }

    public function edit($id)
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.shipments'),
                'path' => fr_route('shipments.index')
            ],
            [
                'name' => __('cargo::view.edit_shipment'),
            ],
        ]);
        $item = Shipment::findOrFail($id);
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.shipments.edit')->with(['model' => $item]);
    }

    public function update(Request $request, $id)
    {
        // $request->validate([
        //     'Shipment.type'            => 'required',
        //     'Shipment.branch_id'       => 'required',
        //     'Shipment.shipping_date'   => 'nullable',
        //     'Shipment.collection_time' => 'nullable',
        //     'Shipment.client_id'       => 'required',
        //     'Shipment.client_phone'    => 'required|min:5',
        //     'Shipment.country_code'    => 'nullable',
        //     'Shipment.client_address'  => 'required',
        //     'Shipment.reciver_name'    => 'nullable|string|min:3|max:50', //CHANGED
        //     'Shipment.reciver_phone'   => 'nullable|min:5',
        //     'Shipment.follow_up_country_code'   => 'nullable',
        //     'Shipment.reciver_address' => 'nullable|string|min:8',
        //     'Shipment.from_country_id' => 'nullable',
        //     'Shipment.to_country_id'   => 'nullable',
        //     'Shipment.from_state_id'   => 'nullable',
        //     'Shipment.to_state_id'     => 'nullable',
        //     'Shipment.from_area_id'    => 'nullable',
        //     'Shipment.to_area_id'      => 'nullable',
        //     'Shipment.payment_type'    => 'nullable',
        //     'Shipment.payment_method_id' => 'nullable',
        //     'Shipment.order_id'          => 'nullable',
        //     'Shipment.attachments_before_shipping' => 'nullable',
        //     'Shipment.amount_to_be_collected'      => 'required',
        //     'Shipment.delivery_time'    => 'nullable',
        //     'Shipment.total_weight'     => 'required',
        //     'Shipment.tax'           => 'nullable',
        //     'Shipment.insurance'     => 'nullable',
        //     'Shipment.shipping_cost' => 'nullable',
        //     'Shipment.return_cost'   => 'nullable',
        // ]);

        // dd('here');

        // dd($request);
        try {
            DB::beginTransaction();
            $model = Shipment::find($id);
            $model->fill($request->Shipment);

            $costs = $this->applyShipmentCost($model, $_POST['Package']);
            $model->fill($costs);

            if (!$model->save()) {
                throw new \Exception();
            }

            foreach (PackageShipment::where('shipment_id', $model->id)->get() as $pack) {
                $pack->delete();
            }
            $counter = 0;

            if (isset($_POST['Package'])) {
                if (!empty($_POST['Package'])) {
                    if (isset($_POST['Package'][$counter]['package_id'])) {

                        foreach ($_POST['Package'] as $package) {
                            $package_shipment = new PackageShipment();
                            $package_shipment->fill($package);
                            $package_shipment->shipment_id = $model->id;
                            if (!$package_shipment->save()) {
                                throw new \Exception();
                            }
                        }
                    }
                }
            }

            // event(new UpdateShipment($model));
            DB::commit();

            // $model->syncFromMediaLibraryRequest($request->image)->toMediaCollection('attachments');
            return redirect()->route('shipments.show', $model->id)->with(['message_alert' => __('cargo::messages.saved')]);;
        } catch (\Exception $e) {
            // dd($e);
            DB::rollback();
            print_r($e->getMessage());
            exit;
            return back();
        }
    }

    public function import(Request $request)
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.shipments'),
                'path' => fr_route('shipments.index')
            ],
            [
                'name' => __('cargo::view.import_shipments'),
            ],
        ]);
        $shipment = new Shipment;
        $columns = $shipment->getTableColumns();
        $countries = Country::where('covered', 1)->get();
        $states    = State::where('covered', 1)->get();
        $areas     = Area::get();
        $packages  = Package::all();
        $branches  = Branch::where('is_archived', 0)->get();
        $paymentsGateway = BusinessSetting::where("key", "payment_gateway")->where("value", "1")->get();
        $deliveryTimes   = DeliveryTime::all();
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.shipments.import')
            ->with(['columns' => $columns, 'countries' => $countries, 'states' => $states, 'areas' => $areas, 'packages' => $packages, 'branches' => $branches, 'deliveryTimes' => $deliveryTimes]);
    }

    public function parseImport(Request $request)
    {

        $request->validate([
            'shipments_file' => 'required|mimes:csv,txt',
            "columns"        => "required|array|min:16",
        ]);

        $path = $request->file('shipments_file')->getRealPath();
        $data = [];
        $csv = new CSVUtility("testfile");
        $csv->readCSV($path);
        $totalRows = $csv->totalRows();

        for ($row = 0; $row < $totalRows; $row++) {

            $value = $csv->getRow($row);
            array_push($data, $value);
        }



        if (count($data[0]) != count($request->columns)) {
            return back()->with(['error_message_alert' => __('cargo::view.this_file_you_are_trying_to_import_is_not_the_file_that_you_should_upload')]);
        }

        if (!in_array('type', $request->columns) || !in_array('client_phone', $request->columns) || !in_array('client_address', $request->columns) || !in_array('branch_id', $request->columns) || !in_array('shipping_date', $request->columns) || !in_array('reciver_name', $request->columns) || !in_array('reciver_phone', $request->columns) || !in_array('reciver_address', $request->columns) || !in_array('from_country_id', $request->columns) || !in_array('to_country_id', $request->columns) || !in_array('from_state_id', $request->columns) || !in_array('to_state_id', $request->columns) || !in_array('to_area_id', $request->columns) || !in_array('from_area_id', $request->columns) || !in_array('payment_type', $request->columns) || !in_array('payment_method_id', $request->columns) || !in_array('package_id', $request->columns)) {
            return back()->with(['error_message_alert' => __('cargo::view.make_sure_all_required_parameters_in_CSV')]);
        }
        if (auth()->user()->can('import-shipments')) {
            if (!in_array('client_id', $request->columns)) {
                return back()->with(['error_message_alert' => __('cargo::view.make_sure_all_required_parameters_in_CSV')]);
            }
        }

        try {
            $user_role = auth()->user()->role;
            $admin  = 1;
            $auth_staff  = 0;
            $auth_branch = 3;
            $auth_client = 4;

            unset($data[0]);

            if ($user_role == $auth_client) {
                $client = Client::where('user_id', auth()->user()->id)->first();
            }

            foreach ($data as $row) {
                for ($i = 0; $i < count($row); $i++) {

                    if ($user_role != $auth_client) {
                        if ($request->columns[$i] == 'client_id') {
                            if (!Client::find($row[$i])) {
                                return back()->with(['error_message_alert' => __('cargo::view.invalid_client')]);
                            }
                            $client = Client::where('id', $row[$i])->first();
                        }
                    }

                    // Validation
                    if ($request->columns[$i] == 'type') {
                        if (intval($row[$i]) != Shipment::POSTPAID && intval($row[$i]) != Shipment::PREPAID) {
                            return back()->with(['error_message_alert' => __('cargo::view.invalid_type')]);
                        }
                    }

                    if ($request->columns[$i] == 'branch_id') {
                        if (!Branch::find($row[$i])) {
                            return back()->with(['error_message_alert' => __('cargo::view.invalid_branch')]);
                        }
                    }

                    if ($request->columns[$i] == 'client_address') {
                        if (!ClientAddress::where('client_id', $client->id)->where('id', $row[$i])->first()) {
                            return back()->with(['error_message_alert' => __('cargo::view.invalid_client_address')]);
                        }
                    }

                    if ($request->columns[$i] == 'from_country_id' || $request->columns[$i] == 'to_country_id') {
                        if (!Country::find($row[$i])) {
                            return back()->with(['error_message_alert' => __('cargo::view.invalid_country')]);
                        }
                    }

                    if ($request->columns[$i] == 'from_state_id' || $request->columns[$i] == 'to_state_id') {
                        if (!State::find($row[$i])) {
                            return back()->with(['error_message_alert' => __('cargo::view.invalid_state')]);
                        }
                    }

                    if ($request->columns[$i] == 'from_area_id' || $request->columns[$i] == 'to_area_id') {
                        if (!Area::find($row[$i])) {
                            return back()->with(['error_message_alert' => __('cargo::view.invalid_area')]);
                        }
                    }

                    if ($request->columns[$i] == 'payment_method_id') {
                        $paymentSettings = resolve(\Modules\Payments\Entities\PaymentSetting::class)->toArray();
                        if (!isset($paymentSettings[$row[$i]])) {
                            return back()->with(['error_message_alert' => __('cargo::view.invalid_payment_method')]);
                        }
                    }

                    if ($request->columns[$i] == 'payment_type') {
                        if ($row[$i] != Shipment::POSTPAID && $row[$i] != Shipment::PREPAID) {
                            return back()->with(['error_message_alert' => __('cargo::view.invalid_payment_type')]);
                        }
                    }

                    if ($request->columns[$i] == 'package_id') {
                        if (!Package::find($row[$i])) {
                            return back()->with(['error_message_alert' => __('cargo::view.invalid_package')]);
                        }
                    }
                    // End Validation

                    if ($request->columns[$i] != 'package_id' && $request->columns[$i] != 'description' && $request->columns[$i] != 'height' && $request->columns[$i] != 'width' && $request->columns[$i] != 'length' && $request->columns[$i] != 'weight' && $request->columns[$i] != 'qty') {

                        if ($request->columns[$i] == 'amount_to_be_collected') {

                            if ($row[$i] == "" || $row[$i] == " " || !is_numeric($row[$i])) {
                                $new_shipment[$request->columns[$i]] = 0;
                            } else {
                                $new_shipment[$request->columns[$i]] = $row[$i];
                            }
                        } elseif ($request->columns[$i] == 'client_phone') {
                            if ($row[$i] == "" || $row[$i] == " ") {
                                $new_shipment[$request->columns[$i]] = $client->responsible_mobile ?? $auth_user->phone;
                            } else {
                                $new_shipment[$request->columns[$i]] = $row[$i];
                            }
                        } else {
                            $new_shipment[$request->columns[$i]] = $row[$i];
                        }
                    } else {
                        if ($request->columns[$i] == 'package_id') {
                            $new_package[$request->columns[$i]] = intval($row[$i]);
                        } else {
                            if ($request->columns[$i] != 'description') {
                                if ($row[$i] == "" || $row[$i] == " " || !is_numeric($row[$i])) {
                                    $new_package[$request->columns[$i]] = 1;

                                    if ($request->columns[$i] == 'weight') {
                                        $new_shipment['total_weight'] = 1;
                                    }
                                } else {
                                    $new_package[$request->columns[$i]] = $row[$i];
                                    if ($request->columns[$i] == 'weight') {
                                        $new_shipment['total_weight'] = $row[$i];
                                    }
                                }
                            } else {
                                $new_package[$request->columns[$i]] = $row[$i];
                            }
                        }
                    }

                    if ($request->columns[$i] == 'delivery_time') {
                        if (isset($row[$i]) && !empty($row[$i])) {
                            if (!DeliveryTime::find($row[$i])) {
                                return back()->with(['error_message_alert' => __('cargo::view.invalid_delivery_time')]);
                            }
                        }
                    }
                }
                $request['Shipment'] = $new_shipment;

                $packages[0] = $new_package;
                $request['Package'] = $packages;

                $this->storeShipment($request);
            }

            return back()->with(['message_alert' => __('cargo::messages.imported')]);
        } catch (\Throwable $th) {

            return dd($th);
        }
    }

    public function change(Request $request, $to)
    {
        if (isset($request->ids)) {
            $action = new StatusManagerHelper();
            $response = $action->change_shipment_status($request->ids, $to);
            if ($response['success']) {
                event(new ShipmentAction($to, $request->ids));
                return back()->with(['message_alert' => __('cargo::messages.saved')]);
            }
        } else {
            return back()->with(['message_alert' => __('cargo::messages.select_error')]);
        }
    }

    public function createPickupMission(Request $request, $type)
    {
        try {

            if (!is_array($request->checked_ids)) {
                $request->checked_ids = json_decode($request->checked_ids, true);
            }

            DB::beginTransaction();
            $model = new Mission();
            $model->fill($request['Mission']);
            $model->status_id = Mission::REQUESTED_STATUS;
            $model->type = Mission::PICKUP_TYPE;
            if (!$model->save()) {
                throw new \Exception();
            }

            $code = '';
            for ($n = 0; $n < ShipmentSetting::getVal('mission_code_count'); $n++) {
                $code .= '0';
            }
            $code   =   substr($code, 0, -strlen($model->id));
            $model->code = $code . $model->id;
            $model->code = ShipmentSetting::getVal('mission_prefix') . $code . $model->id;

            if (!$model->save()) {
                throw new \Exception();
            }

            //change shipment status to requested
            $action = new StatusManagerHelper();

            $response = $action->change_shipment_status($request->checked_ids, Shipment::REQUESTED_STATUS, $model->id);

            //Calaculate Amount
            $helper = new TransactionHelper();
            $helper->calculate_mission_amount($model->id);

            foreach ($request->checked_ids as $shipment_id) {
                if ($model->id != null && ShipmentMission::check_if_shipment_is_assigned_to_mission($shipment_id, Mission::PICKUP_TYPE) == 0) {
                    $shipment = Shipment::find($shipment_id);
                    $shipment_mission = new ShipmentMission();
                    $shipment_mission->shipment_id = $shipment->id;
                    $shipment_mission->mission_id = $model->id;
                    if ($shipment_mission->save()) {
                        $shipment->mission_id = $model->id;
                        $shipment->save();
                    }
                }
            }

            event(new ShipmentAction(Shipment::REQUESTED_STATUS, $request->checked_ids));

            event(new CreateMission($model));

            DB::commit();
            if ($request->is('api/*')) {
                return $model;
            } else {
                return back()->with(['message_alert' => __('cargo::messages.created')]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            print_r($e->getMessage());
            exit;

            flash(translate("Error"))->error();
            return back();
        }
    }

    public function createDeliveryMission(Request $request, $type)
    {
        try {
            $request->checked_ids = json_decode($request->checked_ids, true);
            DB::beginTransaction();
            $model = new Mission();
            // $model->fill($request['Mission']);
            $model->code = -1;
            $model->status_id = Mission::REQUESTED_STATUS;
            $model->type = Mission::DELIVERY_TYPE;
            $model->otp  = MissionPRNG::get();
            // if(ShipmentSetting::getVal('def_shipment_conf_type')=='otp'){
            //     $model->otp = MissionPRNG::get();
            // }
            if (!$model->save()) {
                throw new \Exception();
            }
            $code = '';
            for ($n = 0; $n < ShipmentSetting::getVal('mission_code_count'); $n++) {
                $code .= '0';
            }
            $code   =   substr($code, 0, -strlen($model->id));
            $model->code = ShipmentSetting::getVal('mission_prefix') . $code . $model->id;
            if (!$model->save()) {
                throw new \Exception();
            }
            foreach ($request->checked_ids as $shipment_id) {


                if ($model->id != null && ShipmentMission::check_if_shipment_is_assigned_to_mission($shipment_id, Mission::DELIVERY_TYPE) == 0) {
                    $shipment = Shipment::find($shipment_id);
                    $shipment_mission = new ShipmentMission();
                    $shipment_mission->shipment_id = $shipment->id;
                    $shipment_mission->mission_id = $model->id;
                    if ($shipment_mission->save()) {
                        $shipment->mission_id = $model->id;
                        $shipment->save();
                    }
                }
            }
            //Calaculate Amount
            $helper = new TransactionHelper();
            $helper->calculate_mission_amount($model->id);

            event(new CreateMission($model));
            DB::commit();

            if ($request->is('api/*')) {
                return $model;
            } else {
                return back()->with(['message_alert' => __('cargo::messages.created')]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            print_r($e->getMessage());
            exit;

            flash(translate("Error"))->error();
            return back();
        }
    }

    public function createTransferMission(Request $request, $type)
    {
        try {
            $request->checked_ids = json_decode($request->checked_ids, true);
            DB::beginTransaction();
            $model = new Mission();
            $model->fill($request['Mission']);
            $model->code = -1;
            $model->status_id = Mission::REQUESTED_STATUS;
            $model->type = Mission::TRANSFER_TYPE;
            if (!$model->save()) {
                throw new \Exception();
            }
            $code = '';
            for ($n = 0; $n < ShipmentSetting::getVal('mission_code_count'); $n++) {
                $code .= '0';
            }
            $code   =   substr($code, 0, -strlen($model->id));
            $model->code = ShipmentSetting::getVal('mission_prefix') . $code . $model->id;
            if (!$model->save()) {
                throw new \Exception();
            }
            foreach ($request->checked_ids as $shipment_id) {
                // if ($model->id != null && ShipmentMission::check_if_shipment_is_assigned_to_mission($shipment_id, Mission::TRANSFER_TYPE) == 0) {
                $shipment = Shipment::find($shipment_id);
                $shipment_mission = new ShipmentMission();
                $shipment_mission->shipment_id = $shipment->id;
                $shipment_mission->mission_id = $model->id;
                if ($shipment_mission->save()) {
                    $shipment->mission_id = $model->id;
                    $shipment->save();
                }
                // }
            }

            //Calaculate Amount
            $helper = new TransactionHelper();
            $helper->calculate_mission_amount($model->id);


            event(new CreateMission($model));
            DB::commit();

            if ($request->is('api/*')) {
                return $model;
            } else {
                return back()->with(['message_alert' => __('cargo::messages.created')]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            print_r($e->getMessage());
            exit;

            flash(translate("Error"))->error();
            return back();
        }
    }

    public function createSupplyMission(Request $request, $type)
    {
        try {
            if (!is_array($request->checked_ids)) {
                $request->checked_ids = json_decode($request->checked_ids, true);
            }

            DB::beginTransaction();
            $model = new Mission();
            $model->fill($request['Mission']);
            $model->code = -1;
            $model->status_id = Mission::REQUESTED_STATUS;
            $model->type = Mission::SUPPLY_TYPE;
            if (!$model->save()) {
                throw new \Exception();
            }
            $code = '';
            for ($n = 0; $n < ShipmentSetting::getVal('mission_code_count'); $n++) {
                $code .= '0';
            }
            $code   =   substr($code, 0, -strlen($model->id));
            $model->code = ShipmentSetting::getVal('mission_prefix') . $code . $model->id;
            if (!$model->save()) {
                throw new \Exception();
            }
            foreach ($request->checked_ids as $shipment_id) {
                if ($model->id != null && ShipmentMission::check_if_shipment_is_assigned_to_mission($shipment_id, Mission::SUPPLY_TYPE) == 0) {
                    $shipment = Shipment::find($shipment_id);
                    $shipment_mission = new ShipmentMission();
                    $shipment_mission->shipment_id = $shipment->id;
                    $shipment_mission->mission_id = $model->id;
                    if ($shipment_mission->save()) {
                        $shipment->mission_id = $model->id;
                        $shipment->save();
                    }
                }
            }

            //Calaculate Amount
            $helper = new TransactionHelper();
            $helper->calculate_mission_amount($model->id);


            event(new CreateMission($model));
            DB::commit();

            if ($request->is('api/*')) {
                return $model;
            } else {
                return back()->with(['message_alert' => __('cargo::messages.created')]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            print_r($e->getMessage());
            exit;

            flash(translate("Error"))->error();
            return back();
        }
    }

    public function createReturnMission(Request $request, $type)
    {
        try {
            $request->checked_ids = json_decode($request->checked_ids, true);
            DB::beginTransaction();
            $model = new Mission();
            $model->fill($request['Mission']);
            $model->code = -1;
            $model->status_id = Mission::REQUESTED_STATUS;
            $model->otp  = MissionPRNG::get();
            $model->type = Mission::RETURN_TYPE;
            if (!$model->save()) {
                throw new \Exception();
            }
            $code = '';
            for ($n = 0; $n < ShipmentSetting::getVal('mission_code_count'); $n++) {
                $code .= '0';
            }
            $code   =   substr($code, 0, -strlen($model->id));
            $model->code = ShipmentSetting::getVal('mission_prefix') . $code . $model->id;
            if (!$model->save()) {
                throw new \Exception();
            }

            foreach ($request->checked_ids as $shipment_id) {
                if ($model->id != null && ShipmentMission::check_if_shipment_is_assigned_to_mission($shipment_id, Mission::RETURN_TYPE) == 0) {
                    $shipment = Shipment::find($shipment_id);
                    $shipment_mission = new ShipmentMission();
                    $shipment_mission->shipment_id = $shipment->id;
                    $shipment_mission->mission_id = $model->id;
                    if ($shipment_mission->save()) {
                        $shipment->mission_id = $model->id;
                        $shipment->save();
                    }
                }
            }

            //Calaculate Amount
            $helper = new TransactionHelper();
            $helper->calculate_mission_amount($model->id);

            event(new CreateMission($model));
            DB::commit();

            if ($request->is('api/*')) {
                return $model;
            } else {
                return back()->with(['message_alert' => __('cargo::messages.created')]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            print_r($e->getMessage());
            exit;

            flash(translate("Error"))->error();
            return back();
        }
    }

    public function removeShipmentFromMission(Request $request, $fromApi = false)
    {
        $shipment_id = $request->shipment_id;
        $mission_id = $request->mission_id;
        try {
            DB::beginTransaction();

            $mission = Mission::find($mission_id);
            $shipment = Shipment::find($shipment_id);
            if ($mission && $shipment && in_array($mission->status_id, [Mission::APPROVED_STATUS, Mission::REQUESTED_STATUS, Mission::RECIVED_STATUS])) {

                $action = new StatusManagerHelper();
                if ($mission->type == Mission::getType(Mission::PICKUP_TYPE)) {
                    $response = $action->change_shipment_status([$shipment_id], Shipment::SAVED_STATUS, $mission_id);
                } elseif (in_array($mission->type, [Mission::getType(Mission::DELIVERY_TYPE), Mission::getType(Mission::RETURN_TYPE), Mission::getType(Mission::TRANSFER_TYPE)]) && $mission->status_id == Mission::RECIVED_STATUS) {
                    $response = $action->change_shipment_status([$shipment_id], Shipment::RETURNED_STATUS, $mission_id);
                } elseif (in_array($mission->type, [Mission::getType(Mission::DELIVERY_TYPE), Mission::getType(Mission::RETURN_TYPE), Mission::getType(Mission::TRANSFER_TYPE)]) && in_array($mission->status_id, [Mission::APPROVED_STATUS, Mission::REQUESTED_STATUS])) {
                    $response = $action->change_shipment_status([$shipment_id], Shipment::RETURNED_STOCK, $mission_id);
                }

                if ($shipment_mission = $mission->shipment_mission_by_shipment_id($shipment_id)) {
                    $shipment_mission->delete();
                }
                $shipment_reason = new ShipmentReason();
                $shipment_reason->reason_id = $request->reason;
                $shipment_reason->shipment_id = $request->shipment_id;
                $shipment_reason->type = "Delete From Mission";
                $shipment_reason->save();
                //Calaculate Amount
                $helper = new TransactionHelper();
                $helper->calculate_mission_amount($mission_id);

                $mission_shipments = ShipmentMission::where('mission_id', $mission->id)->get();
                if (count($mission_shipments) == 0) {
                    $mission->status_id = Mission::DONE_STATUS;
                    $mission->save();
                }
                event(new UpdateMission($mission_id));
                // event(new ShipmentAction( Shipment::SAVED_STATUS,[$shipment]));
                DB::commit();
                if ($fromApi) {
                    return true;
                }
                return back()->with(['message_alert' => __('cargo::messages.deleted')]);
            } else {
                return back()->with(['error_message_alert' => __('cargo::messages.invalid')]);
            }
        } catch (\Exception $e) {
            DB::rollback();
            print_r($e->getMessage());
            exit;

            flash(translate("Error"))->error();
            return back();
        }
    }

    public function pay($shipment_id)
    {
        $shipment = Shipment::find($shipment_id);
        if (!$shipment || $shipment->paid == 1) {
            flash("Invalid Link")->error();
            return back();
        }

        // return $shipment;
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.shipments.pay', compact('shipment'));
    }

    public function ajaxGetEstimationCost(Request $request)
    {
        $request->validate([
            'total_weight' => 'required|numeric|min:0',
        ]);
        $costs = $this->applyShipmentCost($request, $request->package_ids);
        $formated_cost["tax"] = format_price($costs["tax"]);
        $formated_cost["insurance"] = format_price($costs["insurance"]);


        $formated_cost["return_cost"] = format_price($costs["return_cost"]);
        $formated_cost["shipping_cost"] = format_price($costs["shipping_cost"]);
        $formated_cost["total_cost"] = format_price($costs["shipping_cost"] + $costs["tax"] + $costs["insurance"]);

        return $formated_cost;
    }

    public function applyShipmentCost($request, $packages)
    {
        $client_costs    = Client::where('id', $request['client_id'])->first();
        $idPackages      = array_column($packages, 'package_id');
        $client_packages = ClientPackage::where('client_id', $request['client_id'])->whereIn('package_id', $idPackages)->get();

        $from_country_id = $request['from_country_id'];
        $to_country_id = $request['to_country_id'];

        if (isset($request['from_state_id']) && isset($request['to_state_id'])) {
            $from_state_id = $request['from_state_id'];
            $to_state_id = $request['to_state_id'];
        }
        if (isset($request['from_area_id']) && isset($request['to_area_id'])) {
            $from_area_id = $request['from_area_id'];
            $to_area_id = $request['to_area_id'];
        }

        $total_weight = 0;
        $package_extras = 0;

        if ($client_packages) {
            foreach ($client_packages as $pack) {
                $total_weight += isset($pack['weight']) ? $pack['weight'] : 1;
                $extra = $pack['cost'];
                $package_extras += $extra;
            }
        } else {
            foreach ($packages as $pack) {
                $total_weight += isset($pack['weight']) ? $pack['weight'] : 1;
                $extra = Package::find($pack['package_id'])->cost;
                $package_extras += $extra;
            }
        }

        //$weight =  $request['total_weight'];
        $weight = isset($request['total_weight']) ? $request['total_weight'] : $total_weight;

        $array = ['return_cost' => 0, 'shipping_cost' => 0, 'tax' => 0, 'insurance' => 0];

        // Shipping Cost = Default + kg + Covered Custom  + Package extra
        $covered_cost = Cost::where('from_country_id', $from_country_id)->where('to_country_id', $to_country_id);

        if (isset($request['from_area_id']) && isset($request['to_area_id'])) {
            $covered_cost = $covered_cost->where('from_area_id', $from_area_id)->where('to_area_id', $to_area_id);
            if (!$covered_cost->first()) {
                $covered_cost = Cost::where('from_country_id', $from_country_id)->where('to_country_id', $to_country_id);

                if (isset($request['from_state_id']) && isset($request['to_state_id'])) {
                    $covered_cost = $covered_cost->where('from_state_id', $from_state_id)->where('to_state_id', $to_state_id);
                    if (!$covered_cost->first()) {
                        $covered_cost = Cost::where('from_country_id', $from_country_id)->where('to_country_id', $to_country_id);
                        $covered_cost = $covered_cost->where('from_state_id', 0)->where('to_state_id', 0);
                    }
                } else {
                    $covered_cost = $covered_cost->where('from_area_id', 0)->where('to_area_id', 0);
                    if (!$covered_cost->first()) {
                        $covered_cost = Cost::where('from_country_id', $from_country_id)->where('to_country_id', $to_country_id);
                        $covered_cost = $covered_cost->where('from_state_id', 0)->where('to_state_id', 0);
                    }
                }
            }
        } else {

            if (isset($request['from_state_id']) && isset($request['to_state_id'])) {
                $covered_cost = $covered_cost->where('from_state_id', $from_state_id)->where('to_state_id', $to_state_id);
            } else {
                $covered_cost = $covered_cost->where('from_area_id', 0)->where('to_area_id', 0);
                if (!$covered_cost->first()) {
                    $covered_cost = Cost::where('from_country_id', $from_country_id)->where('to_country_id', $to_country_id);
                    $covered_cost = $covered_cost->where('from_state_id', 0)->where('to_state_id', 0);
                }
            }
        }
        $covered_cost = $covered_cost->first();

        $def_return_cost_gram = $client_costs && $client_costs->def_return_cost_gram   ? $client_costs->def_return_cost_gram   : ShipmentSetting::getCost('def_return_cost_gram');
        $def_return_cost      = $client_costs && $client_costs->def_return_cost ? $client_costs->def_return_cost : ShipmentSetting::getCost('def_return_cost');

        $def_shipping_cost_gram = $client_costs && $client_costs->def_shipping_cost_gram ? $client_costs->def_shipping_cost_gram : ShipmentSetting::getCost('def_shipping_cost_gram');
        $def_shipping_cost      = $client_costs && $client_costs->def_shipping_cost ? $client_costs->def_shipping_cost : ShipmentSetting::getCost('def_shipping_cost');

        $def_return_mile_cost_gram = $client_costs && $client_costs->def_return_mile_cost_gram ? $client_costs->def_return_mile_cost_gram : ShipmentSetting::getCost('def_return_mile_cost_gram');
        $def_return_mile_cost      = $client_costs && $client_costs->def_return_mile_cost ? $client_costs->def_return_mile_cost : ShipmentSetting::getCost('def_return_mile_cost');

        $def_mile_cost_gram = $client_costs && $client_costs->def_mile_cost_gram ? $client_costs->def_mile_cost_gram : ShipmentSetting::getCost('def_mile_cost_gram');
        $def_mile_cost      = $client_costs && $client_costs->def_mile_cost ? $client_costs->def_mile_cost : ShipmentSetting::getCost('def_mile_cost');

        $def_insurance_gram = $client_costs && $client_costs->def_insurance_gram ? $client_costs->def_insurance_gram : ShipmentSetting::getCost('def_insurance_gram');
        $def_insurance      = $client_costs && $client_costs->def_insurance ? $client_costs->def_insurance : ShipmentSetting::getCost('def_insurance');


        $def_tax_gram = $client_costs && $client_costs->def_tax_gram ? $client_costs->def_tax_gram : ShipmentSetting::getCost('def_tax_gram');
        $def_tax      = $client_costs && $client_costs->def_tax ? $client_costs->def_tax : ShipmentSetting::getCost('def_tax');




        if ($covered_cost != null) {
            if ($weight > 1) {
                if (ShipmentSetting::getVal('is_def_mile_or_fees') == '2') {
                    $return_cost = (float) $def_return_cost ?? $covered_cost->return_cost + (float) ($def_return_cost_gram * ($weight - 1));
                    $shipping_cost_first_one = (float) ($def_shipping_cost != null ? $def_shipping_cost : $covered_cost->shipping_cost) + $package_extras;
                    $shipping_cost_for_extra = (float) ($def_shipping_cost_gram * ($weight - 1));
                } else if (ShipmentSetting::getVal('is_def_mile_or_fees') == '1') {
                    $return_cost = (float) $def_return_mile_cost ?? $covered_cost->return_mile_cost + (float) ($def_return_mile_cost_gram * ($weight - 1));
                    $shipping_cost_first_one = (float) ($def_mile_cost ?? $covered_cost->mile_cost) + $package_extras;
                    $shipping_cost_for_extra = (float) ($def_mile_cost_gram * ($weight - 1));
                }
                $insurance = (float) $def_insurance ?? $covered_cost->insurance + (float) ($def_insurance_gram * ($weight - 1));

                $tax_for_first_one = (($def_tax ?? $covered_cost->tax * $shipping_cost_first_one) / 100);

                $tax_for_exrea = (($def_tax_gram * $shipping_cost_for_extra) / 100);

                $shipping_cost = $shipping_cost_first_one + $shipping_cost_for_extra;
                $tax = $tax_for_first_one + $tax_for_exrea;
            } else {

                if (ShipmentSetting::getVal('is_def_mile_or_fees') == '2') {

                    $return_cost = (float) $def_return_cost ?? $covered_cost->return_cost;
                    $shipping_cost = (float) ($def_shipping_cost != null ? $def_shipping_cost : $covered_cost->shipping_cost) + $package_extras;
                } else if (ShipmentSetting::getVal('is_def_mile_or_fees') == '1') {
                    $return_cost = (float) $def_return_mile_cost ?? $covered_cost->return_mile_cost;
                    $shipping_cost = (float) ($def_mile_cost ?? $covered_cost->mile_cost) + $package_extras;
                }
                $insurance = (float) $def_insurance ?? $covered_cost->insurance;
                $tax = (($def_tax ?? $covered_cost->tax * $shipping_cost) / 100);
            }

            $array['tax'] = $tax;
            $array['insurance'] = $insurance;
            $array['return_cost'] = $return_cost;
            $array['shipping_cost'] = $shipping_cost;
        } else {
            if ($weight > 1) {
                if (ShipmentSetting::getVal('is_def_mile_or_fees') == '2') {
                    $return_cost = $def_return_cost + (float) ($def_return_cost_gram * ($weight - 1));
                    $shipping_cost_first_one = $def_shipping_cost + $package_extras;
                    $shipping_cost_for_extra = (float) ($def_shipping_cost_gram * ($weight - 1));
                } else if (ShipmentSetting::getVal('is_def_mile_or_fees') == '1') {
                    $return_cost = $def_return_mile_cost + (float) ($def_return_mile_cost_gram * ($weight - 1));
                    $shipping_cost_first_one = $def_mile_cost + $package_extras;
                    $shipping_cost_for_extra = (float) ($def_mile_cost_gram * ($weight - 1));
                }

                $insurance = $def_insurance + (float) ($def_insurance_gram * ($weight - 1));
                $tax_for_first_one = (($def_tax * $shipping_cost_first_one) / 100);
                $tax_for_exrea = ((ShipmentSetting::getCost('def_tax_gram') * $shipping_cost_for_extra) / 100);

                $shipping_cost = $shipping_cost_first_one + $shipping_cost_for_extra;
                $tax = $tax_for_first_one + $tax_for_exrea;
            } else {
                if (ShipmentSetting::getVal('is_def_mile_or_fees') == '2') {
                    $return_cost = $def_return_cost;
                    $shipping_cost = $def_shipping_cost + $package_extras;
                } else if (ShipmentSetting::getVal('is_def_mile_or_fees') == '1') {
                    $return_cost = $def_return_mile_cost;
                    $shipping_cost = $def_mile_cost + $package_extras;
                }
                $insurance = $def_insurance;
                $tax = (($def_tax * $shipping_cost) / 100);
            }

            $array['tax'] = $tax;
            $array['insurance'] = $insurance;
            $array['return_cost'] = $return_cost;
            $array['shipping_cost'] = $shipping_cost;
        }
        return $array;
    }

    public function print($shipment, $type = 'invoice')
    {
        $shipment = Shipment::with('nwcReceipt')->find($shipment);
        if ($type == 'label') {
            $adminTheme = env('ADMIN_THEME', 'adminLte');
            return view('cargo::' . $adminTheme . '.pages.shipments.print-label', compact('shipment'));
        } else {
            breadcrumb([
                [
                    'name' => __('cargo::view.dashboard'),
                    'path' => fr_route('admin.dashboard')
                ],
                [
                    'name' => __('cargo::view.shipments'),
                    'path' => fr_route('shipments.index')
                ],
                [
                    'name' => __('cargo::view.shipment') . ' ' . $shipment->code,
                    'path' => fr_route('shipments.show', $shipment->id)
                ],
                [
                    'name' => __('cargo::view.print_invoice'),
                ],
            ]);
            $adminTheme = env('ADMIN_THEME', 'adminLte');
            return view(
                'cargo::' . $adminTheme . '.pages.shipments.print-invoice',
                [
                    'shipment' => $shipment,
                    'receipt'  => $shipment?->nwcReceipt,
                ]
            );
        }
    }

    public function logPrint(Request $request, AuditLogService $auditLogService, $shipment)
    {
        $request->validate([
            'type' => 'required|in:invoice,receipt',
        ]);

        $shipment = Shipment::with(['receipt', 'nwcReceipt'])->findOrFail($shipment);
        $user = auth()->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if ($request->type === 'invoice' && !$user->can('print-shipment-invoice')) {
            return response()->json(['success' => false, 'message' => 'You are not allowed to print invoices.'], 403);
        }

        if ($request->type === 'receipt' && !$user->can('print-shipment-receipt')) {
            return response()->json(['success' => false, 'message' => 'You are not allowed to print receipts.'], 403);
        }

        $receiptNumber = $shipment->receipt?->receipt_number ?? $shipment->nwcReceipt?->receipt_number;
        $event = $request->type === 'receipt' ? 'receipt_printed' : 'invoice_printed';
        $printLabel = $request->type === 'receipt' ? 'Receipt' : 'Invoice';

        $auditLogService->createLog(
            $event,
            $shipment,
            null,
            [],
            [
                'print_type' => $request->type,
                'receipt_number' => $receiptNumber,
                'shipment_code' => $shipment->code,
                'printed_at' => now()->toDateTimeString(),
            ],
            $printLabel . ' print initiated by ' . ($user->name ?? 'System')
        );

        return response()->json(['success' => true]);
    }

    public function printTracking($shipment)
    {

        $shipment = Shipment::find($shipment);
        $client = Client::where('id', $shipment->client_id)->first();
        $PackageShipment = PackageShipment::where('shipment_id', $shipment->id)->get();
        $ClientAddress = ClientAddress::where('client_id', $shipment->client_id)->first();

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.shipments.print-tracking')->with(['model' => $shipment, 'client' => $client, 'PackageShipment' => $PackageShipment, 'ClientAddress' => $ClientAddress]);
    }

    public function printStickers(Request $request)
    {
        $request->checked_ids = json_decode($request->checked_ids, true);
        $shipments = Shipment::whereIn('id', $request->checked_ids)->get();
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.shipments.print-stickers', compact('shipments'));
    }

    public function ShipmentApis()
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.shipment_apis'),
            ],
        ]);
        $client = Client::where('user_id', auth()->user()->id)->first();

        $countries = Country::where('covered', 1)->get();
        $states    = State::where('covered', 1)->get();
        $areas     = Area::get();
        $packages  = Package::all();
        $branches   = Branch::where('is_archived', 0)->get();
        $paymentsGateway = BusinessSetting::where("key", "payment_gateway")->where("value", "1")->get();
        $addresses       = ClientAddress::where('client_id', $client->id)->get();
        $deliveryTimes   = DeliveryTime::all();

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.shipments.apis')
            ->with(['countries' => $countries, 'states' => $states, 'areas' => $areas, 'packages' => $packages, 'branches' => $branches, 'paymentsGateway' => $paymentsGateway, 'deliveryTimes' => $deliveryTimes, 'client' => $client, 'addresses' => $addresses]);
    }

    public function ajaxGgenerateToken()
    {
        $userRegistrationHelper = new UserRegistrationHelper(auth()->user()->id);
        $token = $userRegistrationHelper->setApiTokenGenerator();

        return response()->json($token);
    }

    public function createMissionAPI(Request $request)
    {

        $apihelper = new ApiHelper();
        $user = $apihelper->checkUser($request);

        if ($user) {
            $request->validate([
                'checked_ids'       => 'required',
                'type'              => 'required',
                'Mission.client_id' => 'required',
                'Mission.address'   => 'required',
            ]);

            $count = 0;
            foreach ($request->checked_ids as $id) {
                if (Shipment::whereIn('id', $request->checked_ids)->pluck('mission_id')->first()) {
                    $count++;
                }
            }
            if ($count >= 1) {
                return response()->json(['message' => 'this shipment already in mission']);
            } else {
                switch ($request->type) {
                    case Mission::PICKUP_TYPE:
                        $mission = $this->createPickupMission($request, $request->type);
                        break;
                    case Mission::DELIVERY_TYPE:
                        $mission = $this->createDeliveryMission($request, $request->type);
                        break;
                    case Mission::TRANSFER_TYPE:
                        $mission = $this->createTransferMission($request, $request->type);
                        break;
                    case Mission::SUPPLY_TYPE:
                        $mission = $this->createSupplyMission($request, $request->type);
                        break;
                    case Mission::RETURN_TYPE:
                        $mission = $this->createReturnMission($request, $request->type);
                        break;
                }
                return response()->json($mission);
            }
        } else {
            return response()->json(['message' => 'Not Authorized']);
        }
    }

    public function BarcodeScanner()
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.barcode_scanner'),
            ],
        ]);
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.shipments.barcode-scanner');
    }
    public function ChangeStatusByBarcode(Request $request)
    {
        if ($request->checked_ids) {
            $request->checked_ids = json_decode($request->checked_ids, true);
        } else {
            return back()->with(['message_alert' => __('cargo::view.no_shipments_added')]);
        }
        $user_role = auth()->user()->role;
        $action    = new StatusManagerHelper();
        $shipments = Shipment::whereIn('code', $request->checked_ids)->get();

        if (count($shipments) > 0) {
            foreach ($shipments as $shipment) {
                if ($shipment) {
                    $mission = Mission::where('id', $shipment->mission_id)->first();

                    $request->request->add(['ids' => [$shipment->id]]);
                    if ($user_role == 5) { // ROLE 5 == DRIVER

                        if ($shipment->status_id == Shipment::CAPTAIN_ASSIGNED_STATUS) // casa if shipment in delivery mission
                        {
                            $to = Shipment::RECIVED_STATUS;
                            $response = $action->change_shipment_status($request->ids, $to, $mission->id ?? null);
                            if ($response['success']) {
                                event(new ShipmentAction($to, $request->ids));
                            } else {
                                return back()->with(['error_message_alert' => __('cargo::messages.somthing_wrong')]);
                            }
                        } else {
                            $message = __('cargo::view.cant_change_this_shipment') . $shipment->code;
                            return back()->with(['error_message_alert' => $message]);
                        }
                    } elseif (auth()->user()->can('shipments-barcode-scanner') || $user_role == 1) { // ROLE 1 == ADMIN

                        if ($mission && $mission->type == Mission::getType(Mission::PICKUP_TYPE) && $mission->status_id == Mission::RECIVED_STATUS) {
                            // casa if shipment in packup mission
                            $to = Shipment::APPROVED_STATUS;
                            $response = $action->change_shipment_status($request->ids, $to, $mission->id ?? null);
                            if ($response['success']) {
                                event(new ShipmentAction($to, $request->ids));
                            } else {
                                return back()->with(['error_message_alert' => __('cargo::messages.somthing_wrong')]);
                            }
                        } elseif ($shipment->status_id == Shipment::RETURNED_STATUS) {
                            // casa if shipment in returned mission
                            $to = Shipment::RETURNED_STOCK;
                            $response = $action->change_shipment_status($request->ids, $to, $mission->id ?? null);
                            if ($response['success']) {
                                event(new ShipmentAction($to, $request->ids));
                            } else {
                                return back()->with(['error_message_alert' => __('cargo::messages.somthing_wrong')]);
                            }
                        } else {
                            $message = __('cargo::view.cant_change_this_shipment') . $shipment->code;
                            return back()->with(['error_message_alert' => $message]);
                        }
                    }
                } else {
                    $message = __('cargo::view.no_shipment_with_this_barcode') . $shipment->code;
                    return back()->with(['error_message_alert' => $message]);
                }
            }
            return back()->with(['message_alert' => __('cargo::messages.saved')]);
        } else {
            return back()->with(['error_message_alert' => __('cargo::view.no_shipment_with_this_barcode')]);
        }
    }

    public function trackingView(Request $request)
    {
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.shipments.tracking-view');
    }


    // Tracking Get results function
    public function tracking(Request $request)
    {
        try {

            $shipment = Shipment::where('code', $request->code)
                ->latest()
                ->first();
                
            $adminTheme = env('ADMIN_THEME', 'adminLte');

            $track_map = [];
            if (empty($request->code)) {
                return view('cargo::adminLte.pages.shipments.tracking')
                ->with([
                    'error' => __('cargo::view.enter_your_tracking_code'),
                    'model' => $shipment,
                    'track_map' => $this->getFallbackTrackMap(),
                ]);
            }
        
            if (empty($shipment)) {
                return view('cargo::adminLte.pages.shipments.tracking')->with([
                    'error' => __('cargo::view.error_in_shipment_number'),
                    'model' => $shipment,
                    'track_map' => $this->getFallbackTrackMap(),
                ]);
            }

            // Get consignment and tracking info
            $cons = Consignment::where('id', $shipment->consignment_id)->first();
            
            if (!$cons) {
                return view('cargo::' . $adminTheme . '.pages.shipments.tracking')
                    ->with([
                        'model' => $shipment,
                        'track_map' => $this->getFallbackTrackMap(),
                    ]);
            }

            $track_map = $this->getTrackMapArray($cons);

            return view('cargo::' . $adminTheme . '.pages.shipments.tracking')
                ->with([
                    'model' => $shipment,
                    'track_map' => $track_map,
                    'container' => $cons
                ]);

        } catch (\Exception $e) {
            // dd($e);
            \Log::error('Tracking Error: ' . $e->getMessage());
            return view('cargo::adminLte.pages.shipments.tracking')
                ->with([
                    'error' => __('cargo::messages.error_occurred'),
                    'model' => null,
                    'track_map' => [],
            ]);
        }
    }

    public function calculator(Request $request)
    {
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.shipments.shipment-calculator');
    }

    public function calculatorStore(Request $request)
    {

        $request->validate([
            'Shipment.type'            => 'required',
            'Shipment.branch_id'       => 'required',
            'Shipment.client_phone'    => 'required_if:if_have_account,==,0',
            'Shipment.reciver_name'    => 'required|string|min:3|max:50',
            'Shipment.reciver_phone'   => 'required',
            'Shipment.reciver_address' => 'required|string|min:8',
            'Shipment.from_country_id' => 'required',
            'Shipment.to_country_id'   => 'required',
            'Shipment.from_state_id'   => 'required',
            'Shipment.to_state_id'     => 'required',
            'Shipment.from_area_id'    => 'required',
            'Shipment.to_area_id'      => 'required',
            'Shipment.payment_type'    => 'required',
            'Shipment.payment_method_id' => 'required',
        ]);
        $ClientController = new ClientController(new AclRepository);

        $shipment = $request->Shipment;

        if ($request->if_have_account == '1') {
            $client = Client::where('email', $request->client_email)->first();
            Auth::loginUsingId($client->user_id);
        } elseif ($request->if_have_account == '0') {
            // Add New Client

            $request->request->add(['name' => $request->client_name]);
            $request->request->add(['email' => $request->client_email]);
            $request->request->add(['password' => $request->client_password]);
            $request->request->add(['responsible_mobile' => $request->Shipment['client_phone']]);
            $request->request->add(['responsible_name' => $request->client_name]);
            $request->request->add(['national_id' => $request->national_id ?? '']);
            $request->request->add(['branch_id' => $request->Shipment['branch_id']]);
            $request->request->add(['terms_conditions' => '1']);
            $client = $ClientController->registerStore($request, true);
        }

        if ($client) {
            $shipment['client_id']    = $client->id;
            $shipment['client_phone'] = $client->responsible_mobile;

            // Add New Client Address
            $request->request->add(['client_id' => $client->id]);
            $request->request->add(['address' => $request->client_address]);
            $request->request->add(['country' => $request->Shipment['from_country_id']]);
            $request->request->add(['state'   => $request->Shipment['from_state_id']]);
            if (isset($request->area)) {
                $request->request->add(['area' => $request->Shipment['from_area_id']]);
            }
            $new_address        = $ClientController->addNewAddress($request, $calc = true);
            if ($new_address) {
                $shipment['client_address'] = $new_address->id;
            }
        }
        $request->Shipment = $shipment;
        $model = $this->storeShipment($request);
        return redirect()->route('shipments.show', $model->id)->with(['message_alert' => __('cargo::messages.created')]);
    }

    public function ajaxGetShipmentByBarcode(Request $request)
    {
        $apihelper = new ApiHelper();
        $user = $apihelper->checkUser($request);

        if ($user) {
            $userClient = Client::where('user_id', $user->id)->first();
            $barcode    = $request->barcode;
            $shipment   = Shipment::where('client_id', $userClient->id)->where('barcode', $barcode)->first();
            return response()->json($shipment);
        } else {
            return response()->json(['message' => 'Not Authorized']);
        }
    }

    public function shipmentsReport(ShipmentsDataTable $dataTable, $status = 'all', $type = null)
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.shipments_report')
            ]
        ]);

        $data_with = [];
        $share_data = array_merge(get_class_vars(ShipmentsDataTable::class), $data_with);

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return $dataTable->render('cargo::' . $adminTheme . '.pages.shipments.report', $share_data);
    }

    public function updatePaymentMeth(Request $request)
    {
        $shipment = Shipment::where('id', $request->shipment_id)->first();

        if (!$shipment) {
            return response()->json(['success' => false, 'message' => 'Shipment not found'], 404);
        }

        $shipment->update([
            'payment_method_id' => $request->input('payment_method')
        ]);

        return response()->json([
            'success' => true,
            'shipment' => $shipment
        ], 200);
    }

    /**
     * Process a refund for a shipment payment
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refundPayment(Request $request, RefundService $refundService, AuditLogService $auditLogService)
    {
        $request->validate([
            'shipment_id' => 'required|exists:shipments,id',
            'refund_type' => 'required|in:full,partial',
            'amount' => 'nullable|numeric|min:0.01',
            'reason' => 'required|string|max:5000',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.'
            ], 401);
        }

        $settings = app(GeneralSettings::class);
        if (!(bool) ($settings->enable_refund_payments ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Refunds are disabled.'
            ], 403);
        }

        $canDirectRefund = $user->can('approve-refund-requests');
        if (!$canDirectRefund) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to approve refunds.'
            ], 403);
        }

        if ($user->role == 4) {
            return response()->json([
                'success' => false,
                'message' => 'Clients must request refunds.'
            ], 403);
        }

        $shipment = Shipment::with(['paymentReceipts', 'receipt'])->findOrFail($request->shipment_id);

        if (!$shipment->paid) {
            return response()->json([
                'success' => false,
                'message' => 'This shipment is not marked as paid.'
            ], 400);
        }

        $pendingRequest = RefundRequest::where('shipment_id', $shipment->id)
            ->where('status', RefundRequest::STATUS_PENDING)
            ->first();
        if ($pendingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'A refund request is already pending for this shipment.'
            ], 409);
        }

        $refundType = $request->refund_type;
        $amount = $refundType === RefundRequest::TYPE_PARTIAL ? (float) $request->amount : 0.0;

        if ($refundType === RefundRequest::TYPE_PARTIAL && $amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Refund amount must be greater than zero.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $result = $refundService->applyRefund(
                $shipment,
                $amount,
                $refundType,
                $request->reason
            );

            $auditLogService->createLog(
                'refund_processed',
                $shipment,
                null,
                [],
                [
                    'refund_type' => $refundType,
                    'refund_amount' => $result['refund_amount'],
                    'refunded_total' => $result['refunded_total'],
                ],
                'Refund processed by ' . ($user->name ?? 'System')
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment refunded successfully'
            ]);
        } catch (\RuntimeException $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Refund Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark a shipment as paid
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsPaid(Request $request, AuditLogService $auditLogService)
    {
        $request->validate([
            'shipment_id'       => 'required|exists:shipments,id',
            'discount_type'     => 'nullable|in:percent,percentage,fixed',
            'discount_value'    => 'nullable|numeric|min:0',
            'final_total'       => 'required|numeric|min:0',
            'method_of_payment' => 'required|array',
            'method_of_payment.*' => 'string|max:255',
            'payment_amount'    => 'required|array',
            'payment_amount.*'  => 'numeric|min:0',
            'current_user'      => 'nullable|string|max:255',
        ]);

        // Validate that the arrays have the same length
        if (count($request->method_of_payment) !== count($request->payment_amount)) {
            return response()->json([
                'error'   => 'Payment arrays mismatch.',
                'message' => 'The number of payment methods must match the number of payment amounts.'
            ], 400);
        }

        // Calculate total payment amount
        $totalPayment = array_sum($request->payment_amount);
        $finalTotal = (float) $request->final_total;

        if (abs($totalPayment - $finalTotal) > 0.01) {
            return response()->json([
                'error'   => 'Payment mismatch.',
                'message' => 'The sum of payment amounts does not match the final total.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $shipment = Shipment::with('nwcReceipt')->findOrFail($request->shipment_id);
            $oldValues = $shipment->only(['paid']);

            $discountType = $request->filled('discount_type') ? $request->discount_type : null;
            if ($discountType === 'percentage') {
                $discountType = 'percent';
            }
            $discountValue = $request->discount_value !== null ? (float) $request->discount_value : 0.0;

            $baseAmount = (float) $request->final_total;
            $discountAmount = 0.0;
            $calculatedFinalTotal = $baseAmount;

            if ($discountType && $discountValue > 0) {
                if ($discountType === 'percent') {
                    $discountAmount = ($baseAmount * $discountValue) / 100;
                } elseif ($discountType === 'fixed') {
                    $discountAmount = $discountValue;
                }

                $calculatedFinalTotal = max(0, $baseAmount - $discountAmount);
            } else {
                $discountType = null;
                $discountValue = 0.0;
            }

            if (abs($calculatedFinalTotal - (float) $request->final_total) > 0.01) {
                throw new \RuntimeException('Final total mismatch with discount calculation.');
            }

            $shipment->paid = 1;
            $shipment->save();

            $lastTransaction = Transxn::orderByDesc('id')->first();
            $lastId = $lastTransaction ? (int) $lastTransaction->id : 0;
            $nextReceiptNumber = 'REC-' . str_pad((string) ($lastId + 1), 6, '0', STR_PAD_LEFT);

            $transaction = Transxn::create([
                'shipment_id'    => $shipment->id,
                'receipt_number' => $nextReceiptNumber,
                'discount_type'  => $discountType,
                'discount_value' => $discountValue,
                'total'          => $calculatedFinalTotal,
                'status'         => 'completed',
            ]);

            // Create multiple payment receipt records
            $paymentReceipts = [];
            $user = Auth::user();
            $cashierName = $request->current_user ?: ($user?->name ?? 'System');
            
            foreach ($request->method_of_payment as $index => $method) {
                if (!empty($method) && isset($request->payment_amount[$index]) && $request->payment_amount[$index] > 0) {
                    $receiptNumber = $nextReceiptNumber . '-' . ($index + 1); // Create unique receipt numbers for each payment
                    
                    $paymentReceipt = ShipmentPaymentReceipt::create([
                        'shipment_id' => $shipment->id,
                        'method_of_payment' => trim($method),
                        'amount' => (float) $request->payment_amount[$index],
                        'receipt_number' => $receiptNumber,
                        'cashier_name' => $cashierName,
                        'user_id' => $user?->id,
                    ]);
                    
                    $paymentReceipts[] = $paymentReceipt;
                }
            }

            $receiptAttributeKeys = [
                'receipt_number',
                'rate',
                'bill_usd',
                'bill_kwacha',
                'method_of_payment',
                'discount_type',
                'discount_value',
                'cashier_name',
            ];

            $existingReceipt = $shipment->nwcReceipt;
            $oldReceiptValues = $existingReceipt ? $existingReceipt->only($receiptAttributeKeys) : [];

            $exchangeRateRecord = CurrencyExchangeRate::query()->first();
            $exchangeRate = $exchangeRateRecord ? (float) $exchangeRateRecord->exchange_rate : null;

            $billKwachaValue = convert_currency($shipment->amount_to_be_collected, 'usd', 'zmw');
            if (!is_numeric($billKwachaValue)) {
                $sanitizedKwacha = preg_replace('/[^0-9.\-]/', '', (string) $billKwachaValue);
                $billKwachaValue = $sanitizedKwacha === '' ? null : $sanitizedKwacha;
            }
            $billKwacha = is_numeric($billKwachaValue) ? (float) $billKwachaValue : 0.0;
            $billKwacha = round($billKwacha, 2);

            $billUsd = round((float) $shipment->amount_to_be_collected, 2);

            if ($exchangeRate && $exchangeRate > 0) {
                $billUsd = round($billKwacha / $exchangeRate, 2);
            } elseif ($billUsd > 0 && $billKwacha > 0) {
                $exchangeRate = round($billKwacha / $billUsd, 6);
            } else {
                $exchangeRate = null;
            }

            // For backward compatibility, we'll use the first payment method in the main receipt record
            $firstPaymentMethod = !empty($request->method_of_payment) ? trim($request->method_of_payment[0]) : '';

            $receiptData = [
                'receipt_number'    => $nextReceiptNumber,
                'rate'              => $exchangeRate,
                'bill_usd'          => $billUsd,
                'bill_kwacha'       => $billKwacha,
                'method_of_payment' => $firstPaymentMethod,
                'discount_type'     => $discountType,
                'discount_value'    => $discountValue,
                'cashier_name'      => $cashierName,
                'user_id'           => $user?->id,
            ];

            $receipt = NwcReceipt::updateOrCreate(
                ['shipment_id' => $shipment->id],
                $receiptData
            );
            $shipment->setRelation('nwcReceipt', $receipt);

            $auditLogService->createLog(
                $existingReceipt ? 'updated' : 'created',
                $receipt,
                null,
                $oldReceiptValues,
                $receipt->only($receiptAttributeKeys),
                ($existingReceipt ? 'Receipt updated' : 'Receipt recorded') . ' for shipment ' . $shipment->code
            );

            $auditLogService->createLog(
                'updated',
                $shipment,
                null,
                $oldValues,
                $shipment->only(['paid']),
                'Shipment marked as paid by ' . $cashierName
            );

            // Create audit logs for each payment method
            foreach ($paymentReceipts as $paymentReceipt) {
                $auditLogService->createLog(
                    'created',
                    $paymentReceipt,
                    null,
                    [],
                    $paymentReceipt->only(['method_of_payment', 'amount', 'receipt_number']),
                    'Payment receipt recorded for shipment ' . $shipment->code . ' - ' . $paymentReceipt->method_of_payment . ': ' . $paymentReceipt->amount
                );
            }

            DB::commit();

            return response()->json([
                'message'         => 'Payment recorded successfully.',
                'transaction'     => $transaction,
                'receipt'         => $receipt->only($receiptAttributeKeys),
                'payment_receipts' => $paymentReceipts
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            \Log::error('Mark as Paid Error: ' . $th->getMessage(), ['exception' => $th]);

            return response()->json([
                'error'   => 'Payment failed.',
                'message' => $th->getMessage(),
            ], 500);
        }
    }


    public function overview()
    {
        $shipmentsQuery = \Modules\Cargo\Entities\Shipment::with(['consignment', 'from_country', 'to_country', 'from_state', 'to_state', 'branch', 'client']);
        $shipments = \Modules\Cargo\Entities\Shipment::getShipments($shipmentsQuery)->latest()->get();

        // Calculate statistics for sea consignments only
        $seaShipments = $shipments->filter(function ($shipment) {
            return $shipment->consignment && $shipment->consignment->cargo_type === 'sea';
        });
        
        $seaStats = [
            'total' => $seaShipments->count(),
            'delivered' => $seaShipments->where('status_id', \Modules\Cargo\Entities\Shipment::DELIVERED_STATUS)->count(),
            'in_transit' => $seaShipments->where('status_id', \Modules\Cargo\Entities\Shipment::PENDING_STATUS)->count(),
        ];

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::' . $adminTheme . '.pages.shipments.overview', compact('shipments', 'seaStats'));
    }
}
