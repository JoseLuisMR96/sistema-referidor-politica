<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Elección que vigila la campaña el día E, con sus candidatos o listas. */
class Eleccion extends Model
{
    use BelongsToTenant;

    protected $table = 'elecciones';

    protected $fillable = ['nombre', 'cargo', 'fecha', 'abierta'];

    protected $casts = ['fecha' => 'date', 'abierta' => 'boolean'];

    public const DISCO = 'local';

    public function opciones(): HasMany
    {
        return $this->hasMany(EleccionOpcion::class)->orderBy('orden')->orderBy('id');
    }

    public function testigos(): HasMany
    {
        return $this->hasMany(Testigo::class);
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(ReporteMesa::class);
    }
}
