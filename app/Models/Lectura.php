<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lectura extends Model
{
    use HasFactory;

    protected $table = 'lecturas';

    protected $fillable = [
        'sensor_id', 'valor', 'medido_en', 'recibido_en', 'calidad', 'raw',
    ];

    protected $casts = [
        'valor' => 'decimal:3',
        'medido_en' => 'datetime',
        'recibido_en' => 'datetime',
        'raw' => 'array',
    ];

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class, 'sensor_id');
    }
}
