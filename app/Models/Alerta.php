<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerta extends Model
{
    use HasFactory;

    protected $table = 'alertas';

    protected $fillable = [
        'modulo_id', 'regla_alerta_id', 'sensor_id', 'actuador_id', 'severidad',
        'mensaje', 'contexto', 'estado', 'reconocida_por_user_id', 'reconocida_en', 'cerrada_en',
    ];

    protected $casts = [
        'contexto' => 'array',
        'reconocida_en' => 'datetime',
        'cerrada_en' => 'datetime',
    ];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function reglaAlerta(): BelongsTo
    {
        return $this->belongsTo(ReglaAlerta::class, 'regla_alerta_id');
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class, 'sensor_id');
    }

    public function actuador(): BelongsTo
    {
        return $this->belongsTo(Actuador::class, 'actuador_id');
    }

    public function reconocidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconocida_por_user_id');
    }
}
