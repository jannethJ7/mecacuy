<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EjecucionProgramacion extends Model
{
    use HasFactory;

    protected $table = 'ejecuciones_programacion';

    protected $fillable = [
        'programacion_id', 'inicio_en', 'fin_en', 'estado', 'nota',
    ];

    protected $casts = [
        'inicio_en' => 'datetime',
        'fin_en' => 'datetime',
    ];

    public function programacion(): BelongsTo
    {
        return $this->belongsTo(Programacion::class, 'programacion_id');
    }
}
