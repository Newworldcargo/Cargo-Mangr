<?php

namespace Modules\Cargo\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Cargo\Http\DataTables\ClientsDataTable;
use Modules\Cargo\Http\DataTables\ClientAddressDataTable;
use Modules\Cargo\Http\Requests\ClientRequest;
use Modules\Cargo\Http\Requests\ClientAddressRequest;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Shipment;
use App\Models\User;
use Modules\Cargo\Http\Helpers\UserRegistrationHelper;
use Modules\Users\Events\UserCreatedEvent;
use Modules\Users\Events\UserUpdatedEvent;
use Modules\Cargo\Entities\Package;
use Modules\Cargo\Entities\ClientPackage;
use Modules\Cargo\Entities\ClientAddress;
use Modules\Cargo\Http\Requests\AddressRequest;
use Modules\Cargo\Entities\BusinessSetting;
use app\Http\Helpers\ApiHelper;
use App\Mail\WelcomeMail;
use DB;
use Modules\Cargo\Events\AddClient;
use Modules\Acl\Repositories\AclRepository;
use Modules\Cargo\Http\Requests\RegisterRequest;
use Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class ClientController extends Controller
{
    private $aclRepo;

    public function __construct(AclRepository $aclRepository)
    {
        $this->aclRepo = $aclRepository;
        // check on permissions
        $this->middleware('user_role:1|0|3')->only('index','clientsReport');
        $this->middleware('user_role:1|0|3|4')->only('show');
        $this->middleware('user_role:1|0|3')->only('create', 'store');
        $this->middleware('user_role:1|0|3')->only('edit');
        $this->middleware('user_role:1|0|3|4')->only('update');
        $this->middleware('user_role:1|0|3')->only('delete', 'multiDestroy');
        $this->middleware('user_role:4')->only('profile');
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(ClientsDataTable $dataTable)
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.clients')
            ]
        ]);
        $data_with = [];
        $share_data = array_merge(get_class_vars(ClientsDataTable::class), $data_with);
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return $dataTable->render('cargo::'.$adminTheme.'.pages.clients.index', $share_data);
    }

    public function newAddress() {

        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.add_address'),
            ],
        ]);

        if(auth()->user()->role == 3){
            $branches = Branch::where('is_archived',0)->where('user_id',auth()->user()->id)->get();
        }else{
            $branches = Branch::where('is_archived',0)->get();
        }

        $client = Client::where('user_id', auth()->user()->id)->first();

        $adminTheme = env('ADMIN_THEME', 'adminLte');
            $branches = Branch::where('is_archived',0)->where('user_id',auth()->user()->id)->get();
        return view('cargo::'.$adminTheme.'.pages.clients.create_add_address')->with(['client'=>$client ,'branches'=>$branches]);
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
                'name' => __('cargo::view.clients'),
                'path' => fr_route('clients.index')
            ],
            [
                'name' => __('cargo::view.add_client'),
            ],
        ]);
        if(auth()->user()->role == 3){
            $branches = Branch::where('is_archived',0)->where('user_id',auth()->user()->id)->get();
        }else{
            $branches = Branch::where('is_archived',0)->get();
        }
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::'.$adminTheme.'.pages.clients.create')->with(['branches' => $branches]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(ClientRequest $request)
    {
        $data = $request->only(['def_mile_cost','def_return_mile_cost','def_mile_cost_gram','def_return_mile_cost_gram','def_return_cost_gram','def_insurance_gram','def_tax_gram','def_shipping_cost_gram','def_return_cost','def_insurance','def_tax','def_shipping_cost','supply_cost','pickup_cost','how_know_us','package_access_options','follow_up_mobile','follow_up_country_code' , 'follow_up_name','name', 'email', 'password', 'responsible_mobile', 'country_code' ,'responsible_name','national_id','branch_id','address']);

        $Userdata['name']     = $data['name'];
        $Userdata['email']    = $data['email'];
        $Userdata['password'] = $data['password'];
        $Userdata['role']     = 4;
        $userRegistrationHelper = new UserRegistrationHelper();
		$response = $userRegistrationHelper->NewUser($Userdata);
        if(!$response['success']){
            throw new \Exception($response['error_msg']);
        }

        $data['code']    = 0;
        $data['user_id'] = $response['user']['id'];
        $data['created_by'] = auth()->check() ? auth()->id() : null;
        unset($data['password']);
        unset($data['address']);

        $client = new Client();
        $client->fill($data);
        if (!$client->save()){
            throw new \Exception();
        }
        $client->code = $client->id;
        if (!$client->save()){
            throw new \Exception();
        }

        if (isset($request->package_id)) {
            if (!empty($request->package_id)) {

                foreach ($request->package_id as $key => $package) {
                    $client_package = new ClientPackage();

                    $client_package->client_id    = $client->id;
                    $client_package->package_id   = $package;
                    $client_package->name = Package::select('name')->where('id',$package)->first();
                    $client_package->name = $client_package->name->name;
                    $client_package->cost = $request->package_extra[$key];

                    if (!$client_package->save()) {
                        throw new \Exception();
                    }
                }
            }
        }

        if (isset($request->address) && !empty($request->address) ) {
            foreach ($request->address as $address) {

                if(isset($address['address']) && $address['address'] != null )
                {
                    $client_address = new ClientAddress();
                    $client_address->fill($address);
                    $client_address->client_id = $client->id;

                    if (!$client_address->save()) {
                        throw new \Exception();
                    }
                }
            }
        }

        // $client->addFromMediaLibraryRequest($request->image)->toMediaCollection('avatar');
        if ($request->hasFile('image')) {
            // Delete old avatar if exists
            if ($client->avatar) {
                Storage::disk('public')->delete($client->avatar);
            }

            // Store new avatar
            $imagePath = $request->file('image')->store('avatars', 'public');
            $client->avatar = $imagePath;
            $client->save();
        }
        event(new AddClient($client));
        return redirect()->route('clients.index')->with(['message_alert' => __('cargo::messages.created')]);

    }

    public function register()
    {
        $branches = Branch::where('is_archived',0)->get();
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::'.$adminTheme.'.pages.clients.register')->with(['branches' => $branches]);
    }

    public function registerStore(Request $request , $calc = false)
    {
        try {
            // Debug: Log all request data
            //Log::info('Registration attempt - Request data:', $request->all());
            
            $validator = \Validator::make($request->all(), [
                'name' => 'required|string|min:3|max:50',
                'email' => 'required|max:50|email|unique:users,email',
                'password' => 'required|string|min:6',
                'responsible_mobile' => 'required|digits_between:8,20',
                'country_code' => 'required',
                'responsible_name' => 'required|string|min:3|max:50',
                'national_id'   => 'required',
                'branch_id' => 'required',
                'terms_conditions' => 'required',
            ]);
            
            if ($validator->fails()) {
                \Log::error('Registration validation failed:', $validator->errors()->toArray());
                return back()->withErrors($validator)->withInput();
            }

            $data = $request->only(['name', 'email', 'password', 'responsible_mobile', 'country_code' , 'responsible_name','national_id','branch_id']);

            // dd($data);
            // dd($request->all()); 
            // Check for similar accounts before proceeding with registration
            $nameParts = explode(' ', trim($data['name']));
            $email = $data['email'];
            $similarUsers = User::where(function($query) use ($nameParts, $email) {
                // foreach ($nameParts as $part) {
                //     if (strlen($part) > 2) {
                //         $query->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($part) . '%']);
                //     }
                // }
            
                if (!empty($email)) {
                    $query->orWhereRaw('LOWER(email) = ?', [strtolower($email)]);
                }
            })
            ->where('role', 4)
            ->get();

            $similarAccounts = Client::whereIn('user_id', $similarUsers->pluck('id'))->get();
            // dd($similarAccounts);
            if (!$similarAccounts->isEmpty()) {
                // Store the registration data in session
                session(['pending_registration' => $data]);
                
                // save $similarAccounts in users table with user as foregn key unary relation alter add a unary foreign attribute key called parent_id

                // Redirect to claim accounts page
                return redirect()->route('clients.claim-accounts')->with([
                    'similar_accounts' => $similarAccounts,
                    'registration_data' => $data
                ]);
            }

            // If no similar accounts found, proceed with registration
            $Userdata['name']     = $data['name'];
            $Userdata['email']    = $data['email'];
            $Userdata['password'] = $data['password'];
            $Userdata['role']     = 4;

            $userRegistrationHelper = new UserRegistrationHelper();
            $response = $userRegistrationHelper->NewUser($Userdata);
            if(!$response['success']){
                throw new \Exception($response['error_msg']);
            }

            $data['code']    = 0;
            $data['user_id'] = $response['user']['id'];
            $data['created_by'] = auth()->check() ? auth()->id() : null;
            unset($data['password']);
            unset($data['address']);

            $client = new Client();
            $client->fill($data);
            if (!$client->save()){
                throw new \Exception();
            }
            $client->code = $client->id;
            if (!$client->save()){
                throw new \Exception();
            }
            // event(new AddClient($client));
            Auth::loginUsingId($client->user_id);

            // dd($client);
            // Send Welcome Email
            // Mail::to($client->email)->send(new WelcomeMail($client));
            // if($calc)
            // {
            //     return $client;
            // }

            return redirect()->route('admin.dashboard');
        } catch (\Throwable $th) {
            report($th);

            $message = __('An unexpected error occurred while creating your account. Please try again.');
            if (config('app.debug')) {
                $message = $th->getMessage();
            }

            return redirect()->back()->withInput()->with('error', $message);
        }
    }

    public function claimExistingAccount($data){
        // dd($data);
        // Split the name into parts
        $nameParts = explode(' ', trim($data['name']));
        $email = $data['email'];

        // dd($similarUsers);
        // Search for similar accounts by name in both User and Client tables
        $similarUsers = User::where(function($query) use ($nameParts, $email) {
            foreach ($nameParts as $part) {
                if (strlen($part) > 2) {
                    $query->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($part) . '%']);
                }
            }
        
            if (!empty($email)) {
                $query->orWhereRaw('LOWER(email) = ?', [strtolower($email)]);
            }
        })
        ->where('role', 4)
        ->get();
        
        // $similarUsers = User::where(function($query) use ($nameParts) {
        //     foreach ($nameParts as $part) {
        //         if (strlen($part) > 2) { // Only search for parts longer than 2 characters
        //             $query->orWhere(function($q) use ($part) {
        //                 // Search for exact part match
        //                 $q->where('name', 'like', '%' . $part . '%')
        //                   // Search for part at start of name
        //                   ->orWhere('name', 'like', $part . '%')
        //                   // Search for part at end of name
        //                   ->orWhere('name', 'like', '%' . $part)
        //                   // Search for part with space before
        //                   ->orWhere('name', 'like', '% ' . $part . '%')
        //                   // Search for part with space after
        //                   ->orWhere('name', 'like', '%' . $part . ' %');
        //             });
        //         }
        //     }
        // })->where('role', 4)->get();

        // Get the corresponding clients for these users
        $similarAccounts = Client::whereIn('user_id', $similarUsers->pluck('id'))->get();

        // Store the registration data in session for later use
        session(['pending_registration' => $data]);
        
        // Always redirect to claim accounts page, even if no similar accounts found
        return redirect()->route('clients.claim-accounts')->with([
            'similar_accounts' => $similarAccounts,
            'registration_data' => $data
        ]);
    }

    public function showClaimAccounts()
    {
        if (!session()->has('pending_registration')) {
            return redirect()->route('clients.register');
        }

        $similarAccounts = session('similar_accounts');
        $registrationData = session('pending_registration');
        
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::'.$adminTheme.'.pages.clients.claim-accounts', [
            'similar_accounts' => $similarAccounts,
            'registration_data' => $registrationData
        ]);
    }

    public function processClaim(Request $request)
    {
        if ($request->has('claim_account')) {
            // User wants to claim an existing account
            $accountId = $request->claim_account;
            $account = Client::findOrFail($accountId);
            $registrationData = session('pending_registration');
            // Update the account with new information
            $account->update([
                'name' => $registrationData['name'],
                'email' => $registrationData['email'],
                'responsible_mobile' => $registrationData['responsible_mobile'],
                'country_code' => $registrationData['country_code'],
                'responsible_name' => $registrationData['responsible_name'],
                'national_id' => $registrationData['national_id'],
                'branch_id' => $registrationData['branch_id']
            ]);
            // Update the password for the related user
            $user = \App\Models\User::findOrFail($account->user_id);
            $user->password = Hash::make($registrationData['password']);
            $user->save();
            // Log the user in immediately
            Auth::loginUsingId($user->id);
            // Clear the session
            session()->forget(['pending_registration', 'similar_accounts']);

            // Update parent_id for all similar accounts' users
            if (session()->has('similar_accounts')) {
                foreach (session('similar_accounts') as $similarAccount) {
                    $similarUser = \App\Models\User::find($similarAccount->user_id);
                    if ($similarUser && $similarUser->id !== $user->id) {
                        $similarUser->parent_id = $user->id;
                        $similarUser->save();
                    }
                }
            }

            return redirect()->route('admin.dashboard')
                ->with('message_alert', __('cargo::messages.account_claimed'));
        } else {
            // User wants to create a new account
            $registrationData = session('pending_registration');
            
            // Clear the session
            session()->forget(['pending_registration', 'similar_accounts']);

            // Create new user
            $Userdata['name'] = $registrationData['name'];
            $Userdata['email'] = $registrationData['email'];
            $Userdata['password'] = $registrationData['password'];
            $Userdata['role'] = 4;

            $userRegistrationHelper = new UserRegistrationHelper();
            $response = $userRegistrationHelper->NewUser($Userdata);
            
            if (!$response['success']) {
                throw new \Exception($response['error_msg']);
            }

            // Create new client
            $registrationData['code'] = 0;
            $registrationData['user_id'] = $response['user']['id'];
            $registrationData['created_by'] = auth()->check() ? auth()->id() : null;
            unset($registrationData['password']);

            $client = new Client();
            $client->fill($registrationData);
            if (!$client->save()) {
                throw new \Exception();
            }
            
            $client->code = $client->id;
            if (!$client->save()) {
                throw new \Exception();
            }

            event(new AddClient($client));
            Auth::loginUsingId($client->user_id);

            // Send Welcome Email
            // Mail::to($client->email)->send(new WelcomeMail($client));

            return redirect()->route('admin.dashboard')
                ->with('message_alert', __('cargo::messages.created'));
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('view.profile_details')
            ],
        ]);
        $user = Client::findOrFail($id);
        $shipments = Shipment::where('client_id', $id)->count();
        $client_address = ClientAddress::where('client_id',$id)->get();
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::'.$adminTheme.'.pages.clients.show')->with(['model' => $user, 'shipments' => $shipments,'client_address' => $client_address]);
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.edit_client'),
            ],
        ]);

        if(auth()->user()->role == 3){
            $branches = Branch::where('is_archived',0)->where('user_id',auth()->user()->id)->get();
        }else{
            $branches = Branch::where('is_archived',0)->get();
        }
        $client = Client::findOrFail($id);
        $packages = $client->packages;
        $adminTheme = env('ADMIN_THEME', 'adminLte');return view('cargo::'.$adminTheme.'.pages.clients.edit')->with(['model' => $client, 'branches' => $branches, 'packages' => $packages]);
    }

    public function profile($id)
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.edit_profile'),
            ],
        ]);

        if(auth()->user()->role == 3){
            $branches = Branch::where('is_archived',0)->where('user_id',auth()->user()->id)->get();
        }else{
            $branches = Branch::where('is_archived',0)->get();
        }


        $client = Client::findOrFail($id);
        $packages = $client->packages;
        $adminTheme = env('ADMIN_THEME', 'adminLte');return view('cargo::'.$adminTheme.'.pages.clients.edit-profile')->with(['model' => $client, 'branches' => $branches, 'packages' => $packages]);

    }


    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */

    public  function manageAddress(ClientAddressDataTable $dataTable)
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' =>  __('cargo::view.manage_address') ,
            ],
        ]);
        $client = Client::where('user_id', auth()->user()->id)->first();
        $client_addresses = ClientAddress::where('client_id',$client->id)->get();

        $data_with = ['client_addresses'=> $client_addresses,'model'=>$client];
        $share_data = array_merge(get_class_vars(ClientAddressDataTable::class), $data_with);
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return $dataTable->render('cargo::'.$adminTheme.'.pages.clients.manage_address' , $share_data);

    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public  function manageAddressUpdata(Request $request) {

        ClientAddress::where('client_id',$request->client_id)->update(['is_default'=>0]);
        ClientAddress::where('id', $request->address_id )->update(['is_default'=>1]);


        return redirect()->route('clients.manage-address')->with(['message_alert' => __('cargo::messages.update')]);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(ClientRequest $request, $id)
    {
        
        if (env('DEMO_MODE') == 'On') {
            return redirect()->back()->with(['error_message_alert' => __('view.demo_mode')]);
        }

        $client = Client::findOrFail($id);

        $data = $request->only(['def_mile_cost','def_return_mile_cost','def_mile_cost_gram','def_return_mile_cost_gram','def_return_cost_gram','def_insurance_gram','def_tax_gram','def_shipping_cost_gram','def_return_cost','def_insurance','def_tax','def_shipping_cost','supply_cost','pickup_cost','how_know_us','package_access_options','follow_up_mobile','follow_up_country_code' , 'follow_up_name','name', 'email', 'password', 'responsible_mobile','country_code' ,'responsible_name','national_id','branch_id','address']);

        $Userdata['name']     = $data['name'];
        $Userdata['email']    = $data['email'];
        $Userdata['password'] = $data['password'];
        $userRegistrationHelper = new UserRegistrationHelper($client->user_id);
		$response = $userRegistrationHelper->NewUser($Userdata);
        if(!$response['success']){
            throw new \Exception($response['error_msg']);
        }

        $data['updated_by'] = auth()->check() ? auth()->id() : null;
        unset($data['password']);
        unset($data['address']);

        $client->fill($data);
        if (!$client->save()){
            throw new \Exception();
        }

        if (isset($request->package_id)) {
            if (!empty($request->package_id)) {
                foreach ($request->package_id as $key => $package) {
                    $client_package = ClientPackage::where('client_id',$client->id)->where('package_id' , $package)->first();
                    if($client_package){
                        $client_package->cost = $request->package_extra[$key];
                    }else{
                        $client_package = new ClientPackage();
                        $client_package->client_id    = $client->id;
                        $client_package->package_id   = $package;
                        $client_package->name = Package::select('name')->where('id',$package)->first();
                        $client_package->name = $client_package->name->name;
                        $client_package->cost = $request->package_extra[$key];
                    }
                    if (!$client_package->save()) {
                        throw new \Exception();
                    }
                }
            }
        }

        $client_addresses = ClientAddress::where('client_id',$client->id)->get();
        if (isset($request->address) && !empty($request->address) ) {


            if (!is_array($request->address)){
                $address = $request->address;
                $request->address = array($address);
            }

            foreach ($request->address as $address) {

                if(isset($address['address']) && $address['address'] != null )
                {
                    $client_address = new ClientAddress();
                    $client_address->fill($address);
                    $client_address->client_id = $client->id;

                    if (!$client_address->save()) {
                        throw new \Exception();
                    }
                }
            }
        }
        $client_addresses->each->delete();

        // $client->syncFromMediaLibraryRequest($request->image)->toMediaCollection('avatar');
        if ($request->hasFile('image')) {
            // Delete old avatar if exists
            if ($client->avatar) {
                Storage::disk('public')->delete($client->avatar);
            }

            // Store new avatar
            $imagePath = $request->file('image')->store('avatars', 'public');
            $client->avatar = $imagePath;
            $client->save();
        }
        return redirect()->back()->with(['message_alert' => __('cargo::messages.saved')]);
    }

    public function ajaxGetClientAddresses(Request $request)
    {
        $client_id = $request->client_id;
        $addresses = ClientAddress::where('client_id', $client_id)->get();
        return response()->json($addresses);
    }


    public function newAddressStore(ClientAddressRequest $request) {

        foreach ($request->address as $key => $item) {
            $new_item = new ClientAddress ;
            $new_item->client_id = $request->client_id;
            $new_item = $new_item->fill($item);
            if(!$new_item->save()) { throw new \Exception(); }
        }


        DB::commit();
        return redirect()->route('clients.manage-address')->with(['message_alert' => __('cargo::messages.saved')]);
    }

    public function addNewAddress(Request  $request , $calc = false , $return_view = false)
    {
        $client_address = new ClientAddress();
        $client_address->client_id                 = $request->client_id;
        $client_address->address                   = $request->address;
        $client_address->country_id                = $request->country;
        $client_address->state_id                  = $request->state;

        if(isset($request->area)){
            $client_address->area_id               = $request->area;
        }

        $googleSettings = resolve(\app\Models\GoogleSettings::class)->toArray();
        $googleMap = json_decode($googleSettings['google_map'], true);
        if($googleMap){
            $client_address->client_street_address_map = $request->client_street_address_map ?? '';
            $client_address->client_lat                = $request->client_lat ?? '';
            $client_address->client_lng                = $request->client_lng ?? '';
            $client_address->client_url                = $request->client_url ?? '';
        }

        if (!$client_address->save()) {
            throw new \Exception();
        }

        if($calc)
        {
            return $client_address;
        }

        $client_id  = $request->client_id;

        if($return_view)
        {
            $addresses = ClientAddress::where('client_id', $client_id)->where('is_archived',0)->orderBy('id','DESC')->paginate(15);
            return $addresses;
        }
        $addresses = ClientAddress::where('client_id', $client_id)->where('is_archived',0)->get();
        return response()->json($addresses);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        if (env('DEMO_MODE') == 'On') {
            return redirect()->back()->with(['error_message_alert' => __('view.demo_mode')]);
        }

        $client = Client::findOrFail($id);
        $client_addresses = ClientAddress::where('client_id',$client->id)->get();
        $client_addresses->each->delete();
        User::destroy($client->user_id);
        Client::destroy($id);
        return response()->json(['message' => __('cargo::messages.deleted')]);
    }


    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function addressDelete($id)
    {
        try {
            $Client_address = ClientAddress::find($id);
            if (!$Client_address){
                return redirect()->route('clients.manage-address')->with(['message_alert' => __('cargo::messages.multi_deleted_failed')]);
            }
            $Client_address->delete();
            return redirect()->route('clients.manage-address')->with(['message_alert' => __('cargo::messages.deleted')]);
        } catch (\Exception $ex) {
            return redirect()->route('clients.manage-address')->with(['message_alert' => __('cargo::messages.something_wrong')]);
        }
    }


    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function addressEdit($id){

        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.address'),
            ],
        ]);

        $model = ClientAddress::findOrFail($id);
        $client = Client::where('user_id', auth()->user()->id)->first();

        if (!$model)
            return redirect()->route('clients.manage-address')->with(['message_alert' => __('cargo::messages.multi_deleted_failed')]);

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::'.$adminTheme.'.pages.clients.edit_address')->with(['model'=>$model , 'client'=>$client]);

    }


    public function addressUpdata(Request $request) {

        $data = $request->except('_token', 'id');
        ClientAddress::where('id', $request->id)
        ->update(
            $data
        );
        return redirect()->route('clients.manage-address')->with(['message_alert' => __('cargo::messages.update')]);

    }


    /**
     * Remove multi user from database.
     * @param Request $request
     * @return Renderable
     */
    public function multiDestroy(Request $request)
    {
        if (env('DEMO_MODE') == 'On') {
            return redirect()->back()->with(['error_message_alert' => __('view.demo_mode')]);
        }

        $ids = $request->ids;
        $clients_user_ids = Client::whereIn('id',$ids)->pluck('user_id');
        foreach($ids as $id){
            $client_addresses = ClientAddress::where('client_id',$id)->get();
            $client_addresses->each->delete();
        }
        User::destroy($clients_user_ids);
        Client::destroy($ids);
        return response()->json(['message' => __('cargo::messages.multi_deleted')]);
    }

    public function addNewAddressAPI(Request $request)
    {
        try{
            $request->validate([
                'client_id' => 'required',
                'address'   => 'required',
                'country'   => 'required',
                'state'     => 'required',
                'area'      => 'required',
            ]);

            $apihelper = new ApiHelper();
            $user = $apihelper->checkUser($request);

            if($user){
                $addresses = $this->addNewAddress($request);
                return $addresses;
            }else{
                return response()->json(['message' => 'Not Authorized']);
            }
        }catch(\Exception $e){
			DB::rollback();
			print_r($e->getMessage());
			exit;
		}
    }

    public function getAddresses(Request $request)
    {
        try{
            if($request->is('api/*')){

                $apihelper = new ApiHelper();
                $user = $apihelper->checkUser($request);

                $request->validate([
                    'client_id' => 'required',
                ]);

                if($user){
                    $addresses = ClientAddress::where('client_id', $request->client_id)->get();
                    return response()->json($addresses);
                }else{
                    return response()->json(['message' => 'Not Authorized']);
                }

            }else {
                $addresses = ClientAddress::where('client_id', Auth::user()->userClient->client_id)->where('is_archived',0)->orderBy('id','DESC')->paginate(15);
                return view('backend.clients.index-addresses',compact(['addresses']));
            }
        }catch(\Exception $e){
			DB::rollback();
			print_r($e->getMessage());
			exit;
		}
    }

    public function clientsReport(ClientsDataTable $dataTable)
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.clients_report')
            ]
        ]);
        $data_with = [];
        $share_data = array_merge(get_class_vars(ClientsDataTable::class), $data_with);
        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return $dataTable->render('cargo::'.$adminTheme.'.pages.clients.report', $share_data);
    }

    public function getOneAddress(Request $request)
    {
        $address_id = $_GET['address_id'];
        $address    = ClientAddress::where('id', $address_id)->get();
        return response()->json($address);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function storeInShipmint(ClientRequest $request)
    {
        $data = $request->only(['def_mile_cost','def_return_mile_cost','def_mile_cost_gram','def_return_mile_cost_gram','def_return_cost_gram','def_insurance_gram','def_tax_gram','def_shipping_cost_gram','def_return_cost','def_insurance','def_tax','def_shipping_cost','supply_cost','pickup_cost','how_know_us','package_access_options','follow_up_mobile','follow_up_country_code' , 'follow_up_name','name', 'email', 'password', 'responsible_mobile', 'country_code' ,'responsible_name','national_id','branch_id','address']);

        $Userdata['name']     = $data['name'];
        $Userdata['email']    = $data['email'];
        $Userdata['password'] = $data['password'];
        $Userdata['role']     = 4;
        $userRegistrationHelper = new UserRegistrationHelper();
		$response = $userRegistrationHelper->NewUser($Userdata);
        if(!$response['success']){
            throw new \Exception($response['error_msg']);
        }

        $data['code']    = 0;
        $data['user_id'] = $response['user']['id'];
        $data['created_by'] = auth()->check() ? auth()->id() : null;
        unset($data['password']);
        unset($data['address']);

        $client = new Client();
        $client->fill($data);
        if (!$client->save()){
            throw new \Exception();
        }
        $client->code = $client->id;
        if (!$client->save()){
            throw new \Exception();
        }

        if (isset($request->package_id)) {
            if (!empty($request->package_id)) {

                foreach ($request->package_id as $key => $package) {
                    $client_package = new ClientPackage();

                    $client_package->client_id    = $client->id;
                    $client_package->package_id   = $package;
                    $client_package->name = Package::select('name')->where('id',$package)->first();
                    $client_package->name = $client_package->name->name;
                    $client_package->cost = $request->package_extra[$key];

                    if (!$client_package->save()) {
                        throw new \Exception();
                    }
                }
            }
        }

        if (isset($request->address) && !empty($request->address) ) {
            foreach ($request->address as $address) {

                if(isset($address['address']) && $address['address'] != null )
                {
                    $client_address = new ClientAddress();
                    $client_address->fill($address);
                    $client_address->client_id = $client->id;

                    if (!$client_address->save()) {
                        throw new \Exception();
                    }
                }
            }
        }

        // $client->addFromMediaLibraryRequest($request->image)->toMediaCollection('avatar');
        if ($request->hasFile('image')) {
            // Delete old avatar if exists
            if ($client->avatar) {
                Storage::disk('public')->delete($client->avatar);
            }

            // Store new avatar
            $imagePath = $request->file('image')->store('avatars', 'public');
            $client->avatar = $imagePath;
            $client->save();
        }
        event(new AddClient($client));

        return response()->json([
            'success' => true,
            'message' => __('cargo::messages.created'),
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'responsible_mobile' => $client->responsible_mobile,
                'country_code' => $client->country_code,
            ],
            'client_address' => $client_address ,
        ]);

    }

    /**
     * Handle AJAX shipment schedule request from client dashboard modal.
     */
    public function scheduleShipmentRequest(Request $request)
    {
        $validated = $request->validate([
            'client_type' => 'required|string',
            'full_names' => 'required|string',
            'route' => 'required|string',
            'goods_type' => 'required|string',
            'has_supplier' => 'required|string',
            'need_supplier_help' => 'nullable|string',
        ]);

        // Compose email content
        $mailData = [
            'Client Type' => $validated['client_type'],
            'Full Names' => $validated['full_names'],
            'Route' => $validated['route'],
            'Goods Type' => $validated['goods_type'],
            'Has Supplier' => $validated['has_supplier'],
        ];
        if (isset($validated['need_supplier_help'])) {
            $mailData['Needs Supplier Help'] = $validated['need_supplier_help'];
        }
        // Add shipping rates info if has_supplier is Yes
        if ($validated['has_supplier'] === 'Yes') {
            $mailData['Shipping Rates'] = "Air Cargo: $5/kg, Sea Cargo: $2/kg";
        }

        $adminEmail = env('MAIL_ADMIN', config('mail.from.address'));
        $subject = 'New Shipment Schedule Request';
        $body = "A new shipment schedule request has been submitted:\n\n";
        foreach ($mailData as $key => $value) {
            $body .= "$key: $value\n";
        }

        \Mail::raw($body, function($message) use ($adminEmail, $subject) {
            $message->to($adminEmail)
                    ->subject($subject);
        });

        return response()->json(['success' => true, 'message' => 'Request submitted and emailed to admin.']);
    }

}
