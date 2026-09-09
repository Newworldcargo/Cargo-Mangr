<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsignmentImportBatch extends Model
{
    protected $guarded = [];
    protected $casts = ['mappings' => 'array', 'summary' => 'array', 'result' => 'array', 'confirmed_at' => 'datetime', 'consignment_date' => 'date'];

    public function rows()
    {
        return $this->hasMany(ConsignmentImportRow::class, 'batch_id');
    }
}
