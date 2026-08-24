<?php

namespace App\Models;

use App\Domain\Shared\Auditavel;
use App\Domain\Tenant\BelongsToTenant;
use App\Models\Cliente\Cliente;
use App\Models\Satelite\Comodato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um item da central de alertas — a fila de averiguação da equipe.
 *
 * Genérico por `origem`: nasce servindo o comodato, mas estoque baixo e
 * inconsistência de saldo cabem aqui depois. A alternativa — cada domínio com
 * sua fila — daria três telas para o mesmo gesto de triagem.
 *
 * `Auditavel` porque a triagem é decisão de pessoa: quem ignorou um alerta de
 * desvio patrimonial, e quando, é informação que precisa sobreviver.
 */
class Alerta extends Model
{
    use Auditavel, BelongsToTenant;

    public const ABERTO = 'ABERTO';

    public const EM_ANALISE = 'EM_ANALISE';

    public const RESOLVIDO = 'RESOLVIDO';

    public const IGNORADO = 'IGNORADO';

    protected $table = 'alertas';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'origem', 'severidade', 'titulo', 'descricao',
        'cliente_id', 'comodato_id', 'dados', 'situacao', 'chave',
        'responsavel_user_id', 'resolucao', 'resolvido_em', 'resolvido_por',
        'ocorrencias', 'ultima_ocorrencia',
    ];

    protected function casts(): array
    {
        return [
            'dados' => 'array',
            'resolvido_em' => 'datetime',
            'ultima_ocorrencia' => 'datetime',
            'ocorrencias' => 'integer',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function comodato(): BelongsTo
    {
        return $this->belongsTo(Comodato::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    /** Ainda pede ação de alguém. */
    public function pendente(): bool
    {
        return in_array($this->situacao, [self::ABERTO, self::EM_ANALISE], true);
    }
}
