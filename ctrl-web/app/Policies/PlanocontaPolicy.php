<?php

namespace App\Policies;

use Session;
use App\User;
use App\Planoconta;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlanocontaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the planoconta.
     *
     * @param  \App\User  $user
     * @param  \App\Planoconta  $planoconta
     * @return mixed
     */
    public function view(User $user, Planoconta $planoconta)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create planocontas.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the planoconta.
     *
     * @param  \App\User  $user
     * @param  \App\Planoconta  $planoconta
     * @return mixed
     */
    public function update(User $user, Planoconta $planoconta)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the planoconta.
     *
     * @param  \App\User  $user
     * @param  \App\Planoconta  $planoconta
     * @return mixed
     */
    public function delete(User $user, Planoconta $planoconta)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Planoconta $planoconta)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $grupo_id = Session::get('empresa_padrao')->grupo_id;

        $grupo = $planoconta->grupo_id == $grupo_id;

        return $permissoes->visualizar == 1 && $grupo;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','planoconta.index')->first();
    }
}
