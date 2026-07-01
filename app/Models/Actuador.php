<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actuador extends Model
{
    use HasFactory;

    protected $table = 'actuadores';

    protected $fillable = [
        'modulo_id', 'codigo', 'nombre', 'tipo', 'activo', 'gpio_pin', 'invertido',
        'estado_deseado', 'estado_reportado', 'cambiado_en', 'meta',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'invertido' => 'boolean',
        'estado_deseado' => 'array',
        'estado_reportado' => 'array',
        'cambiado_en' => 'datetime',
        'meta' => 'array',
    ];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function reglasAutomaticas(): HasMany
    {
        return $this->hasMany(ReglaAutomatica::class, 'actuador_id');
    }

    public function programaciones(): HasMany
    {
        return $this->hasMany(Programacion::class, 'actuador_id');
    }

    public function comandosIot(): HasMany
    {
        return $this->hasMany(ComandoIot::class, 'actuador_id');
    }

    public function actuaciones(): HasMany
    {
        return $this->hasMany(Actuacion::class, 'actuador_id');
    }
}
