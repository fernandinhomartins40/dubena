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
        'pedido' => ['view', 'create', 'edit', 'delete'],
        'financeiro' => ['view', 'create', 'edit', 'delete'],
        'caixa' => ['view', 'edit'],
        'fiscal' => ['view', 'edit', 'emitir'],
        'convenio' => ['view', 'edit'],
        'valegas' => ['view', 'edit'],
        'comodato' => ['view', 'edit'],
        'monitora' => ['view', 'edit'],
        'colaborador' => ['view', 'create', 'edit', 'delete'],
        'veiculo' => ['view', 'create', 'edit', 'delete'],
        'posvenda' => ['view', 'create', 'edit', 'delete'],
        'promocao' => ['view', 'create', 'edit', 'delete'],
        'sorteio' => ['view', 'create', 'edit', 'delete'],
        'meta' => ['view', 'create', 'edit', 'delete'],
        'checklist' => ['view', 'create', 'edit', 'delete'],
        'empresa' => ['view', 'create', 'edit', 'delete'],
        'grupo' => ['view', 'create', 'edit', 'delete'],
        'relatorio' => ['view'],
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

        return $mapa;
    }
}
