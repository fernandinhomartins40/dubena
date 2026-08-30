<?php

namespace App\Domain\Saas;

/**
 * Catálogo único de RECURSOS (feature-flags) da plataforma SaaS — P2.
 *
 * É a FONTE DA VERDADE dos recursos vendáveis: planos liberam recursos por
 * `recurso_chave`, o middleware `recurso:` barra rotas por chave, e o /me expõe
 * as features efetivas do tenant. Espelha o papel do PermissaoCatalogo no RBAC.
 *
 * Distinção importante:
 *  - PERMISSÃO (PermissaoCatalogo): o que o USUÁRIO pode fazer (RBAC, por papel).
 *  - RECURSO (este catálogo): o que a EMPRESA contratou (licença, por plano).
 * Um usuário precisa de AMBOS: ter a permissão E a empresa ter o recurso.
 *
 * Adicionar um recurso aqui já o inclui no seeder e no contrato — declarar a
 * chave é o único passo para um novo feature-flag existir.
 */
final class RecursoCatalogo
{
    /**
     * Recurso => descrição amigável. A chave é estável (usada em banco/rota/me).
     *
     * @var array<string, string>
     */
    public const RECURSOS = [
        'marketplace' => 'Marketplace — descoberta da empresa por geolocalização no app',
        'app_consumidor' => 'App do consumidor — pedidos pelo aplicativo',
        'app_entregador' => 'App do entregador — entregas pelo aplicativo',
        'tempo_real' => 'Tempo real — rastreamento e notificações ao vivo',
        'nfce' => 'NFC-e / NF-e — emissão fiscal',
        'monitora' => 'Monitoramento GPS — rastreamento de veículos',
        'cobranca' => 'Cobrança registrada — boleto (CNAB) e PIX',
        'crm' => 'CRM — pós-venda, promoções, sorteios, metas, checklist',
        'frota' => 'Frota — gestão de veículos e manutenção',
        'relatorios_avancados' => 'Relatórios avançados — DRE, SPED e análises',
    ];

    /**
     * Limites NUMÉRICOS por plano (F2-03) — chave => descrição.
     *
     * Recurso responde "tem ou não tem"; limite responde "até quanto". Num SaaS
     * é o limite que separa a revenda de bairro da rede com 11 unidades, então
     * ele é metade da grade comercial.
     *
     * A contagem de cada limite vive em `LimiteCatalogo::contar()`, que é onde
     * se define o que exatamente está sendo contado.
     *
     * @var array<string, string>
     */
    public const LIMITES = [
        'empresas' => 'Unidades (empresas) ativas no tenant',
        'usuarios' => 'Usuários ativos por empresa',
        'veiculos_monitorados' => 'Veículos com rastreamento GPS ativo',
    ];

    /**
     * Todas as chaves de recurso.
     *
     * @return list<string>
     */
    public static function chaves(): array
    {
        return array_keys(self::RECURSOS);
    }

    /**
     * Todas as chaves de limite.
     *
     * @return list<string>
     */
    public static function chavesDeLimite(): array
    {
        return array_keys(self::LIMITES);
    }

    /** A chave de limite existe no catálogo? */
    public static function limiteExiste(string $chave): bool
    {
        return array_key_exists($chave, self::LIMITES);
    }

    /** A chave existe no catálogo? */
    public static function existe(string $chave): bool
    {
        return array_key_exists($chave, self::RECURSOS);
    }

    /**
     * Mapa chave => descrição.
     *
     * @return array<string, string>
     */
    public static function comDescricoes(): array
    {
        return self::RECURSOS;
    }
}
