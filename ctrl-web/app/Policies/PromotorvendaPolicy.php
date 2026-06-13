<?php

namespace App\Policies;

use Session;
use App\User;
use App\Promotorvenda;
use Illuminate\Auth\Access\HandlesAuthorization;

class PromotorvendaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the promotorvenda.
     *
     * @param  \App\User  $user
     * @param  \App\Promotorvenda  $promotorvenda
     * @return mixed
     */
    public function view(User $user, Promotorvenda $promotorvenda)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create promotorvendas.
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
     * Determine whether the user can update the promotorvenda.
     *
     * @param  \App\User  $user
     * @param  \App\Promotorvenda  $promotorvenda
     * @return mixed
     */
    public function update(User $user, Promotorvenda $promotorvenda)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the promotorvenda.
     *
     * @param  \App\User  $user
     * @param  \App\Promotorvenda  $promotorvenda
     * @return mixed
     */
    public function delete(User $user, Promotorvenda $promotorvenda)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Promotorvenda $promotorvenda)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa_id = Session::get('empresa_padrao')->id;
        $empresa = $promotorvenda->empresa_id == $empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','promover.index')->first();
    }
}
