<?php

namespace App\Models\Satelite;

use App\Domain\Shared\Auditavel;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma VERSÃO emitida do contrato de comodato.
 *
 * Os números aqui são congelados na emissão e nunca lidos do comodato depois: o
 * contrato precisa continuar dizendo o que dizia quando foi assinado. Se a
 * versão 1 diz "5 botijões" e o cliente devolveu 2, a versão 1 continua dizendo
 * 5 — quem descreve a posse nova é a versão 2.
 */
class ComodatoContrato extends Model
{
    use Auditavel, BelongsToTenant;
    use HasFactory;

    public const EMISSAO_INICIAL = 'EMISSAO_INICIAL';

    public const DEVOLUCAO_PARCIAL = 'DEVOLUCAO_PARCIAL';

    public const REEMISSAO = 'REEMISSAO';

    public const ACRESCIMO = 'ACRESCIMO';

    public const RENOVACAO = 'RENOVACAO';

    protected $table = 'comodato_contratos';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'comodato_id', 'versao',
        'quantidade_contratada', 'quantidade_devolvida', 'quantidade_em_posse',
        'motivo', 'movimento_id', 'assinado_em', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_contratada' => 'decimal:3',
            'quantidade_devolvida' => 'decimal:3',
            'quantidade_em_posse' => 'decimal:3',
            'assinado_em' => 'datetime',
        ];
    }

    public function comodato(): BelongsTo
    {
        return $this->belongsTo(Comodato::class);
    }

    public function movimento(): BelongsTo
    {
        return $this->belongsTo(ComodatoMovimento::class, 'movimento_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
