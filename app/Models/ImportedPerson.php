<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportedPerson extends Model
{
    protected $table = 'imported_people';

    protected $fillable = [
        'full_name',
        'phone',
        'document_number',
        'voting_place',
        'voting_municipality',
        'batch_id',
        'created_by_user_id',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
