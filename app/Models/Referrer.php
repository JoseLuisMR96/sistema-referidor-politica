<?php

namespace App\Models;

use App\Models\User;
use App\Models\PublicRegistration;
use Illuminate\Database\Eloquent\Model;

class Referrer extends Model
{
    protected $fillable = ['name', 'code', 'is_active', 'phone', 'email'];

    public function registrations()
    {
        return $this->hasMany(PublicRegistration::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
