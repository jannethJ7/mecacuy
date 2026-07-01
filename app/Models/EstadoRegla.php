<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstadoRegla extends Model
{
    use HasFactory;

    protected $table = 'estado_reglas';

    protected $fillable = [
        'regla_id', 'estado_latch', 'cambiado_en', 'evaluado_en', 'bloqueado_hasta',
    ];

    protected $casts = [
        'estado_latch' => 'array',
        'cambiado_en' => 'datetime',
        'evaluado_en' => 'datetime',
        'bloqueado_hasta' => 'datetime',
    ];

    public function regla(): BelongsTo
    {
        return $this->belongsTo(ReglaAutomatica::class, 'regla_id');
    }
}
