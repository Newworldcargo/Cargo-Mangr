<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsignmentImportRow extends Model
{
    protected $guarded = [];
    protected $casts = ['raw_values' => 'array', 'mapped_values' => 'array', 'validation_errors' => 'array', 'validation_warnings' => 'array', 'included' => 'boolean'];

    public function batch()
    {
        return $this->belongsTo(ConsignmentImportBatch::class, 'batch_id');
    }
}
