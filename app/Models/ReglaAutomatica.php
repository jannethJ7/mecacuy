<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReglaAutomatica extends Model
{
    use HasFactory;

    protected $table = 'reglas_automaticas';

    protected $fillable = [
        'modulo_id', 'sensor_id', 'actuador_id', 'nombre', 'activo',
        'objetivo_min', 'objetivo_max', 'histeresis', 'retardo_seg', 'payload', 'prioridad',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'objetivo_min' => 'decimal:3',
        'objetivo_max' => 'decimal:3',
        'histeresis' => 'decimal:3',
        'payload' => 'array',
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

    public function estado(): HasOne
    {
        return $this->hasOne(EstadoRegla::class, 'regla_id');
    }
}
