<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'slug',
        'activo',
        'twilio_messaging_service_sid',
    ];

    protected $casts = [
        'activo' => 'boolean',
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
