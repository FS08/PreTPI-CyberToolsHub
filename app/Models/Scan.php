<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scan extends Model
{
    protected $fillable = [
        'user_id',
        'from','from_domain','to','subject',
        'date_raw','date_iso',
        'text_length','html_length','raw_size',
        'attachments_count','urls_count',
        'urls_json',
    ];

    protected $casts = [
        'date_iso' => 'datetime',
        'urls_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}