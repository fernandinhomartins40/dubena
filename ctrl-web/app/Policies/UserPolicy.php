<?php

namespace App\Policies;

use Session;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Permissões de Usuário (menu 'user.index'). Mesma estratégia da Fase 3
 * (Cidade/BairroPolicy): resolve pela Session('permissoes') quando existir
 * (fluxo AdminLTE) e, na ausência, por User::podeNoMenu (banco — fluxo Filament).
 *
 * NOTA: a versão legada lia Session('empresa_padrao')->id no construtor, o que
 * causava erro fatal quando a Session não estava montada (ex.: painel Filament).
 * Removido — a resolução de empresa fica em podeNoMenu (empresa do usuário).
 */
class UserPolicy
{
    use HandlesAuthorization;

    private const ROTA = 'user.index';

    public function viewAny(User $user)
    {
        return $this->pode($user, 'visualizar');
    }

    public function view(User $user, User $model = null)
    {
        return $this->pode($user, 'visualizar');
    }

    public function create(User $user)
    {
        return $this->pode($user, 'criar');
    }

    public function update(User $user, User $model = null)
    {
        return $this->pode($user, 'editar');
    }

    public function delete(User $user, User $model = null)
    {
        return $this->pode($user, 'deletar');
    }

    public function deleteAny(User $user)
    {
        return $this->pode($user, 'deletar');
    }

    /** Mantido do legado: o usuário é ele mesmo (usado em telas de perfil). */
    public function igualdade(User $user)
    {
        return \Auth::id() == $user->id;
    }

    private function pode(User $user, string $flag): bool
    {
        $permissoes = Session::get('permissoes');

        if ($permissoes !== null) {
            $p = $permissoes->where('descricao', self::ROTA)->first();
            return $p !== null && (int) $p->{$flag} === 1;
        }

        return $user->podeNoMenu(self::ROTA, $flag);
    }
}
