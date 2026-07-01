<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigSistema extends Model
{
    use HasFactory;

    protected $table = 'config_sistema';

    protected $fillable = ['clave', 'valor'];

    protected $casts = [
        'valor' => 'array',
    ];
}
