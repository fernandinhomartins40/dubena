<?php

namespace App\Console\Commands;

use App\Domain\Produto\NaturezaItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * estoque:corrigir-servicos — limpa saldo de estoque de item que não é mercadoria.
 *
 * Enquanto serviço era cadastrado como produto, o PedidoService dava baixa nele
 * como em qualquer botijão. Medido em produção: "Manutenção e Instalação" com
 * saldo de **−2 unidades** — baixa de algo que nunca entrou e nunca vai entrar.
 *
 * O saldo é zerado e o histórico é PRESERVADO: apagar o movimento apagaria o
 * rastro de que a venda aconteceu. O que sai é só o saldo, que não deveria
 * existir.
 *
 * Somente leitura por default.
 */
class EstoqueCorrigirServicos extends Command
{
    protected $signature = 'estoque:corrigir-servicos
        {--executar : Aplica a correção. Sem esta flag, somente leitura.}';

    protected $description = 'Zera saldo de estoque de itens que são serviço ou taxa (não têm existência física).';

    public function handle(): int
    {
        $executar = (bool) $this->option('executar');

        $naoMercadoria = DB::table('produtos')
            ->whereIn('natureza', [NaturezaItem::SERVICO->value, NaturezaItem::TAXA->value])
            ->pluck('descricao', 'id');

        if ($naoMercadoria->isEmpty()) {
            $this->info('Nenhum item classificado como serviço ou taxa.');

            return self::SUCCESS;
        }

        $saldos = DB::table('estoquesaldos')
            ->whereIn('produto_id', $naoMercadoria->keys())
            ->get(['id', 'produto_id', 'setor_id', 'quantidade']);

        if ($saldos->isEmpty()) {
            $this->info($naoMercadoria->count().' item(ns) não-mercadoria, nenhum com saldo de estoque. Nada a corrigir.');

            return self::SUCCESS;
        }

        $this->table(
            ['Produto', 'Natureza', 'Setor', 'Saldo atual', 'Vira'],
            $saldos->map(fn ($s) => [
                mb_substr((string) $naoMercadoria[$s->produto_id], 0, 34),
                DB::table('produtos')->where('id', $s->produto_id)->value('natureza'),
                $s->setor_id,
                $s->quantidade,
                '0 (removido)',
            ])->all(),
        );

        if (! $executar) {
            $this->newLine();
            $this->warn('Somente leitura: nada foi alterado. Use --executar.');

            return self::SUCCESS;
        }

        // Remove o SALDO; o histórico de movimento fica, porque ele registra
        // que a venda de fato aconteceu.
        $removidos = DB::table('estoquesaldos')
            ->whereIn('produto_id', $naoMercadoria->keys())
            ->delete();

        $this->info("{$removidos} saldo(s) removido(s). O histórico de movimentos foi preservado.");

        return self::SUCCESS;
    }
}
