<?php

namespace Modules\Cargo\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Cargo\Entities\Client;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Staff;

class Staff extends Model
{
    use HasFactory;

    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'staffs';
    
    protected static function newFactory()
    {
        return \Modules\Cargo\Database\factories\StaffFactory::new();
    }
    public function branch(){
        return $this->hasOne('Modules\Cargo\Entities\Branch', 'id', 'branch_id');
    }
    public function accessibleBranches(){
        return $this->belongsToMany(Branch::class, 'staff_branch_access', 'staff_id', 'branch_id')->withTimestamps();
    }
    public function user(){
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }
    public function getStaff($query)
    {
        if(auth()->user()->role == 3){
            $branchIds = app(\Modules\Cargo\Services\BranchAccessService::class)->branchIdsFor(auth()->user());
            $query = $query->where('is_archived', 0)->whereIn('branch_id', $branchIds);
        }elseif(auth()->user()->can('manage-staffs') && in_array((int) auth()->user()->role, [0, 2], true)){
            $branchIds = app(\Modules\Cargo\Services\BranchAccessService::class)->branchIdsFor(auth()->user());
            $query = $query->where('is_archived', 0)->whereIn('branch_id', $branchIds);
        }
        return $query;
    }
}
