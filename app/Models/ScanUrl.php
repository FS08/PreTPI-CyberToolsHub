<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanUrl extends Model
{
    protected $fillable = [
        'scan_id', 'url', 'host', 'visibility', 'status',
        'urlscan_uuid', 'result_url', 'error_code', 'error_message',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function scan()
    {
        return $this->belongsTo(Scan::class);
    }
}