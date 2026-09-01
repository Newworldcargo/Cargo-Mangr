<?php

namespace Modules\Cargo\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Support extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subject',
        'category',
        'priority',
        'shipment_number',
        'message',
        'attachments',
        'status'
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    protected static function newFactory()
    {
        return \Modules\Cargo\Database\factories\SupportFactory::new();
    }
}
