<?php

namespace App\Models\Saas;

use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Override de teto numérico por empresa (F2-03) — cortesia, piloto ou bloqueio.
 *
 * Sobrepõe o limite do plano. Tem `motivo` obrigatório e `expira_em` opcional:
 * override expirado deixa de valer sozinho, para que um piloto de 30 dias não
 * vire permanente por esquecimento.
 */
class LimiteOverride extends Model
{
    use BelongsToTenant;

    protected $table = 'limite_overrides';

    protected $fillable = ['empresa_id', 'limite_chave', 'valor', 'motivo', 'expira_em'];

    protected function casts(): array
    {
        return ['valor' => 'integer', 'expira_em' => 'datetime'];
    }

    /** Vigente = sem prazo, ou dentro do prazo. */
    public function vigente(): bool
    {
        return $this->expira_em === null || $this->expira_em->isFuture();
    }
}
