<?php

namespace App\Models\Fiscal;

use App\Domain\Tenant\BelongsToTenant;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuração fiscal da empresa (1:1). CSC token encriptado (segredo NFC-e).
 * Escopada por empresa_id (BelongsToTenant): o CSC é segredo fiscal por empresa
 * e NÃO pode vazar entre tenants.
 */
class ConfigFiscal extends Model
{
    use BelongsToTenant, HasFactory;

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
        return $this->belongsTo(Empresa::class);
    }
}
