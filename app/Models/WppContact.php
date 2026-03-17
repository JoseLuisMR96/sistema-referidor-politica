<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WppContact extends Model
{
    use HasFactory;

    protected $fillable = [

        'name',

        'phone',

        'opt_in',

        'last_message_at'

    ];

    protected $casts = [

        'opt_in' => 'boolean',

        'last_message_at' => 'datetime'

    ];

    public function messages()
    {
        return $this->hasMany(WppMessage::class,'contact_id');
    }

}