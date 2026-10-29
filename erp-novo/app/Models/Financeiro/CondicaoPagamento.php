<?php

namespace App\Models\Financeiro;

use App\Models\Apoio\CadastroApoio;
use Illuminate\Validation\ValidationException;

/**
 * Condição de pagamento (à vista / N parcelas com intervalo) — C4.
 * Escopada por grupo (via CadastroApoio, que também a põe no CRUD genérico de
 * cadastros). O FinanceiroService usa num_parcelas/intervalo/dias_primeira para
 * parcelar o título do pedido.
 *
 * `pix_online`: o app só gera cobrança PIX para condição marcada (PIE R2-02).
 */
class CondicaoPagamento extends CadastroApoio
{
    protected $table = 'condicaopagamentos';

    protected $fillable = [
        'tenant_account_id', 'grupo_id', 'descricao', 'num_parcelas', 'intervalo_dias',
        'dias_primeira', 'a_vista', 'pix_online', 'ativo',
    ];

    /**
     * Condição não se apaga, desativa-se: pedidos, títulos, alçadas e a config
     * da empresa a referenciam, e o CRUD genérico de cadastros faz delete
     * físico — que deixaria órfãos (ou estouraria a FK com 500).
     */
    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw ValidationException::withMessages([
                'condicao' => 'Condição de pagamento não pode ser excluída: desative-a.',
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'num_parcelas' => 'integer',
            'intervalo_dias' => 'integer',
            'dias_primeira' => 'integer',
            'a_vista' => 'boolean',
            'pix_online' => 'boolean',
            'ativo' => 'boolean',
        ];
    }
}
