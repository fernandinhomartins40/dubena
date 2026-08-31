<?php

namespace App\Models\Cliente;

use App\Domain\Cliente\PapelPessoa;
use App\Domain\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Papel de uma pessoa, com vigência (F3-01).
 *
 * Substitui os booleanos paralelos `cliente`/`fornecedor`/`transportador`, que
 * respondiam "é?" e não conseguiam responder "era, quando?".
 *
 * O caso que motivou: um fornecedor que deixou de fornecer não tinha como sair
 * da lista sem apagar o histórico — desmarcar o booleano faz parecer que ele
 * nunca forneceu, e as notas de entrada antigas passam a apontar para alguém
 * que "não é fornecedor".
 */
class ClientePapel extends Model
{
    use BelongsToTenant;

    protected $table = 'cliente_papeis';

    protected $fillable = [
        'empresa_id', 'grupo_id', 'tenant_account_id', 'cliente_id',
        'papel', 'inicio', 'fim',
    ];

    protected function casts(): array
    {
        return [
            'papel' => PapelPessoa::class,
            'inicio' => 'date',
            'fim' => 'date',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Papéis vigentes hoje.
     *
     * `fim` nulo é o vigente sem prazo. `fim` preenchido significa que o papel
     * ACABOU naquele dia, então `fim > hoje` — e não `>=`.
     *
     * A diferença importa e me custou um teste: encerrar um papel com a data de
     * hoje é o caso comum ("deixou de ser fornecedor agora"), e com `>=` ele
     * continuaria vigente até a virada do dia. O papel encerrado tem de sair da
     * lista no instante em que se encerra.
     *
     * Um papel que terminou continua na tabela — é justamente o histórico que os
     * booleanos não guardavam.
     */
    public function scopeVigentes(Builder $q): Builder
    {
        // `whereDate` e nao `where`: o valor gravado chega como
        // "2026-08-31 00:00:00" e, comparado como STRING com "2026-08-31", sai
        // maior — um papel encerrado hoje continuaria vigente. E a mesma
        // armadilha do `whereBetween` em coluna datetime registrada no
        // CLAUDE.md, e ela me custou um teste aqui.
        return $q->where(fn (Builder $w) => $w->whereNull('fim')->orWhereDate('fim', '>', now()->toDateString()));
    }
}
