<?php

namespace App\Domain\Shared;

/**
 * Catálogo único de permissões "modulo.acao" do sistema (C1).
 *
 * É a FONTE DA VERDADE do RBAC: o RbacSeeder popula a tabela `permissions` a
 * partir daqui, e o teste de contrato garante que toda chave usada nos
 * controllers (`temPermissao('x.y')`) existe neste catálogo. Assim a SPA recebe
 * em /me um conjunto de permissões que casa 1:1 com o que o backend exige.
 */
final class PermissaoCatalogo
{
    /**
     * Ações por módulo. Cada par vira a chave "modulo.acao".
     *
     * @var array<string, list<string>>
     */
    public const MODULOS = [
        'cliente' => ['view', 'create', 'edit', 'delete'],
        'produto' => ['view', 'create', 'edit', 'delete', 'config', 'preco'],
        'estoque' => ['view', 'edit'],
        'pedido' => ['view', 'create', 'edit', 'delete', 'aprovar'], // aprovar = verbo sensível (ABAC)
        'pedidosituacao' => ['view', 'create', 'edit', 'delete'], // colunas do Kanban (situações)
        'financeiro' => ['view', 'create', 'edit', 'delete', 'baixar'], // baixar = verbo sensível (ABAC)
        'caixa' => ['view', 'edit', 'fechar', 'estornar'], // verbos sensíveis (ABAC: limite/ownership)
        'fiscal' => ['view', 'edit', 'emitir'],
        'convenio' => ['view', 'edit'],
        'valegas' => ['view', 'edit'],
        // `estornar` é separado de `edit` porque desfaz uma devolução JÁ
        // recebida: o vasilhame volta a constar em poder do cliente e o estoque
        // é baixado de novo. Quem registra a entrega no balcão não precisa
        // poder anulá-la.
        'comodato' => ['view', 'edit', 'estornar', 'config'],
        // Central de alertas -- fila de averiguacao. `triar` e separado de `view`
        // porque encerrar um alerta de suspeita de desvio patrimonial e decisao,
        // nao consulta: quem ignora precisa responder por isso.
        'alerta' => ['view', 'triar'],
        'monitora' => ['view', 'edit'],
        // Central de Logística (L1) — fila, atribuição, redistribuição, bloqueio.
        'logistica' => ['view', 'distribuir', 'config'],
        // Central de VENDAS (F3) — fila de solicitações do campo e a decisão sobre
        // elas. Separada da logística: uma decide quem leva, a outra se vende e
        // por quanto. `aprovar` e `faturar` são verbos distintos de propósito —
        // quem libera desconto não é necessariamente quem fecha a venda.
        'venda' => ['view', 'aprovar', 'faturar', 'alcada'],
        // Missões de campo (L7/L9) — molde, execução e auditoria.
        'missao' => ['view', 'create', 'edit', 'aprovar'],
        'colaborador' => ['view', 'create', 'edit', 'delete'],
        'veiculo' => ['view', 'create', 'edit', 'delete'],
        'posvenda' => ['view', 'create', 'edit', 'delete'],
        'promocao' => ['view', 'create', 'edit', 'delete'],
        'sorteio' => ['view', 'create', 'edit', 'delete'],
        'meta' => ['view', 'create', 'edit', 'delete'],
        'checklist' => ['view', 'create', 'edit', 'delete'],
        'cupomfiscal' => ['view', 'create', 'edit'],
        'mcmm' => ['view', 'create', 'edit', 'delete'],
        'documento' => ['view', 'create', 'edit', 'delete'],
        'bem' => ['view', 'create', 'edit', 'delete'],
        'cartao' => ['view', 'create'],
        'gasdopovo' => ['view', 'create', 'edit'],
        'empresa' => ['view', 'create', 'edit', 'delete'],
        'grupo' => ['view', 'create', 'edit', 'delete'],
        // Cidades da plataforma (P3) — catálogo + vínculo empresa↔cidade.
        'cidade' => ['view', 'create', 'edit', 'delete'],
        'relatorio' => ['view'],
        // Central de Acessos (A2) — administração de usuários e papéis.
        'usuario' => ['view', 'create', 'edit', 'delete', 'reset'],
        'papel' => ['view', 'create', 'edit', 'delete'],
        // Estrutura organizacional (A3) — árvore unidade → departamento → setor.
        'unidade' => ['view', 'create', 'edit', 'delete'],
        'departamento' => ['view', 'create', 'edit', 'delete'],
        'setor' => ['view', 'create', 'edit', 'delete'],
        // Auditoria de segurança (A6) — trilha de eventos e histórico de papéis.
        'auditoria' => ['view'],
    ];

