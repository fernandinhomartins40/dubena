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
     * @param  list<string>  $camposSensiveis  nomes de campo com controle field-level
     * @return array<string, mixed>
     */
    public function filtrarLeitura(?User $user, string $modulo, array $dados, array $camposSensiveis): array
    {
        foreach ($camposSensiveis as $campo) {
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
     * @param  list<string>  $camposSensiveis
     * @return array<string, mixed>
     */
    public function filtrarEscrita(?User $user, string $modulo, array $dados, array $camposSensiveis): array
    {
        foreach ($camposSensiveis as $campo) {
            if (array_key_exists($campo, $dados) && ! $this->pode($user, $modulo, $campo, 'edit')) {
                unset($dados[$campo]);
            }
        }

        return $dados;
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
