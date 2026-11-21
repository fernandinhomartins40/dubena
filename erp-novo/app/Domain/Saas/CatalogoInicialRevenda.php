<?php

namespace App\Domain\Saas;

use App\Domain\Estoque\TipoLocalEstoque;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PapelSituacao;
use App\Models\Apoio\TelefoneTipo;
use App\Models\Apoio\TipoPessoa;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Pedido\PedidoOperacao;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Produto\ProdutoClasse;
use App\Models\Produto\UnidadeMedida;

/**
 * PIE P2-A-07 / P2-D-13 — o mínimo para a revenda nova registrar o 1º pedido.
 *
 * Extraído do `DemoGuarapuavaSeeder`, sem a massa de demonstração: só o que
 * falta numa base vazia e que o dono não sabe que precisa. Sem condição de
 * pagamento o Novo pedido não fecha (P2-D-13); sem situação com papel
 * `EM_ROTA` o app do entregador falha ao iniciar a rota.
 *
 * Preço ZERO de propósito: preço é decisão da revenda, não da plataforma. O
 * checklist "pronto para vender" acusa produto sem preço até o dono preencher.
 *
 * Idempotente pela descrição: rodar duas vezes não duplica, e o que o dono já
 * renomeou não volta. Quem chama precisa ter o envelope do tenant ativo — é ele
 * que preenche a chave SaaS de cada linha (sem ela a policy canônica esconde o
 * cadastro do próprio dono, como na carga de 2026-10-11).
 */
final class CatalogoInicialRevenda
{
    /** @return array<string, int> quantas linhas cada cadastro tem depois de aplicar */
    public function aplicar(Empresa $empresa): array
    {
        $g = (int) $empresa->grupo_id;
        $e = (int) $empresa->id;
        $grupo = fn (string $classe, string $descricao, array $extra) => $classe::firstOrCreate(
            ['grupo_id' => $g, 'descricao' => $descricao],
            $extra + ['ativo' => true],
        );

        $grupo(TipoPessoa::class, 'Pessoa Física', ['tipopessoacadastro' => 'F']);
        $grupo(TipoPessoa::class, 'Pessoa Jurídica', ['tipopessoacadastro' => 'J']);
        $grupo(TelefoneTipo::class, 'Celular', ['celular' => true]);
        $grupo(TelefoneTipo::class, 'Residencial', ['celular' => false]);
        $grupo(TelefoneTipo::class, 'Comercial', ['celular' => false]);

        $grupo(PedidoOperacao::class, 'Venda', ['venda_direta' => true]);
        $grupo(PedidoOperacao::class, 'Disk', ['disk' => true]);

        $situacoes = [
            ['Em Aberto', EfeitoPedido::PENDENTE, '#F59E0B', PapelSituacao::NENHUM, true],
            ['Em Separação', EfeitoPedido::PENDENTE, '#3B82F6', PapelSituacao::NENHUM, false],
            ['Em Rota', EfeitoPedido::PENDENTE, '#A855F7', PapelSituacao::EM_ROTA, false],
            ['Concluído', EfeitoPedido::CONCLUIDO, '#22C55E', PapelSituacao::NENHUM, false],
            ['Cancelado', EfeitoPedido::CANCELADO, '#EF4444', PapelSituacao::NENHUM, false],
        ];
        foreach ($situacoes as $ordem => [$descricao, $efeito, $cor, $papel, $padrao]) {
            $grupo(PedidoSituacao::class, $descricao, [
                'efeito' => $efeito->value, 'papel' => $papel->value, 'cor' => $cor,
                'ordem' => $ordem, 'padrao_tela_pedido' => $padrao,
            ]);
        }

        // Sem PIX online: cobrança PIX exige a credencial do PSP da própria
        // revenda (fail-closed). O dono liga quando cadastrar a dele.
        $grupo(CondicaoPagamento::class, 'À vista', ['a_vista' => true, 'num_parcelas' => 1, 'intervalo_dias' => 0, 'dias_primeira' => 0]);
        $grupo(CondicaoPagamento::class, 'A prazo 30 dias', ['a_vista' => false, 'num_parcelas' => 1, 'intervalo_dias' => 30, 'dias_primeira' => 30]);

        foreach ([['Depósito', TipoLocalEstoque::DEPOSITO], ['Loja', TipoLocalEstoque::LOJA], ['Veículo 01', TipoLocalEstoque::VEICULO]] as [$descricao, $tipo]) {
            Setor::firstOrCreate(
                ['empresa_id' => $e, 'descricao' => $descricao],
                ['grupo_id' => $g, 'tipo' => $tipo->value, 'ativo' => true],
            );
        }

        $glp = $grupo(ProdutoClasse::class, 'GLP', []);
        $agua = $grupo(ProdutoClasse::class, 'Água', []);
        $grupo(ProdutoClasse::class, 'Acessórios', []);
        $un = $grupo(UnidadeMedida::class, 'Unidade', ['sigla' => 'UN']);

        foreach ([['Gás GLP P13', $glp], ['Gás GLP P20', $glp], ['Gás GLP P45', $glp], ['Água mineral 20 L', $agua]] as [$descricao, $classe]) {
            Produto::firstOrCreate(
                ['empresa_id' => $e, 'descricao' => $descricao],
                [
                    'grupo_id' => $g, 'produtoclasse_id' => $classe->id, 'unidademedida_id' => $un->id,
                    'preco_venda' => 0, 'ativo' => true,
                ],
            );
        }

        return [
            'situacoes' => PedidoSituacao::withoutGrupo()->where('grupo_id', $g)->count(),
            'condicoes_pagamento' => CondicaoPagamento::withoutGrupo()->where('grupo_id', $g)->count(),
            'setores' => Setor::withoutTenant()->where('empresa_id', $e)->count(),
            'produtos' => Produto::withoutTenant()->where('empresa_id', $e)->count(),
        ];
    }
}
