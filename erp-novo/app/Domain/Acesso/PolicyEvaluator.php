<?php

namespace App\Domain\Acesso;

use App\Domain\Tenant\TenantContext;
use App\Models\PermissionCondition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Avaliador ABAC (A4) — o "permite(user, acao, recurso)" do plano.
 *
 *   permite = RBAC.tem(user, acao)
 *             E escopoCobre(user, recurso)        (hierarquia da A3)
 *             E condições(papel, permissão, recurso).todas_satisfeitas
 *
 * Sem recurso, comporta-se como o RBAC puro (não quebra o que já existe): só a
 * checagem de permissão roda. O recurso, quando passado, habilita o escopo
 * hierárquico e as condições de atributo (limite/ownership/horário).
 *
 * O suporte (bypass) é tratado ANTES, no Gate::before — aqui assume-se um usuário
 * comum (o evaluator nunca é a única barreira: roda sobre RBAC + RLS).
 */
class PolicyEvaluator
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * @param  array<string,mixed>|Model|null  $recurso
     */
    public function permite(User $user, string $ability, array|Model|null $recurso = null): bool
    {
        if ($user->support) {
            return true;
        }

        $empresaId = $this->tenant->empresaId() ?? $user->empresa_id;

        // 1) RBAC: precisa ter a permissão pela via normal.
        if (! $user->temPermissao($ability, $empresaId)) {
            return false;
        }

        // Sem recurso, paramos no RBAC (compatível com o enforcement atual).
        if ($recurso === null) {
            return true;
        }

        // Papéis do usuário (na empresa ativa) que CONCEDEM esta ability — são os
        // únicos relevantes para escopo e condições.
        $papeis = $this->papeisQueConcedem($user, $ability, $empresaId);
        if ($papeis->isEmpty()) {
            return false;
        }

        // 2) Escopo hierárquico (A3): ao menos um papel concedente cobre o recurso.
        if (! $papeis->contains(fn (Role $r) => $this->escopoCobre($r, $recurso))) {
            return false;
        }

        // 3) Condições ABAC: TODAS as condições ativas (de qualquer papel concedente
        // que cubra o recurso) devem ser satisfeitas.
        return $this->condicoesSatisfeitas($papeis, $ability, $recurso, $empresaId);
    }

    /**
     * Papéis efetivos do usuário (na empresa) cujas permissões incluem a ability.
     *
     * @return Collection<int, Role>
     */
    private function papeisQueConcedem(User $user, string $ability, ?int $empresaId)
    {
        return $user->papeisEfetivos($empresaId)
            ->filter(fn (Role $r) => $r->permissions->contains('chave', $ability))
            ->values();
    }

    /**
     * O escopo da atribuição do papel cobre o recurso? Regra:
     *  - papel sem escopo (todos os nós nulos) = empresa inteira → cobre tudo;
     *  - com escopo, o recurso precisa bater no nó (ou descender dele, se herda_filhos).
     *  - recurso sem o atributo de escopo correspondente = não restringe por aquele nível.
     *
     * @param  array<string,mixed>|Model  $recurso
     */
    private function escopoCobre(Role $papel, array|Model $recurso): bool
    {
        $pivot = $papel->pivot ?? null;
        if ($pivot === null) {
            return true; // sem pivot carregado (papel global) → não restringe por escopo
        }

        $setor = $pivot->setor_id;
        $depto = $pivot->departamento_id;
        $unidade = $pivot->unidade_id;

        // Atribuição sem escopo = empresa inteira.
        if ($setor === null && $depto === null && $unidade === null) {
            return true;
        }

        $rSetor = $this->attr($recurso, 'setor_id');
        $rDepto = $this->attr($recurso, 'departamento_id');
        $rUnidade = $this->attr($recurso, 'unidade_id');

        // Escopo de setor: o mais específico. Casa direto pelo setor.
        if ($setor !== null) {
            return $rSetor !== null && (int) $rSetor === (int) $setor;
        }

        // Escopo de departamento.
        if ($depto !== null) {
            if ($rDepto !== null && (int) $rDepto === (int) $depto) {
                return true;
            }

            // herda_filhos: um recurso de setor abaixo deste depto também é coberto.
            return false;
        }

        // Escopo de unidade.
        if ($unidade !== null) {
            return $rUnidade !== null && (int) $rUnidade === (int) $unidade;
        }

        return true;
    }

    /**
     * @param  Collection<int, Role>  $papeis
     * @param  array<string,mixed>|Model  $recurso
     */
    private function condicoesSatisfeitas($papeis, string $ability, array|Model $recurso, ?int $empresaId): bool
    {
        // Carrega as condições ativas dos papéis concedentes para esta permissão.
        $roleIds = $papeis->pluck('id')->all();

        $condicoes = PermissionCondition::query()
            ->where('ativo', true)
            ->whereIn('role_id', $roleIds)
            ->whereHas('permission', fn ($q) => $q->where('chave', $ability))
            ->get();

        foreach ($condicoes as $cond) {
            if (! $this->avaliarCondicao($cond, $recurso)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string,mixed>|Model  $recurso
     */
    private function avaliarCondicao(PermissionCondition $cond, array|Model $recurso): bool
    {
        $p = $cond->parametros ?? [];

        return match ($cond->tipo) {
            'limite' => $this->avaliarLimite($p, $recurso),
            'ownership' => $this->avaliarOwnership($p, $recurso),
            'horario' => $this->avaliarHorario($p),
            default => true, // tipo desconhecido não bloqueia (forward-compatible)
        };
    }

    /** @param array<string,mixed> $p @param array<string,mixed>|Model $recurso */
    private function avaliarLimite(array $p, array|Model $recurso): bool
    {
        $campo = $p['campo'] ?? 'valor';
        $max = $p['valor_max'] ?? null;
        if ($max === null) {
            return true;
        }
        $valor = $this->attr($recurso, $campo);

        return $valor === null || (float) $valor <= (float) $max;
    }

    /** @param array<string,mixed> $p @param array<string,mixed>|Model $recurso */
    private function avaliarOwnership(array $p, array|Model $recurso): bool
    {
        $campoDono = $p['campo_dono'] ?? 'user_id';
        $userId = auth()->id();
        $dono = $this->attr($recurso, $campoDono);

        // Sem dono no recurso → não bloqueia (a condição não se aplica a este recurso).
        return $dono === null || (int) $dono === (int) $userId;
    }

    /** @param array<string,mixed> $p */
    private function avaliarHorario(array $p): bool
    {
        $de = $p['de'] ?? null;   // 'HH:MM'
        $ate = $p['ate'] ?? null; // 'HH:MM'
        if ($de === null || $ate === null) {
            return true;
        }
        $agora = now()->format('H:i');

        // Janela normal (de <= ate). Janela que cruza a meia-noite também tratada.
        if ($de <= $ate) {
            return $agora >= $de && $agora <= $ate;
        }

        return $agora >= $de || $agora <= $ate;
    }

    /**
     * Lê um atributo do recurso (array ou Model), tolerante à ausência.
     *
     * @param  array<string,mixed>|Model  $recurso
     */
    private function attr(array|Model $recurso, string $chave): mixed
    {
        if ($recurso instanceof Model) {
            return $recurso->getAttribute($chave);
        }

        return $recurso[$chave] ?? null;
    }
}
