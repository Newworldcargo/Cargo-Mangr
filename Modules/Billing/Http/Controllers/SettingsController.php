<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Services\{SalesConfiguration, InvoiceAmounts};
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Services\BranchAccessService;

class SettingsController extends Controller
{
    private function allowed(Request $request): void
    {
        abort_unless(!in_array((int)$request->user()->role,[4,5],true) && ((int)$request->user()->role === 1 || $request->user()->can('manage-billing-settings')),403);
    }

    public function index(Request $request)
    {
        $this->allowed($request);
        $branches = Branch::where('is_archived',0)->whereIn('id',app(BranchAccessService::class)->branchIdsFor($request->user()))->orderBy('name')->get();
        $id = (int)$request->input('branch_id', $branches->first()?->id);
        abort_unless($branches->contains('id',$id),403);
        $config = app(SalesConfiguration::class)->catalogue($id);
        $taxes = DB::table('billing_tax_rates')->where('branch_id',$id)->orderBy('label')->get();
        $services = DB::table('billing_services')->where('branch_id',$id)->orderBy('name')->get();
        return view('billing::settings', compact('branches','id','config','taxes','services'));
    }

    public function save(Request $request, string $section)
    {
        $this->allowed($request);
        $request->validate(['branch_id'=>'required|integer','id'=>'nullable|integer']);
        $branch = app(SalesConfiguration::class)->branch($request->user(), (int)$request->branch_id);
        $rules = [
            'profile'=>['legal_name'=>'required|string|max:160','address'=>'required|string|max:1000','tpin'=>'required_if:vat_status,registered|nullable|string|max:40','vat_status'=>'required|in:registered,not_registered','payment_instructions'=>'nullable|string|max:2000'],
            'tax'=>['label'=>'required|string|max:100','category'=>'required|in:standard,zero_rated,exempt,non_vat','rate'=>'required|string|max:6','effective_from'=>'required|date_format:Y-m-d','effective_until'=>'nullable|date_format:Y-m-d|after_or_equal:effective_from','active'=>'required|boolean'],
            'service'=>['name'=>'required|string|max:160','unit'=>'required|in:'.implode(',',SalesConfiguration::UNITS),'price'=>'required|string|max:20','tax_id'=>'required_if:active,1|nullable|integer','tax_mode'=>'required|in:exclusive,inclusive','active'=>'required|boolean'],
        ];
        abort_unless(isset($rules[$section]),404);
        $data = $request->validate($rules[$section]);
        try {
            if ($section === 'tax') {
                $data['basis_points'] = InvoiceAmounts::decimal($data['rate'],10000); unset($data['rate']);
                if (($data['category'] === 'standard') !== ($data['basis_points'] > 0)) throw new \DomainException('Standard tax needs a positive rate; zero-rated, exempt and non-VAT categories require a zero rate.');
            }
            if ($section === 'service') {
                $data['price_minor'] = InvoiceAmounts::decimal($data['price']); unset($data['price']);
                $data['currency'] = app(BranchAccessService::class)->currencyFor(null,$branch,true);
                $data['tax_id']=$data['tax_id']??null;
                if (($data['active'] || $data['tax_id']!==null) && !DB::table('billing_tax_rates')->where('branch_id',$branch->id)->where('active',true)->where('id',$data['tax_id'])->exists()) throw new \DomainException('Choose an active tax category for this branch.');
            }
        } catch (\DomainException $error) { throw ValidationException::withMessages(['settings'=>$error->getMessage()]); }
        $table = ['profile'=>'billing_profiles','tax'=>'billing_tax_rates','service'=>'billing_services'][$section];
        DB::transaction(function () use ($request,$branch,$data,$table) {
            $query = DB::table($table)->where('branch_id',$branch->id);
            if ($table !== 'billing_profiles') $query->where('id',$request->input('id',0));
            $old = $query->lockForUpdate()->first();
            if ($request->filled('id') && !$old) abort(404);
            if ($old) { $query->update($data+['updated_at'=>now()]); $id=$old->id; }
            else $id=DB::table($table)->insertGetId($data+['branch_id'=>$branch->id,'created_at'=>now(),'updated_at'=>now()]);
            \App\Models\AuditLog::create(['user_id'=>$request->user()->id,'event'=>'sales_settings_updated','auditable_type'=>$table,'auditable_id'=>$id,
                'description'=>'Sales configuration updated.','old_values'=>(array)$old,'new_values'=>$data]);
        });
        return redirect()->route('billing.settings',['branch_id'=>$branch->id])->with('success','Saved.');
    }
}
