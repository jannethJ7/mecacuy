<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComandoIot extends Model
{
    use HasFactory;

    protected $table = 'comandos_iot';

    protected $fillable = [
        'modulo_id', 'actuador_id', 'tipo', 'payload', 'estado', 'nonce', 'intentos',
        'ejecutar_en', 'enviado_en', 'confirmado_en', 'expira_en', 'ultimo_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'ejecutar_en' => 'datetime',
        'enviado_en' => 'datetime',
        'confirmado_en' => 'datetime',
        'expira_en' => 'datetime',
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
