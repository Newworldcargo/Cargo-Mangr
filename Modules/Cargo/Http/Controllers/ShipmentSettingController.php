<?php

namespace Modules\Cargo\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\Cargo\Entities\ShipmentSetting;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Services\MobilePricingSettings;
use Modules\Acl\Repositories\AclRepository;

class ShipmentSettingController extends Controller
{
    private $aclRepo;
    
    public function __construct(AclRepository $aclRepository)
    {
        $this->aclRepo = $aclRepository;
        // check on permissions
        $this->middleware('user_role:1|0');
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $adminTheme = env('ADMIN_THEME', 'adminLte');return view('cargo::'.$adminTheme.'.index');
    }

    public function settings()
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.shipping_settings'),
            ],
        ]);

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view($adminTheme.'.pages.settings', ['fields' => ShipmentSetting::fields()]);
    }
    public function storeSettings(ShipmentSetting $settings,Request $request)
    {
        
        foreach(ShipmentSetting::fields() as $field_key => $field){
            if (ShipmentSetting::where('key',$field_key)->count() == 0) {
                $settings = new ShipmentSetting();
                $settings->key   = $field_key;
                $settings->value = isset($request->fields[$field_key]) ? $request->fields[$field_key] : '';
            }else{
                $settings = ShipmentSetting::where('key', $field_key)->first();
                $settings->value = isset($request->fields[$field_key]) ? $request->fields[$field_key] : '';
            }
            $settings->save();
        }
        
        
        return redirect()->back()->with(['message_alert' => __('cargo::messages.saved')]);
    }

    public function feesSettings(MobilePricingSettings $mobilePricing)
    {
        breadcrumb([
            [
                'name' => __('cargo::view.dashboard'),
                'path' => fr_route('admin.dashboard')
            ],
            [
                'name' => __('cargo::view.shipping_rates'),
            ],
        ]);

        $branches = Branch::where('is_archived', 0)->orderBy('name')->get(['id', 'name', 'address']);
        $routeValues = [];
        foreach ($branches as $origin) {
            foreach ($branches as $destination) {
                if ($origin->id === $destination->id) continue;
                $routeValues['intercity'][$origin->id][$destination->id] = $mobilePricing->routeValues('intercity', $origin->id, $destination->id);
                foreach (['air', 'sea'] as $mode) {
                    $routeValues['import'][$origin->id][$destination->id][$mode] = $mobilePricing->routeValues('import', $origin->id, $destination->id, $mode);
                }
            }
        }

        $adminTheme = env('ADMIN_THEME', 'adminLte');
        return view('cargo::'.$adminTheme.'.pages.shipment-settings.fees-settings', [
            'branches' => $branches,
            'mobilePricingValues' => $mobilePricing->globalValues(),
            'mobileRouteValues' => $routeValues,
        ]);
    }

    public function storeFeesSettings(Request $request)
    {
        foreach ($request->Setting as $key => $value) {
            if (ShipmentSetting::where('key',$key)->count() == 0) {
                $set = new ShipmentSetting();
                $set->key = $key;
                $set->value = $value;
                $set->save();
            } else {
                $set = ShipmentSetting::where('key', $key)->first();
                $set->value = $value;
                $set->save();
            }
        }

        return redirect()->back()->with(['message_alert' => __('cargo::messages.saved')]);
    }

    public function storeMobilePricing(Request $request, MobilePricingSettings $mobilePricing)
    {
        $request->validate([
            'currency' => ['required', 'string', 'in:USD'],
            'pricing' => ['required', 'array'],
            'pricing.*' => ['nullable', 'numeric', 'min:0'],
            'intercity_routes' => ['nullable', 'array'],
            'import_routes' => ['nullable', 'array'],
        ]);

        $activeBranchIds = Branch::where('is_archived', 0)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $settings = ['mobile_pricing_currency' => strtoupper($request->currency)];
        foreach (MobilePricingSettings::GLOBAL_FIELDS as $field => $key) {
            $settings[$key] = $request->input("pricing.{$field}", '');
        }

        foreach ((array) $request->input('intercity_routes', []) as $originId => $destinations) {
            foreach ((array) $destinations as $destinationId => $values) {
                $this->appendRouteSettings($settings, $mobilePricing, 'intercity', (int) $originId, (int) $destinationId, (array) $values, $activeBranchIds);
            }
        }
        foreach ((array) $request->input('import_routes', []) as $originId => $destinations) {
            foreach ((array) $destinations as $destinationId => $modes) {
                foreach (['air', 'sea'] as $mode) {
                    $this->appendRouteSettings($settings, $mobilePricing, 'import', (int) $originId, (int) $destinationId, (array) ($modes[$mode] ?? []), $activeBranchIds, $mode);
                }
            }
        }

        foreach ($settings as $key => $value) {
            ShipmentSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return redirect()->to(route('shipments.settings.fees') . '#mobile-pricing')->with(['message_alert' => __('cargo::messages.saved')]);
    }

    private function appendRouteSettings(array &$settings, MobilePricingSettings $mobilePricing, string $service, int $originId, int $destinationId, array $values, array $activeBranchIds, ?string $mode = null): void
    {
        if ($originId === $destinationId || !in_array($originId, $activeBranchIds, true) || !in_array($destinationId, $activeBranchIds, true)) return;
        $enabled = isset($values['enabled']) ? '1' : '0';
        $fields = $service === 'import'
            ? ['base_fee', 'per_kg', 'fragile_fee', 'container_fee']
            : ['base_fee', 'per_km', 'per_kg', 'fragile_fee', 'container_fee'];
        if ($enabled === '1' && (!isset($values['base_fee']) || !is_numeric($values['base_fee']) || (float) $values['base_fee'] <= 0)) {
            throw ValidationException::withMessages(['routes' => 'Every enabled mobile pricing route requires a positive base fee.']);
        }
        $settings[$mobilePricing->routeKey($service, $originId, $destinationId, 'enabled', $mode)] = $enabled;
        foreach ($fields as $field) {
            $value = $values[$field] ?? '';
            if ($value !== '' && (!is_numeric($value) || (float) $value < 0)) {
                throw ValidationException::withMessages(['routes' => 'Mobile pricing values must be zero or greater.']);
            }
            $settings[$mobilePricing->routeKey($service, $originId, $destinationId, $field, $mode)] = $value;
        }
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        $adminTheme = env('ADMIN_THEME', 'adminLte');return view('cargo::'.$adminTheme.'.create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $adminTheme = env('ADMIN_THEME', 'adminLte');return view('cargo::'.$adminTheme.'.show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $adminTheme = env('ADMIN_THEME', 'adminLte');return view('cargo::'.$adminTheme.'.edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }
}
