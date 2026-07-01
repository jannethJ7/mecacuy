<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sensor extends Model
{
    use HasFactory;

    protected $table = 'sensores';

    protected $fillable = [
        'modulo_id', 'codigo', 'nombre', 'tipo', 'unidad', 'activo', 'gpio_pin',
        'valor_actual', 'valor_actual_en', 'meta',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'gpio_pin' => 'integer',
        'valor_actual' => 'decimal:3',
        'valor_actual_en' => 'datetime',
        'meta' => 'array',
    ];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(Lectura::class, 'sensor_id');
    }

    public function reglasAutomaticas(): HasMany
    {
        return $this->hasMany(ReglaAutomatica::class, 'sensor_id');
    }

    public function reglasAlerta(): HasMany
    {
        return $this->hasMany(ReglaAlerta::class, 'sensor_id');
    }
    public function ultimaLectura()
{
    return $this->hasOne(Lectura::class)->latestOfMany();
}
}
