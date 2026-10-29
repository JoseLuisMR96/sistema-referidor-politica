<?php

namespace App\Models;

use App\Enums\CargoEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Un tenant es un candidato o campaña a la que se apoya (p. ej. "Camilo Cfer",
 * postulado al Concejo). Todo lo que se registra —referidores, registros,
 * pregoneros, padrón importado y envíos de WhatsApp— pertenece a uno.
 */
class Tenant extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'partido',
        'slogan',
        'color_primario',
        'color_secundario',
        'tema_completo',
        'logo_path',
        'foto_path',
        'fondo_path',
        'cargo',
        'departamento_id',
        'municipio_id',
        'slug',
        'activo',
        'twilio_messaging_service_sid',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'tema_completo' => 'boolean',
        'cargo' => CargoEnum::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = static::slugUnico($tenant->nombre);
            }
        });
    }

    public static function slugUnico(string $nombre): string
    {
        $base = Str::slug($nombre) ?: 'tenant';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    /**
     * Ámbito de la circunscripción, o null si al tenant le falta el cargo o
     * el lugar (p. ej. el "Tenant inicial" sin completar).
     */
    public function ambito(): ?string
    {
        if (! $this->cargo) {
            return null;
        }

        if ($this->cargo->requiereMunicipio() && ! $this->municipio_id) {
            return null;
        }

        if ($this->cargo->requiereDepartamento() && ! $this->departamento_id) {
            return null;
        }

        return $this->cargo->ambito();
    }

    /** "Gobernación · META", "Concejo municipal · Villavicencio (META)", "Senado · Nacional". */
    public function circunscripcionTexto(): string
    {
        if (! $this->cargo) {
            return 'Sin cargo definido';
        }

        $lugar = match ($this->cargo->ambito()) {
            CargoEnum::AMBITO_NACIONAL => 'Nacional',
            CargoEnum::AMBITO_DEPARTAMENTAL => $this->departamento?->nombre ?? 'sin departamento',
            CargoEnum::AMBITO_MUNICIPAL => $this->municipio
                ? "{$this->municipio->nombre} ({$this->departamento?->nombre})"
                : 'sin municipio',
        };

        return "{$this->cargo->label()} · {$lugar}";
    }

    /**
     * URL pública del logo o la foto (ruta marca.archivo). El ?v= cambia al
     * actualizar el tenant para que el navegador no muestre la imagen vieja.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? route('marca.archivo', [$this->slug, 'logo', 'v' => $this->updated_at?->timestamp], false) : null;
    }

    public function fotoUrl(): ?string
    {
        return $this->foto_path ? route('marca.archivo', [$this->slug, 'foto', 'v' => $this->updated_at?->timestamp], false) : null;
    }

    public function fondoUrl(): ?string
    {
        return $this->fondo_path ? route('marca.archivo', [$this->slug, 'fondo', 'v' => $this->updated_at?->timestamp], false) : null;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Messaging Service SID con el que salen los WhatsApp de este tenant.
     * Si no tiene uno propio, usa el global de .env.
     */
    public function messagingServiceSid(): ?string
    {
        return $this->twilio_messaging_service_sid ?: config('services.twilio.messaging_service_sid');
    }
}
