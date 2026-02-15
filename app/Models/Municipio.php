<?php

namespace App\Models;

use App\Models\Departamento;
use App\Models\PublicRegistration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Municipio extends Model
{
    protected $table = 'municipios';

    protected $fillable = [
        'codigo_dane',
        'departamento_id',
        'nombre',
        'slug',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    // Registros donde este municipio es el de residencia
    public function publicRegistrationsResidence(): HasMany
    {
        return $this->hasMany(PublicRegistration::class, 'residence_municipality_id');
    }

    // Registros donde este municipio es el donde vota
    public function publicRegistrationsVoting(): HasMany
    {
        return $this->hasMany(PublicRegistration::class, 'voting_municipality_id');
    }
}
