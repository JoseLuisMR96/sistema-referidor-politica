<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    protected $fillable = [
        'campaign_id','to','contact_name','twilio_sid','status',
        'error_code','error_message','sent_at','delivered_at','last_status_at',
        'raw_webhook'
    ];

    protected $casts = [
        'raw_webhook' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'last_status_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsappCampaign::class, 'campaign_id');
    }
}
