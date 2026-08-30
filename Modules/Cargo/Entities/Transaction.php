<?php

namespace Modules\Cargo\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Staff;
use Modules\Cargo\Entities\Driver;
use Modules\Cargo\Services\TransactionScopeService;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';
    protected $guarded = [];
    protected $fillable = [];

    CONST MESSION_TYPE = 1;
    CONST SHIPMENT_TYPE = 2;
    CONST MANUAL_TYPE = 3;

    CONST CAPTAIN = 1;
    CONST CLIENT = 2;
    CONST BRANCH = 3;

    CONST DEBIT = 1;   // -
    CONST CREDIT = 2;  // +
    
    protected static function newFactory()
    {
        return \Modules\Cargo\Database\factories\TransactionFactory::new();
    }

    public function client(){
        return $this->belongsTo('Modules\Cargo\Entities\Client', 'client_id');
    }
    public function branch(){
        return $this->belongsTo('Modules\Cargo\Entities\Branch', 'branch_id');
    }
    public function captain(){
        return $this->belongsTo('Modules\Cargo\Entities\Driver', 'captain_id');
    }
    public function mission(){
        return $this->belongsTo('Modules\Cargo\Entities\Mission', 'mission_id');
    }
    public function shipment(){
        return $this->belongsTo('Modules\Cargo\Entities\Shipment', 'shipment_id');
    }

    static public function getTransactions($query , $request = null){
        $builder = $query instanceof self ? $query->newQuery() : $query;

        return app(TransactionScopeService::class)->apply($builder, auth()->user(), $request);

    }
}
