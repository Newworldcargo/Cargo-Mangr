@php
    $taxGroups=[];
    foreach($items as $item){
        $key=($item->tax_category??'legacy').'|'.($item->tax_label??'Tax').'|'.$item->tax_basis_points;
        if(!isset($taxGroups[$key]))$taxGroups[$key]=['label'=>$item->tax_label??'Tax','rate'=>$item->tax_basis_points,'net'=>0,'tax'=>0];
        $taxGroups[$key]['net']+=$item->subtotal_minor-$item->discount_minor;
        $taxGroups[$key]['tax']+=$item->tax_minor;
    }
@endphp
<div class="table-responsive"><table class="table sales-history-table"><thead><tr><th>Tax category</th><th>Rate</th><th>Net amount</th><th>Tax amount</th></tr></thead><tbody>@foreach($taxGroups as $group)<tr><td>{{ $group['label'] }}</td><td>{{ number_format($group['rate']/100,2) }}%</td><td>{{ $money($group['net']) }}</td><td>{{ $money($group['tax']) }}</td></tr>@endforeach</tbody></table></div>
