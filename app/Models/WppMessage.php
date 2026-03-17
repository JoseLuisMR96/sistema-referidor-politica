<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WppMessage extends Model
{
    use HasFactory;

    protected $fillable = [

        'campaign_id',

        'contact_id',

        'phone',

        'message',

        'status',

        'provider_message_id',

        'sent_at',

        'error',

        'provider_response'

    ];

    protected $casts = [

        'sent_at' => 'datetime',

        'provider_response' => 'array'

    ];

    public function campaign()
    {
        return $this->belongsTo(WppCampaign::class,'campaign_id');
    }

    public function contact()
    {
        return $this->belongsTo(WppContact::class,'contact_id');
    }

}