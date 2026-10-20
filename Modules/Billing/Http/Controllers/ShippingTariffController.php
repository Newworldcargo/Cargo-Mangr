<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Services\{SalesConfiguration, InvoiceAmounts};
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Services\BranchAccessService;

class ShippingTariffController extends Controller
{
    private function allowed(Request $request): void
    {
        abort_unless(!in_array((int)$request->user()->role,[4,5],true) &&
            ((int)$request->user()->role === 1 || $request->user()->can('manage-billing-settings')),403);
    }

    public function index(Request $request)
    {
        $this->allowed($request);
        $branches = Branch::where('is_archived',0)->whereIn('id',app(BranchAccessService::class)->branchIdsFor($request->user()))->orderBy('name')->get();
        $id = (int)$request->input('branch_id',$branches->first()?->id);
        app(SalesConfiguration::class)->branch($request->user(),$id);
        $services = DB::table('billing_services')->where('branch_id',$id)->orderBy('name')->get();
        $routes = Branch::where('is_archived',0)->orderBy('name')->get(['id','name']);
        $tariffs = DB::table('billing_shipping_tariffs')->where('branch_id',$id)->get()->keyBy('service_id');
        $selected = $tariffs->get((int)$request->input('service_id'));
        $configuration = $selected ? json_decode($selected->configuration,true) : [];
        return view('billing::shipping-tariffs',compact('branches','id','services','routes','tariffs','selected','configuration'));
    }

    public function save(Request $request)
    {
        $this->allowed($request);
        $data = $request->validate([
            'branch_id'=>'required|integer','service_id'=>'required|integer','revision'=>'required|integer|min:0',
            'currency'=>'required|in:USD,ZMW','unit'=>'required|in:kg,cbm,ton',
            'minimum'=>'required|string','rounding'=>'required|string','volume_below'=>'nullable|string',
            'effective_from'=>'required|date_format:Y-m-d','effective_until'=>'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'origin_id'=>'required|integer','destination_id'=>'required|integer|different:origin_id',
            'method'=>'required|in:air,sea,road','category'=>'required|in:general,small_parcels,special,motorbike,heavy',
            'transit_days'=>'nullable|integer|min:1|max:365','departure_days'=>'nullable|string|max:100',
            'duty'=>'required|in:included,excluded,unconfirmed','terms'=>'nullable|string|max:2000',
            'active'=>'required|boolean','booking_enabled'=>'required|boolean',
            'weight_basis'=>'required|in:actual,chargeable',
            'tiers'=>'required|array|min:1|max:30','tiers.*.from'=>'required|string','tiers.*.rate'=>'required|string',
        ]);
        $data+=['effective_until'=>null,'volume_below'=>null,'transit_days'=>null,'departure_days'=>null,'terms'=>null];
        app(SalesConfiguration::class)->branch($request->user(),(int)$data['branch_id']);
        abort_unless(DB::table('billing_services')->where('branch_id',$data['branch_id'])->where('id',$data['service_id'])->exists(),404);
        if (Branch::where('is_archived',0)->whereIn('id',[$data['origin_id'],$data['destination_id']])->count() !== 2 ||
            (int)$data['destination_id'] !== (int)$data['branch_id']) {
            throw ValidationException::withMessages(['destination_id'=>'Choose active route branches, with this billing branch as the destination.']);
        }
        try {
            $minimum=InvoiceAmounts::quantity($data['minimum']);
            InvoiceAmounts::quantity($data['rounding']);
            if (!empty($data['volume_below'])) {
                if (InvoiceAmounts::quantity($data['volume_below']) <= 0) throw new \DomainException('Volume limit must be greater than zero.');
            }
            $last=-1;
            foreach ($data['tiers'] as $tier) {
                $from=InvoiceAmounts::quantity($tier['from']);
                if ($from <= $last) throw new \DomainException('Quantity bands must be unique and entered in increasing order.');
                if (InvoiceAmounts::decimal($tier['rate']) <= 0) throw new \DomainException('Every band needs a rate greater than zero.');
                $last=$from;
            }
            if (InvoiceAmounts::quantity($data['tiers'][0]['from']) > $minimum) throw new \DomainException('The first band must cover the minimum chargeable quantity.');
            if ($data['booking_enabled'] && ($data['method']==='road' || $data['weight_basis']==='chargeable')) {
                throw new \DomainException('Automatic import pricing currently requires Air or Sea and actual measurements. Leave booking use off for chargeable-weight services pending volumetric rules.');
            }
            if ($data['category']==='small_parcels' && empty($data['volume_below'])) throw new \DomainException('Small-parcel services require a volume limit.');
        } catch (\DomainException $error) { throw ValidationException::withMessages(['tariff'=>$error->getMessage()]); }
        $branchId=(int)$data['branch_id']; $serviceId=(int)$data['service_id']; $revision=(int)$data['revision'];
        unset($data['branch_id'],$data['service_id'],$data['revision']);
        DB::transaction(function () use ($request,$branchId,$serviceId,$revision,$data) {
            // Serialize tariff edits and duplicate route checks within a billing branch.
            DB::table('branches')->where('id',$branchId)->lockForUpdate()->first();
            $row=DB::table('billing_shipping_tariffs')->where('service_id',$serviceId)->lockForUpdate()->first();
            if ((int)($row->revision ?? 0) !== $revision) throw ValidationException::withMessages(['tariff'=>'This rate was changed by another user. Reload it before saving.']);
            if ($data['active'] && $data['booking_enabled']) {
                foreach(DB::table('billing_shipping_tariffs')->where('branch_id',$branchId)->where('service_id','!=',$serviceId)->get() as $other) {
                    $config=json_decode($other->configuration,true);
                    $same=$config['active'] && $config['booking_enabled'];
                    foreach(['origin_id','destination_id','method','category'] as $key) $same=$same && (string)$config[$key]===(string)$data[$key];
                    $overlap=$data['effective_from'] <= ($config['effective_until'] ?: '9999-12-31') &&
                        $config['effective_from'] <= ($data['effective_until'] ?: '9999-12-31');
                    if($same && $overlap) throw ValidationException::withMessages(['tariff'=>'Another booking rate covers this route, cargo category and date range. Use non-overlapping dates or a different category.']);
                }
            }
            $values=['configuration'=>json_encode($data),'revision'=>$revision+1,'updated_at'=>now()];
            if($row) DB::table('billing_shipping_tariffs')->where('id',$row->id)->update($values);
            else $values['id']=DB::table('billing_shipping_tariffs')->insertGetId($values+['branch_id'=>$branchId,'service_id'=>$serviceId,'created_at'=>now()]);
            \App\Models\AuditLog::create(['user_id'=>$request->user()->id,'event'=>'shipping_tariff_updated',
                'auditable_type'=>'billing_shipping_tariffs','auditable_id'=>$row->id ?? $values['id'],
                'description'=>'Shipping rate configuration updated.','old_values'=>$row?(array)$row:[],
                'new_values'=>$values]);
        });
        return redirect()->route('billing.shipping-tariffs',['branch_id'=>$branchId,'service_id'=>$serviceId])->with('success','Shipping rate saved.');
    }
}
