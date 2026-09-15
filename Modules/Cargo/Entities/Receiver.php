<?php

namespace Modules\Cargo\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Image\Manipulations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Modules\Cargo\Entities\Branch;
use Spatie\MediaLibrary\MediaCollections\Models\Media;



class Receiver extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'receivers';


    protected static function newFactory()
    {
        return \Modules\Cargo\Database\factories\ReceiverFactory::new();
    }

    public function getClients($query)
    {
        if(auth()->user()->role == 1){
            return $query->where('is_archived', 0);
        }elseif(auth()->user()->role == 3){
            $branch = Branch::where('user_id',auth()->user()->id)->pluck('id')->first();
        }elseif(in_array((int) auth()->user()->role, [0, 2], true)){
            $branchIds = app(\Modules\Cargo\Services\BranchAccessService::class)->branchIdsFor(auth()->user());
            return $query->where('is_archived', 0)->whereIn('branch_id', $branchIds);
        }
        return $query->where('is_archived', 0)->where('branch_id', $branch);
    }

}
