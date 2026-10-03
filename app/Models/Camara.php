<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Camara extends Model
{
    use HasFactory;

    protected $table = 'camaras';

    protected $fillable = [
        'modulo_id',
        'codigo',
        'nombre',
        'stream_key',
        'tipo',
        'habilitada',
        'ultimo_contacto',
        'meta',
    ];

    protected $casts = [
        'habilitada' => 'boolean',
        'ultimo_contacto' => 'datetime',
        'meta' => 'array',
    ];

    protected $appends = ['hls_url', 'webrtc_url'];

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id');
    }

    public function getHlsUrlAttribute(): ?string
    {
        $base = rtrim((string) config('streaming.mediamtx.hls_public_base'), '/');

        return $base !== '' ? $base.'/'.$this->stream_key.'/index.m3u8' : null;
    }

    public function getWebrtcUrlAttribute(): ?string
    {
        $base = rtrim((string) config('streaming.mediamtx.webrtc_public_base'), '/');

        return $base !== '' ? $base.'/'.$this->stream_key : null;
    }
}
