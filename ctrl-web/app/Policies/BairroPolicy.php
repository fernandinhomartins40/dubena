<?php

namespace App\Policies;

use Session;
use App\User;
use App\Bairro;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Permissões de Bairro. Fonte legada: flags em `menuusers` para o menu
 * 'bairro.index'. Ver CidadePolicy para a estratégia Session→banco (Fase 3).
 */
class BairroPolicy
{
    use HandlesAuthorization;

    private const ROTA = 'bairro.index';

    public function viewAny(User $user)
    {
        return $this->pode($user, 'visualizar');
    }

    public function view(User $user, Bairro $bairro = null)
    {
        return $this->pode($user, 'visualizar');
    }

    public function create(User $user)
    {
        return $this->pode($user, 'criar');
    }

    public function update(User $user, Bairro $bairro = null)
    {
        return $this->pode($user, 'editar');
    }

    public function delete(User $user, Bairro $bairro = null)
    {
        return $this->pode($user, 'deletar');
    }

    public function deleteAny(User $user)
    {
        return $this->pode($user, 'deletar');
    }

    private function pode(User $user, string $flag): bool
    {
        $acaoRbac = ['visualizar' => 'view', 'criar' => 'create', 'editar' => 'edit', 'deletar' => 'delete'][$flag] ?? 'view';

        $permissoes = Session::get('permissoes');
        if ($permissoes !== null) {
            $p = $permissoes->where('descricao', self::ROTA)->first();
            if ($p !== null && (int) $p->{$flag} === 1) {
                return true;
            }
        }

        return $user->podeRecurso('bairro', $acaoRbac);
    }
}
