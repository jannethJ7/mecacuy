<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditoriaEvento extends Model
{
    use HasFactory;

    protected $table = 'auditoria_eventos';

    public $timestamps = false;

    protected $fillable = [
        'actor_tipo', 'actor_id', 'evento_tipo', 'entidad_tipo', 'entidad_id', 'data', 'creado_en',
    ];

    protected $casts = [
        'data' => 'array',
        'creado_en' => 'datetime',
    ];
}
