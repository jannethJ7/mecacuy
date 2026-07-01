<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReglaAlerta extends Model
{
    use HasFactory;

    protected $table = 'reglas_alerta';

    protected $fillable = [
        'modulo_id', 'sensor_id', 'actuador_id', 'tipo', 'umbral_min', 'umbral_max',
        'sin_datos_min', 'severidad', 'plantilla_mensaje', 'enfriamiento_min', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'umbral_min' => 'decimal:3',
        'umbral_max' => 'decimal:3',
    ];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class, 'sensor_id');
    }

    public function actuador(): BelongsTo
    {
        return $this->belongsTo(Actuador::class, 'actuador_id');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class, 'regla_alerta_id');
    }
}
