<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programacion extends Model
{
    use HasFactory;

    protected $table = 'programaciones';

    protected $fillable = [
        'modulo_id', 'actuador_id', 'nombre', 'activo', 'dias', 'hora_inicio',
        'duracion_seg', 'estado_deseado', 'prioridad',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'dias' => 'array',
        'estado_deseado' => 'array',
    ];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function actuador(): BelongsTo
    {
        return $this->belongsTo(Actuador::class, 'actuador_id');
    }

    public function ejecuciones(): HasMany
    {
        return $this->hasMany(EjecucionProgramacion::class, 'programacion_id');
    }
}
