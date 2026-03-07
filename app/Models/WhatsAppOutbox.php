<?php

// app/Models/WhatsAppOutbox.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppOutbox extends Model
{
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
