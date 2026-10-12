<?php

// app/Models/WhatsAppOutbox.php
namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppOutbox extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_outbox';

    protected $fillable = [
        'phone',
        'message',
        'status',
        'attempts',
        'reserved_by',
        'reserved_at',
        'sent_at',
        'last_error'
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'sent_at' => 'datetime',
    ];
}
