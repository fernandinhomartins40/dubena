<?php

namespace App\Models\Fiscal;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Inutilização de faixa de numeração fiscal (F09) — escopada por empresa. */
class InutilizacaoFiscal extends Model
{
    use BelongsToTenant;

    protected $table = 'inutilizacoes_fiscais';

    protected $fillable = [
        'empresa_id', 'modelo', 'serie', 'numero_inicial', 'numero_final',
        'justificativa', 'protocolo', 'homologada', 'motivo',
    ];

    protected function casts(): array
    {
        return [
            'modelo' => 'integer',
            'serie' => 'integer',
            'numero_inicial' => 'integer',
            'numero_final' => 'integer',
            'homologada' => 'boolean',
        ];
    }
}