    /**
     * Permissões GRANULARES (A7) — chaves já formadas, fora do esquema
     * `modulo.acao` padrão (campos sensíveis, relatórios e export/import).
     * Entram incrementalmente: declarar aqui já as inclui no catálogo, no Gate,
     * no RbacSeeder e no contrato — sem explodir tudo de uma vez.
     *
     * Convenção (plano §6.1):
     *  - campo:    `modulo.campo.{nome}.{view|edit}`
     *  - relatório: `relatorio.{slug}.view`
     *  - export/import: `modulo.export` / `modulo.import`
     *
     * @var array<string, string> chave => descrição
     */
    public const GRANULARES = [
        // Campos sensíveis do cliente (crédito/convênio).
        'cliente.campo.credito_limite.view' => 'Cliente — Ver limite de crédito',
        'cliente.campo.credito_limite.edit' => 'Cliente — Editar limite de crédito',
        'cliente.campo.credito_saldo.view' => 'Cliente — Ver saldo de crédito',
        'cliente.campo.convenio_limite.view' => 'Cliente — Ver limite de convênio',
        'cliente.campo.convenio_limite.edit' => 'Cliente — Editar limite de convênio',
        'produto.campo.custo.view' => 'Produto — Ver custos',
        'produto.campo.custo.edit' => 'Produto — Editar custos',
        // Export/import.
        'cliente.export' => 'Cliente — Exportar',
        'produto.export' => 'Produto — Exportar',
        // Relatórios específicos.
        'relatorio.dre.view' => 'Relatório — DRE',
        'relatorio.vendas.view' => 'Relatório — Vendas',
    ];

    /**
     * Descrições amigáveis por ação (para a coluna `descricao`).
     *
     * @var array<string, string>
     */
    private const ACOES = [
        'view' => 'Visualizar',
        'create' => 'Criar',
        'edit' => 'Editar',
        'delete' => 'Excluir',
        'config' => 'Configurar',
        'preco' => 'Gerir preços',
        'emitir' => 'Emitir',
        'reset' => 'Resetar senha',
        'export' => 'Exportar',
        'import' => 'Importar',
        'aprovar' => 'Aprovar',
        'baixar' => 'Baixar (financeiro)',
        'fechar' => 'Fechar',
        'estornar' => 'Estornar',
        'distribuir' => 'Distribuir entregas',
        'faturar' => 'Faturar venda',
        'alcada' => 'Gerir alçadas de desconto',
        'triar' => 'Triar alertas',
    ];

    /**
     * Todas as chaves "modulo.acao" do sistema.
     *
     * @return list<string>
     */
    public static function chaves(): array
    {
        $chaves = [];
        foreach (self::MODULOS as $modulo => $acoes) {
            foreach ($acoes as $acao) {
                $chaves[] = "{$modulo}.{$acao}";
            }
        }

        // Granulares (A7) — campos/relatórios/export.
        $chaves = array_merge($chaves, array_keys(self::GRANULARES));

        return $chaves;
    }

    /**
     * Mapa chave => descrição (ex.: 'caixa.edit' => 'Caixa — Editar').
     *
     * @return array<string, string>
     */
    public static function comDescricoes(): array
    {
        $mapa = [];
        foreach (self::MODULOS as $modulo => $acoes) {
            foreach ($acoes as $acao) {
                $rotulo = ucfirst($modulo);
                $mapa["{$modulo}.{$acao}"] = "{$rotulo} — ".(self::ACOES[$acao] ?? ucfirst($acao));
            }
        }

        // Granulares (A7) já vêm com descrição própria.
        $mapa = array_merge($mapa, self::GRANULARES);

        return $mapa;
    }
}
