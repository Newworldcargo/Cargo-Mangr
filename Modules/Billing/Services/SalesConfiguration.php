<?php

namespace Modules\Billing\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Services\BranchAccessService;

final class SalesConfiguration
{
    public const UNITS = ['service', 'kg', 'cbm', 'ton', 'trip', 'hour', 'day', 'package'];

    public function assertReady(object $document, array $items): void
    {
        if ($document->document_details === null) return;
        $issuer=json_decode($document->issuer_snapshot,true);
        $profile=DB::table('billing_profiles')->where('branch_id',$document->branch_id)->first();
        if (!$profile || empty($issuer['address']) || empty($issuer['vat_status'])) throw new \DomainException('Complete the branch billing profile in Sales settings, then edit and save this draft before issuing it.');
        foreach(['name'=>'legal_name','address'=>'address','tpin'=>'tpin','vat_status'=>'vat_status'] as $snapshot=>$field) {
            if(($issuer[$snapshot]??null)!==$profile->$field) throw new \DomainException('The branch billing profile has changed. Edit and save this draft before issuing it.');
        }
        $prepared=$this->lines(array_map([InvoiceAmounts::class,'editableLine'],$items),$document->branch_id,$document->issue_date,false);
        foreach($items as $index=>$item) {
            if(InvoiceAmounts::decimal($prepared[$index]['tax_percent'],10000)!==(int)$item->tax_basis_points || $prepared[$index]['tax_category']!==($item->tax_category??null)) {
                throw new \DomainException('Tax settings have changed. Review and save this draft before issuing it.');
            }
        }
    }

    public function branch($user, int $id): Branch
    {
        $branch = Branch::where('is_archived', 0)->findOrFail($id);
        if ((int) $user->role !== 1) abort_unless(app(BranchAccessService::class)->branchIdsFor($user)->contains($id), 403);
        return $branch;
    }

    public function agents(int $branchId)
    {
        return User::whereIn('role', [0, 1, 2, 3])->where(function ($query) use ($branchId) {
            $query->where('role', 1)->orWhereIn('id', DB::table('branches')->where('id', $branchId)->select('user_id'))
                ->orWhereIn('id', DB::table('staffs')->where('branch_id', $branchId)->select('user_id'));
        })->orderBy('name')->get(['id', 'name']);
    }

    public function catalogue(int $branchId): array
    {
        $branch = Branch::findOrFail($branchId);
        $currency = app(BranchAccessService::class)->currencyFor(null, $branch, true);
        return ['currency'=>$currency, 'agents'=>$this->agents($branchId),
            'profile'=>DB::table('billing_profiles')->where('branch_id',$branchId)->first(),
            'taxes'=>DB::table('billing_tax_rates')->where('branch_id',$branchId)->where('active',true)->orderBy('label')->get(),
            'services'=>DB::table('billing_services')->where('branch_id',$branchId)->where('currency',$currency)->where('active',true)->orderBy('name')->get()->map(function ($service) {
                $tariff=app(ShippingTariff::class)->forService($service->id);
                $service->shipping_tariff=$tariff ? json_decode($tariff->configuration,true) : null;
                return $service;
            })];
    }

    public function lines(array $items, int $branchId, string $date, bool $priceTariffs = true, bool $allowPendingTax = false): array
    {
        $profile=DB::table('billing_profiles')->where('branch_id',$branchId)->first();
        $taxes = DB::table('billing_tax_rates')->where('branch_id',$branchId)->where('active',true)
            ->where('effective_from','<=',$date)->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until','>=',$date))->get()->keyBy('id');
        foreach ($items as $index => &$item) {
            $tax = $taxes->get($item['tax_id'] ?? 0);
            if (!$tax && (!$allowPendingTax || !empty($item['tax_id']))) throw ValidationException::withMessages(["items.$index.tax_id"=>'Choose an active tax category for the issue date. Configure branch taxes in Sales settings if none are available.']);
            if ($tax && $profile && $profile->vat_status === 'not_registered' && $tax->category !== 'non_vat') throw ValidationException::withMessages(["items.$index.tax_id"=>'This branch is not VAT registered. Choose its non-VAT tax category.']);
            // Preview may show an explicitly incomplete estimate, never a payable tax decision.
            $item['tax_percent'] = $tax ? number_format($tax->basis_points/100,2,'.','') : '0';
            $item['tax_label'] = $tax->label ?? null; $item['tax_category'] = $tax->category ?? null;
            if (!empty($item['service_id'])) {
                $service = DB::table('billing_services')->where('branch_id',$branchId)->where('active',true)->find($item['service_id']);
                $branch = Branch::findOrFail($branchId);
                if (!$service || $service->currency !== app(BranchAccessService::class)->currencyFor(null,$branch,true)) {
                    throw ValidationException::withMessages(["items.$index.service_id"=>'Choose an active service in this branch and currency.']);
                }
                $tariff=$priceTariffs ? app(ShippingTariff::class)->forService($service->id) : null;
                if ($tariff) {
                    try {
                        $price=app(ShippingTariff::class)->calculate($tariff,(string)$item['quantity'],$service->currency,$date,$item['cargo_volume']??null);
                    } catch (\DomainException $error) {
                        throw ValidationException::withMessages(["items.$index.quantity"=>$error->getMessage()]);
                    }
                    if ((int)$item['tax_id'] !== (int)$service->tax_id) throw ValidationException::withMessages(["items.$index.tax_id"=>'Use the tax category configured for this shipping service.']);
                    $item['description']=$service->name.' - '.$price['chargeable_quantity'].' '.$price['unit'];
                    $item['quantity']='1';
                    $item['unit']='service';
                    $item['unit_price']=number_format($price['amount_minor']/100,2,'.','');
                    $item['tax_mode']=$service->tax_mode;
                    $item['pricing_snapshot']=$price;
                }
            }
        }
        return $items;
    }

    public function details(array $data, int $branchId, $user): array
    {
        $details = array_intersect_key($data['details'] ?? [],array_flip(['sales_agent_id','order_reference','customer_address','customer_tpin','customer_notes','internal_notes','service_from','service_until','payment_terms']));
        $agent = null;
        if (!empty($details['sales_agent_id'])) {
            $agent = $this->agents($branchId)->firstWhere('id', (int) $details['sales_agent_id']);
            if (!$agent) throw ValidationException::withMessages(['details.sales_agent_id'=>'Choose a sales agent assigned to this branch.']);
        }
        return $details + ['sales_agent_name'=>$agent?->name, 'prepared_by'=>$user->name];
    }
}
