<?php

namespace App\Models\Fiscal;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuração fiscal da empresa (1:1). CSC token encriptado (segredo NFC-e).
 */
class ConfigFiscal extends Model
{
    use HasFactory;

    protected $table = 'config_fiscais';

    protected $fillable = [
        'empresa_id', 'ambiente', 'serie_nfe', 'serie_nfce',
        'regime_tributario', 'csc_id', 'csc_token',
    ];

    protected $hidden = ['csc_token'];

    protected function casts(): array
    {
        return [
            'ambiente' => 'integer',
            'serie_nfe' => 'integer',
            'serie_nfce' => 'integer',
            'regime_tributario' => 'integer',
            'csc_token' => 'encrypted',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Empresa::class);
    }
}
