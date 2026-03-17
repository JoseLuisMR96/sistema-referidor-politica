<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappCampaign extends Model
{
    protected $table = 'campaigns';

    protected $fillable = [
        'name',
        'type',
        'body',
        'media_path',
        'media_mime',
        'location_label',
        'location_lat',
        'location_lng',
        'location_url',
        'template_name',
        'template_variables',
        'status',
        'total',
        'delivered',
        'failed_count',
        'messaging_service_sid',
        'content_sid',
        'content_variables',
    ];

    protected $casts = [
        'template_variables' => 'array',
        'location_lat' => 'decimal:7',
        'location_lng' => 'decimal:7',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'campaign_id');
    }
}
