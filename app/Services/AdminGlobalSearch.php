<?php

namespace App\Services;

use App\Models\Consignment;
use Illuminate\Support\Facades\DB;
use Modules\Cargo\Entities\Shipment;

class AdminGlobalSearch
{
    public function pattern(string $query): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query);
    }

    public function phoneVariants(string $query): array
    {
        if (!preg_match('/^[+\d\s().-]+$/', $query)) return [];
        $digits = preg_replace('/\D/', '', $query);
        if (strlen($digits) < 5) return [];
        $variants = [$digits];
        if (preg_match('/^[79]\d{8}$/', $digits)) $variants = array_merge($variants, ['0' . $digits, '260' . $digits]);
        if (preg_match('/^0([79]\d+)$/', $digits, $match)) $variants[] = '260' . $match[1];
        if (preg_match('/^260([79]\d+)$/', $digits, $match)) $variants[] = '0' . $match[1];
        return array_unique($variants);
    }

    public function shipmentIdentifiers(string $query)
    {
        $prefix = $this->pattern($query) . '%';
        // Separate index scans avoid an OR across unrelated tables defeating the indexes.
        $ids = DB::table('shipments')->select('id')->whereRaw("code LIKE ? ESCAPE '!'", [$prefix]);
        if (ctype_digit($query)) $ids->union(DB::table('shipments')->select('id')->where('id', $query));
        foreach ($this->phoneVariants($query) as $phone) {
            foreach (['client_phone_search', 'client_phone_2_search'] as $field) {
                $ids->union(DB::table('shipments')->select('id')->where($field, 'like', $phone . '%'));
            }
        }
        $ids->union(DB::table('transxns')->select('shipment_id as id')->whereRaw("receipt_number LIKE ? ESCAPE '!'", [$prefix]));
        foreach (['name', 'email'] as $field) {
            $ids->union(DB::table('clients')->join('shipments', 'shipments.client_id', '=', 'clients.id')
                ->select('shipments.id')->whereRaw("clients.$field LIKE ? ESCAPE '!'", [$prefix]));
        }
        return Shipment::query()->joinSub($ids, 'matches', 'matches.id', '=', 'shipments.id');
    }

    public function shipments(string $query, bool $quick = false)
    {
        $fast = $this->shipmentIdentifiers($query);
        if ($quick) return $this->orderShipments($fast, $query);
        $terms = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $builder = Shipment::query()->where(function ($outer) use ($terms, $query) {
            $outer->where(function ($all) use ($terms) {
                foreach ($terms as $term) {
                    $pattern = '%' . $this->pattern($term) . '%';
                    $all->where(function ($match) use ($pattern) {
                        foreach (['code', 'client_phone', 'client_phone_2', 'client_address', 'shipping_date', 'shipping_cost', 'dest_port', 'salesman', 'volume'] as $field) {
                            $match->orWhereRaw("shipments.$field LIKE ? ESCAPE '!'", [$pattern]);
                        }
                        $match->orWhereHas('client', function ($client) use ($pattern) {
                            $client->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern]);
                        });
                    });
                }
            });
            foreach ($this->phoneVariants($query) as $phone) {
                foreach (['client_phone_search', 'client_phone_2_search'] as $field) $outer->orWhere($field, 'like', $phone . '%');
            }
            $outer->orWhereIn('shipments.id', DB::table('transxns')->select('shipment_id')->whereRaw("receipt_number LIKE ? ESCAPE '!'", [$this->pattern($query) . '%']));
            if (ctype_digit($query)) $outer->orWhere('shipments.id', $query);
        });
        return $this->orderShipments($builder, $query);
    }

    public function orderShipments($builder, string $query)
    {
        return $builder->select(['shipments.id', 'shipments.code', 'shipments.client_phone', 'shipments.client_phone_2', 'shipments.client_address', 'shipments.dest_port', 'shipments.type', 'shipments.status_id', 'shipments.created_at'])
            ->orderByRaw('CASE WHEN shipments.code = ? THEN 0 ELSE 1 END', [$query])->orderByDesc('shipments.id');
    }

    public function consignments(string $query, bool $quick = false)
    {
        $builder = Consignment::query();
        $prefix = $this->pattern($query) . '%';
        if ($quick && Consignment::whereRaw("consignment_code LIKE ? ESCAPE '!'", [$prefix])->exists()) {
            $builder->whereRaw("consignment_code LIKE ? ESCAPE '!'", [$prefix]);
        } else {
            foreach (preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) as $term) {
                $builder->where(function ($match) use ($term) {
                    foreach (['consignment_code', 'name', 'desc', 'source', 'destination', 'released_by', 'tracker', 'voyage_no', 'shipping_line', 'cargo_type', 'consignee', 'job_num', 'mawb_num', 'hawb_num', 'status'] as $field) {
                        $match->orWhereRaw("`$field` LIKE ? ESCAPE '!'", ['%' . $this->pattern($term) . '%']);
                    }
                });
            }
        }
        return $builder->select(['id', 'consignment_code', 'name', 'source', 'destination', 'status', 'cargo_type', 'created_at'])
            ->orderByRaw('CASE WHEN consignment_code = ? THEN 0 ELSE 1 END', [$query])->orderByDesc('id');
    }

    public function shipmentResult($item): array
    {
        return ['id' => $item->id, 'title' => $item->code, 'subtitle' => 'Ref: ' . $item->code,
            'description' => "Phone: {$item->client_phone}" . ($item->client_phone_2 ? " / {$item->client_phone_2}" : '') . " | Address: {$item->client_address} | Port: {$item->dest_port}",
            'status' => $item->getStatus(), 'type' => $item->type, 'date' => optional($item->created_at)->format('M d, Y'),
            'url' => url('admin/shipments/shipments/' . $item->id), 'icon' => 'fas fa-box'];
    }

    public function consignmentResult($item): array
    {
        return ['id' => $item->id, 'title' => $item->name ?: $item->consignment_code, 'subtitle' => 'Code: ' . $item->consignment_code,
            'description' => "From {$item->source} to {$item->destination}", 'status' => $item->status, 'type' => $item->cargo_type,
            'date' => optional($item->created_at)->format('M d, Y'), 'url' => route('consignment.show', $item->id),
            'icon' => $item->cargo_type === 'sea' ? 'fa fa-ship' : 'fa fa-plane'];
    }
}
