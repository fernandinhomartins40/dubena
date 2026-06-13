<?php

namespace App\Policies;

use DB;
use Session;
use App\User;
use App\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the role.
     *
     * @param  \App\User  $user
     * @param  \App\Role  $role
     * @return mixed
     */
    public function view(User $user, Role $role)
    {
        $permissao = $this->getPermissao();

        if (is_null($permissao))
            return false;

        return $permissao->visualizar == 1;
    }

    /**
     * Determine whether the user can create roles.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissao = $this->getPermissao();

        if (is_null($permissao))
            return false;

        return $permissao->criar == 1;
    }

    /**
     * Determine whether the user can update the role.
     *
     * @param  \App\User  $user
     * @param  \App\Role  $role
     * @return mixed
     */
    public function update(User $user, Role $role)
    {
        $permissao = $this->getPermissao();

        if (is_null($permissao))
            return false;

        return $permissao->editar == 1;
    }

    /**
     * Determine whether the user can delete the role.
     *
     * @param  \App\User  $user
     * @param  \App\Role  $role
     * @return mixed
     */
    public function delete(User $user, Role $role)
    {
        $permissao = $this->getPermissao();

        if (is_null($permissao))
            return false;

        return $permissao->deletar == 1;
    }

    public function definirView(User $user)
    {
        $permissao = $this->getPermissao('definir.index');
        $tipo = $user->role;

        if (is_null($permissao) || !$tipo)
            return false;

        return $permissao->visualizar == 1 && $tipo->tipo_id == 1;
    }

    public function definirStore(User $user)
    {
        $permissao = $this->getPermissao('definir.index');
        $tipo = $user->role;

        if (is_null($permissao) || !$tipo)
            return false;

        return $permissao->criar == 1 && $tipo->tipo_id == 1;
    }

    public function canSee(User $user)
    {
        $tipo = $user->role;

        if (!$tipo)
            return false;

        return $tipo->tipo_id == 2 || $tipo->tipo_id == 1;
    }

    private function getPermissao($index = 'roles.index')
    {
        return Session::get('permissoes')->where('descricao', $index)->first();
    }
}
