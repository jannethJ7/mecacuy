<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Actuacion extends Model
{
    use HasFactory;

    protected $table = 'actuaciones';

    protected $fillable = [
        'modulo_id', 'actuador_id', 'origen', 'estado_anterior', 'estado_nuevo', 'motivo', 'ejecutado_en',
    ];

    protected $casts = [
        'estado_anterior' => 'array',
        'estado_nuevo' => 'array',
        'motivo' => 'array',
        'ejecutado_en' => 'datetime',
    ];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function actuador(): BelongsTo
    {
        return $this->belongsTo(Actuador::class, 'actuador_id');
    }
}
