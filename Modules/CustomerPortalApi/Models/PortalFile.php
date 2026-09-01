<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PortalFile extends Model
{
    protected $table = 'customer_portal_files';
    protected $guarded = [];

    protected $casts = [
        'size_bytes' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function getAuthorizedUrlAttribute()
    {
        try {
            return Storage::disk(config('filesystems.portal_disk', 's3'))
                ->temporaryUrl($this->storage_key, now()->addMinutes(10));
        } catch (\Throwable $exception) {
            return url('/api/v1/files/' . $this->file_id . '/download');
        }
    }
}
