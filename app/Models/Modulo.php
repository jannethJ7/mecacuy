<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Modulo extends Model
{
    use HasFactory;

    protected $table = 'modulos';

    protected $fillable = [
        'codigo', 'nombre', 'uid', 'habilitado', 'version_firmware',
        'ip', 'rssi', 'ultimo_contacto', 'zona_horaria', 'meta',
    ];

    protected $casts = [
        'habilitado' => 'boolean',
        'ultimo_contacto' => 'datetime',
        'meta' => 'array',
    ];

    public function credencial(): HasOne
    {
        return $this->hasOne(ModuloCredencial::class, 'modulo_id');
    }

    public function sensores(): HasMany
    {
        return $this->hasMany(Sensor::class, 'modulo_id');
    }

    public function actuadores(): HasMany
    {
        return $this->hasMany(Actuador::class, 'modulo_id');
    }

    public function reglasAutomaticas(): HasMany
    {
        return $this->hasMany(ReglaAutomatica::class, 'modulo_id');
    }

    public function programaciones(): HasMany
    {
        return $this->hasMany(Programacion::class, 'modulo_id');
    }

    public function alertas(): HasMany
    {
        return $this->hasMany(Alerta::class, 'modulo_id');
    }

    public function comandosIot(): HasMany
    {
        return $this->hasMany(ComandoIot::class, 'modulo_id');
    }

    public function actuaciones(): HasMany
    {
        return $this->hasMany(Actuacion::class, 'modulo_id');
    }

    public function getEstaOnlineAttribute(): bool
    {
        return $this->ultimo_contacto && $this->ultimo_contacto->greaterThan(now()->subMinutes(5));
    }
}
