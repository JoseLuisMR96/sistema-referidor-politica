<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WppCampaign extends Model
{
    use HasFactory;

    protected $fillable = [

        'name',

        'message',

        'session',

        'total_contacts',

        'sent',

        'failed',

        'started_at',

        'finished_at',
        'image_path',

    ];

    protected $casts = [

        'started_at' => 'datetime',

        'finished_at' => 'datetime'

    ];

    public function messages()
    {
        return $this->hasMany(WppMessage::class,'campaign_id');
    }

}