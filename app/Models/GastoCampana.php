<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gasto de campaña con su categoría de Cuentas Claras y su soporte (factura,
 * cuenta de cobro), que va en el disco privado.
 */
class GastoCampana extends Model
{
    use BelongsToTenant;

    protected $table = 'gastos_campana';

    public const DISCO = 'local';

    /**
     * Categorías de gastos del informe de Cuentas Claras (CNE). Verifica los
     * códigos con el formato vigente de la elección antes de reportar.
     */
    public const CATEGORIAS = [
        '201' => 'Gastos de administración',
        '202' => 'Gastos de oficina y adquisiciones',
        '203' => 'Inversión en materiales y publicaciones',
        '204' => 'Actos públicos',
        '205' => 'Servicio de transporte y correo',
        '206' => 'Capacitación e investigación política',
        '207' => 'Gastos judiciales y de rendición de cuentas',
        '208' => 'Propaganda electoral',
        '209' => 'Costos financieros',
        '210' => 'Otros gastos',
    ];

    /** Categoría en la que caen los eventos de campaña. */
    public const CATEGORIA_EVENTOS = '204';

    public const ESTADOS = ['pagado' => 'Pagado', 'por_pagar' => 'Por pagar'];

    protected $fillable = [
        'categoria', 'fecha', 'concepto', 'proveedor', 'documento', 'valor', 'estado',
        'evento_id', 'responsable_id', 'soporte_ruta', 'soporte_nombre', 'creado_por',
    ];

    protected $casts = ['fecha' => 'date', 'valor' => 'decimal:2'];

    public function categoriaTexto(): string
    {
        return self::CATEGORIAS[$this->categoria] ?? $this->categoria;
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(EventoCampana::class, 'evento_id');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(IntegranteEquipo::class, 'responsable_id');
    }
}
