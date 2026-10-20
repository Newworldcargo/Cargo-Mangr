<?php

namespace Modules\CustomerPortalApi\Services\Pricing;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Services\{ShippingTariff, SalesConfiguration, InvoiceAmounts};
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Services\{BranchAccessService, MobilePricingSettings};

final class ImportTariffQuote
{
    public function calculate(array $input, array $rates): ?array
    {
        if (($input['service']??'') !== 'import') return null;
        $destination=$input['receivingHub']??$input['destination'];
        $branch=Branch::where('is_archived',0)->find($destination['branchId']??0);
        if (!$branch) return null;
        $candidates=DB::table('billing_shipping_tariffs')->where('branch_id',$branch->id)->get();
        $matching=[];
        foreach($candidates as $candidate) {
            $config=json_decode($candidate->configuration,true);
            if ((string)$config['origin_id'] !== (string)($input['pickup']['branchId']??'') ||
                $config['method'] !== ($input['transportMode']??'')) continue;
            $matching[]=$candidate;
        }
        // Once a route uses rate cards, missing categories/dates must not fall back to legacy rates.
        if (!$matching) return null;
        $category=$input['cargo']['category']??'general';
        if ($category==='general' && isset($input['cargo']['totalVolume'])) {
            foreach($matching as $candidate) {
                $c=json_decode($candidate->configuration,true);
                if ($c['category']==='small_parcels' && $c['effective_from']<=now()->toDateString() &&
                    (empty($c['effective_until']) || $c['effective_until']>=now()->toDateString()) &&
                    (float)$input['cargo']['totalVolume']>0 && (float)$input['cargo']['totalVolume']<(float)$c['volume_below']) {
                    $category='small_parcels';
                }
            }
        }
        $matching=array_values(array_filter($matching,function($candidate)use($category){
            $c=json_decode($candidate->configuration,true);
            return $c['active'] && $c['booking_enabled'] && $c['category']===$category &&
                $c['effective_from']<=now()->toDateString() &&
                (empty($c['effective_until']) || $c['effective_until']>=now()->toDateString());
        }));
        if (count($matching)!==1) throw new DomainException('This cargo needs a price confirmed by our team.');
        $tariff=$matching[0]; $config=json_decode($tariff->configuration,true);
        $service=DB::table('billing_services')->where('active',true)->find($tariff->service_id);
        if (!$service) throw new DomainException('This shipping service is currently unavailable.');
        $volume=isset($input['cargo']['totalVolume']) ? number_format((float)$input['cargo']['totalVolume'],3,'.','') : null;
        $weight=(float)($input['cargo']['totalWeight']??0);
        $quantity=$config['unit']==='cbm' ? ($volume??'0') : number_format($config['unit']==='ton'?$weight/1000:$weight,3,'.','');
        $currency=app(BranchAccessService::class)->currencyFor(null,$branch,true);
        $snapshot=app(ShippingTariff::class)->calculate($tariff,$quantity,$currency,now()->toDateString(),$volume);
        $lines=app(SalesConfiguration::class)->lines([[
            'description'=>$service->name,'quantity'=>'1','unit_price'=>number_format($snapshot['amount_minor']/100,2,'.',''),
            'discount'=>'0','tax_id'=>$service->tax_id,'tax_mode'=>$service->tax_mode,'unit'=>'service',
        ]],$branch->id,now()->toDateString());
        $amounts=InvoiceAmounts::calculate($lines);
        $total=$amounts['total_minor']/100;
        $breakdown=[['code'=>'international_freight','label'=>$service->name,'amount'=>$total]];
        $onward=$input['onwardDelivery']??'collection';
        if ($onward!=='collection') {
            $legacyCurrency=\Modules\Cargo\Entities\ShipmentSetting::getVal('mobile_pricing_currency')?:config('customerportalapi.booking_pricing.currency','ZMW');
            if ($currency!==strtoupper($legacyCurrency)) throw new DomainException('Onward delivery needs a price confirmed by our team.');
            $request=$input; $request['service']=$onward==='local'?'local':'intercity';
            if(!app(MobilePricingSettings::class)->advancePricingEnabled($request['service'])) throw new DomainException('Onward delivery needs a price confirmed by our team.');
            $request['pickup']=$destination; $request['vehicleType']=$input['onwardVehicleType']??'scooter';
            unset($request['receivingHub']);
            $delivery=app(MobileBookingQuoteCalculator::class)->calculate($request,app(MobilePricingSettings::class)->ratesFor($request,$rates));
            $total+=$delivery['total']; $breakdown=array_merge($breakdown,$delivery['breakdown']);
        }
        return ['total'=>round($total,2),'currency'=>$currency,'distanceKm'=>null,
            'estimatedDurationMinutes'=>null,'pricingStatus'=>'priced','breakdown'=>$breakdown,
            'tariffSnapshot'=>$snapshot,'estimatedTransitDays'=>$config['transit_days']??null];
    }
}
