<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuloCredencial extends Model
{
    use HasFactory;

    protected $table = 'modulos_credenciales';

    protected $fillable = [
        'modulo_id', 'api_key_hash', 'api_key_encrypted', 'revocado_en', 'ultimo_uso_en',
    ];

    protected $casts = [
        'revocado_en' => 'datetime',
        'ultimo_uso_en' => 'datetime',
    ];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }
}
