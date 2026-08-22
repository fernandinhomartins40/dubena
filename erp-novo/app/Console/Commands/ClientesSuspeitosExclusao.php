<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * clientes:suspeitos-exclusao — RELATÓRIO, não corretor.
 *
 * Enquanto "excluir" apagava de verdade (e o Postgres recusava para quem tinha
 * pedido), o operador não tinha como tirar um cadastro da lista. A saída foi
 * marcar o estado no NOME: "FULANO - EXCLUIDO", "MARIA (MUDOU DE ENDERECO)".
 * Isso destrói o nome real do cliente — que é dado fiscal.
 *
 * Com a desativação implementada, estes cadastros podem ser desativados de
 * verdade e ter o nome restaurado. Este comando LISTA os candidatos; a decisão
 * de mexer em cada um é humana, porque o padrão gera falso positivo (um cliente
 * pode legitimamente se chamar "Mudou Comércio Ltda").
 *
 * NÃO altera nada. Use --csv para revisar em planilha.
 */
class ClientesSuspeitosExclusao extends Command
{
    protected $signature = 'clientes:suspeitos-exclusao {--csv= : grava o resultado neste arquivo}';

    protected $description = 'Lista clientes cujo NOME foi usado para marcar exclusão/mudança (não altera nada).';

    /**
     * Marcas que o operador usava no nome. ILIKE com % dos dois lados: a marca
     * costuma vir no fim ("JOAO - EXCLUIDO"), mas nem sempre.
     */
    private const MARCAS = [
        '%exclui%', '%excluid%', '%nao usar%', '%não usar%', '%nao use%',
        '%mudou%', '%mudanca%', '%mudança%', '%trocou de endereco%', '%trocou de endereço%',
        '%duplicad%', '%cancelad%', '%inativ%', '%desativad%', '%nao existe%', '%não existe%',
        '%deletad%', '%apagad%', '%obsolet%', '%antigo cadastro%', '%cadastro antigo%',
    ];

    public function handle(): int
    {
        $linhas = DB::table('clientes')
            ->where(function ($q) {
                foreach (self::MARCAS as $marca) {
                    $q->orWhere('nome', 'ilike', $marca);
                }
            })
            ->orderBy('nome')
            ->get(['id', 'nome', 'cpf', 'cnpj', 'ativo', 'desativado_em']);

        if ($linhas->isEmpty()) {
            $this->info('Nenhum cadastro com marca de exclusão no nome. Nada a revisar.');

            return self::SUCCESS;
        }

        // Quantos pedidos cada um tem: é o que diz se dá para desativar direto
        // ou se há histórico a conferir antes de mexer no nome.
        $pedidos = DB::table('pedidos')
            ->whereIn('cliente_id', $linhas->pluck('id'))
            ->groupBy('cliente_id')
            // Alias explícito: pluck() lê a coluna pelo NOME no stdClass, e
            // "count(*)" cru não vira nome de propriedade acessível.
            ->selectRaw('cliente_id, count(*) as total')
            ->pluck('total', 'cliente_id');

        $tabela = $linhas->map(fn ($c) => [
            $c->id,
            $c->nome,
            $c->cpf ?: $c->cnpj ?: '—',
            $c->ativo ? 'ativo' : 'desativado',
            (string) ($pedidos[$c->id] ?? 0),
        ])->all();

        $this->table(['ID', 'Nome (como está na base)', 'CPF/CNPJ', 'Situação', 'Pedidos'], $tabela);

        $this->newLine();
        $this->warn($linhas->count().' cadastro(s) com marca de exclusão no nome.');
        $this->line('Nenhuma alteração foi feita. Para cada um, o caminho agora é:');
        $this->line('  1. corrigir o nome para o nome real do cliente;');
        $this->line('  2. usar Desativar na tela de clientes (aba "Desativados" guarda o registro).');

        if ($csv = $this->option('csv')) {
            $saida = fopen($csv, 'w');
            fputcsv($saida, ['id', 'nome', 'documento', 'situacao', 'pedidos']);
            foreach ($tabela as $linha) {
                fputcsv($saida, $linha);
            }
            fclose($saida);
            $this->info("CSV gravado em {$csv}");
        }

        return self::SUCCESS;
    }
}
