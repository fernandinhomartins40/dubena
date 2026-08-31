<?php

namespace App\Domain\Acesso;

use App\Domain\Shared\PermissaoCatalogo;
use App\Domain\Tenant\TenantContext;
use App\Models\User;

/**
 * Field-level (A7) — controla quais CAMPOS um usuário vê/edita, via permissões
 * `modulo.campo.{nome}.{view|edit}`.
 *
 * Convenção: um campo sensível SÓ é restrito se existir a chave correspondente
 * no catálogo. Campos sem chave declarada são livres (não restritos) — assim a
 * granularidade entra incrementalmente, sem quebrar o que já existe.
 *
 * F2-07 — o CATÁLOGO é a única fonte de quais campos são controlados.
 *
 * Antes, a lista vivia em dois lugares: aqui e, de novo, numa constante dentro
 * de cada resource que filtra. Declarar `cliente.campo.documento.view` no
 * catálogo não protegia nada até alguém lembrar de editar a constante — uma
 * permissão que existe, aparece na tela de papéis, pode ser negada, e não
 * esconde o campo. Isso é pior do que não ter a permissão, porque afirma uma
 * proteção que não acontece.
 *
 *  - leitura:  sem `...campo.{nome}.view`  → o campo é REMOVIDO do payload.
 *  - escrita:  sem `...campo.{nome}.edit`  → o campo é IGNORADO na gravação.
 *
 * Suporte (bypass) enxerga/edita tudo (temPermissao já devolve true).
 */
class CamposPermitidos
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * Remove do array de saída os campos que o usuário não pode VER.
     *
     * @param  array<string, mixed>  $dados
     * @param  list<string>|null  $camposSensiveis  null = todos os campos que o
     *                                              catálogo declara para o módulo
     * @return array<string, mixed>
     */
    public function filtrarLeitura(?User $user, string $modulo, array $dados, ?array $camposSensiveis = null): array
    {
        foreach ($camposSensiveis ?? $this->camposControlados($modulo) as $campo) {
            if (! $this->pode($user, $modulo, $campo, 'view')) {
                unset($dados[$campo]);
            }
        }

        return $dados;
    }

    /**
     * Remove do array de entrada os campos que o usuário não pode EDITAR.
     *
     * @param  array<string, mixed>  $dados
     * @param  list<string>|null  $camposSensiveis  null = derivar do catálogo
     * @return array<string, mixed>
     */
    public function filtrarEscrita(?User $user, string $modulo, array $dados, ?array $camposSensiveis = null): array
    {
        foreach ($camposSensiveis ?? $this->camposControlados($modulo) as $campo) {
            if (array_key_exists($campo, $dados) && ! $this->pode($user, $modulo, $campo, 'edit')) {
                unset($dados[$campo]);
            }
        }

        return $dados;
    }

    /**
     * Campos que o catálogo declara como controlados para o módulo.
     *
     * É daqui que os filtros descobrem o que proteger — e é o que garante que
     * uma chave nova no catálogo passe a valer sem depender de ninguém lembrar
     * de atualizar uma segunda lista.
     *
     * @return list<string>
     */
    public function camposControlados(string $modulo): array
    {
        $campos = [];

        foreach (array_keys(PermissaoCatalogo::GRANULARES) as $chave) {
            if (preg_match('/^'.preg_quote($modulo, '/').'\.campo\.([a-z_]+)\.(?:view|edit)$/', $chave, $m) === 1) {
                $campos[$m[1]] = true;
            }
        }

        return array_keys($campos);
    }

    /**
     * O usuário pode `view|edit` o campo? Se a chave NÃO existe no catálogo, o
     * campo não é controlado (livre). Se existe, exige a permissão.
     */
    public function pode(?User $user, string $modulo, string $campo, string $acao): bool
    {
        $chave = "{$modulo}.campo.{$campo}.{$acao}";

        if (! array_key_exists($chave, PermissaoCatalogo::GRANULARES)) {
            return true; // campo não controlado
        }

        return $user !== null
            && $user->temPermissao($chave, $this->tenant->empresaId() ?? (int) $user->empresa_id);
    }
}
