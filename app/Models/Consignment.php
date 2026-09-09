<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Cargo\Entities\Shipment;
use Carbon\Carbon;

class Consignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'checkpoint',
        'status',
        'consignment_code',
        'name',
        'desc',
        'source',
        'destination',
        'released_by',
        'tracker',
        'voyage_no',
        'date',
        'departure_date',
        'shipping_line',
        'arrival_date',
        'eta_dar',
        'eta_lun',
        'cargo_type',
        'consignee',
        'job_num',
        'mawb_num',
        'hawb_num',
        'eta',
        'cargo_date',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'cargo_date' => 'date',
        'date' => 'date',
        'departure_date' => 'date',
        'arrival_date' => 'date',
        'eta' => 'date',
        'eta_dar' => 'date',
        'eta_lun' => 'date',
    ];

    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : 'N/A';
    }

    public function getFormattedUpdatedAtAttribute()
    {
        return $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : 'N/A';
    }

    public function getFormattedEtaAttribute()
    {
        return $this->eta ? $this->eta->format('Y-m-d H:i:s') : 'N/A';
    }

    public function getFormattedCargoDateAttribute()
    {
        return $this->cargo_date ? $this->cargo_date->format('Y-m-d H:i:s') : 'N/A';
    }

    public function getFormattedArrivalDateAttribute()
    {
        return $this->arrival_date ? $this->arrival_date->format('Y-m-d H:i:s') : 'N/A';
    }

    public function getFormattedDepartureDateAttribute()
    {
        return $this->departure_date ? $this->departure_date->format('Y-m-d H:i:s') : 'N/A';
    }

    public function getShipmentCountAttribute()
    {
        if (array_key_exists('shipments_count', $this->attributes)) {
            return (int) $this->attributes['shipments_count'];
        }

        if ($this->relationLoaded('shipments')) {
            return $this->shipments->count();
        }

        return $this->shipments()->count();
    }

    /**
     * Get the shipments for the consignment.
     */
    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function trackingHistory()
    {
        return $this->hasMany(ConsignmentTrackingHistory::class);
    }

    public function getTrackingStages()
    {
        // Fetch stages from the tracking_stages table for this consignment's cargo_type
        $stages = \App\Models\TrackingStage::where('cargo_type', $this->cargo_type)
            ->orderBy('order')
            ->get();
        // Return as [id => name] or [id => description] as needed
        $result = [];
        foreach ($stages as $stage) {
            $result[$stage->id] = $stage->description; // or $stage->description - recommended by brinkly
            // $result[$stage->id] = $stage->name.' | '.$stage->description; // or $stage->description if you prefer
        }
        return $result;
    }

    public function getCurrentStage()
    {
        $latestHistory = $this->trackingHistory()
            ->orderBy('stage_id', 'desc')
            ->first();

        return $latestHistory ? $latestHistory->stage_id : 0;
    }

    public function getCurrentStageName()
    {
        $stages = $this->getTrackingStages();
        return $stages[$this->checkpoint] ?? 'Unknown';
    }

    public function getCurrentStatusAttribute()
    {
        $currentStage = $this->getCurrentStage();
        
        if ($currentStage === 0) {
            return 'PENDING';
        }

        $stage = \DB::table('tracking_stages')
            ->where('id', $currentStage)
            ->first();

        return $stage ? $stage->status : 'PENDING';
    }

    public function updateCheckpoint($stageId)
    {
        $maxStage = count($this->getTrackingStages());
        if ($stageId < 1 || $stageId > $maxStage) {
            return false;
        }

        $this->checkpoint = $stageId;
        
        // Update status based on checkpoint for air and sea stages
        if ($this->cargo_type === 'sea') {
            if ($stageId > 1) {
                $this->status = 'in_transit';
            }
            if ($stageId > 8) {
                $this->status = 'delivered';
            }
        } else {
            // Air cargo type logic
            if ($stageId > 1) {
                $this->status = 'in_transit';
            }
            if ($stageId > 5) {
                $this->status = 'delivered';
            }
        }

        return $this->save();
    }

    public function updateTrackingStage($stageId, $data = [])
    {
        return $this->trackingHistory()->create([
            'stage_id' => $stageId,
            'status' => $data['status'] ?? 'completed',
            'notes' => $data['notes'] ?? null,
            'location' => $data['location'] ?? null,
            'completed_at' => $data['completed_at'] ?? Carbon::now(),
            'updated_by' => auth()->id()
        ]);
    }
}
