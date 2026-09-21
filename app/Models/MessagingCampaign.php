<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MessagingCampaign extends Model
{
    protected $guarded = [];
    protected $hidden = ['content'];
    protected $casts = ['content' => 'encrypted:array'];
}
