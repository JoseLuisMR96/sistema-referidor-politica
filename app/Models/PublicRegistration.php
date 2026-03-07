<?php

namespace App\Models;

use App\Models\User;
use App\Models\Referrer;
use Illuminate\Database\Eloquent\Model;

class PublicRegistration extends Model
{
    protected $fillable = [
        'full_name',
        'document_type',
        'document_number',
        'age',
        'gender',
        'residence_municipality',    
        'voting_municipality',          
        'residence_municipality_id',     
        'voting_municipality_id',        
        'phone',
        'referrer_id',
        'ref_code_used',
        'status',
        'created_by_user_id'
    ];

    public function referrer()
    {
        return $this->belongsTo(Referrer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function residenceMunicipality()
    {
        return $this->belongsTo(Municipio::class, 'residence_municipality_id');
    }

    public function votingMunicipality()
    {
        return $this->belongsTo(Municipio::class, 'voting_municipality_id');
    }
}
