<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallResult extends Model
{
    protected $fillable = [
        'provider',
        'call_id',
        'agent_id',
        'name',
        'phone',
        'senate_candidate',
        'camara_candidate',
        'status',
        'duration_sec',
        'recording_url',
        'notes',
        'condition_candidate_senate',
        'condition_candidate_camara',
        'raw_payload'
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];
}
