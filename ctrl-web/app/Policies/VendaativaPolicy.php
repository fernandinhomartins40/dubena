<?php

namespace App\Policies;

use Session;
use App\User;
use App\Vendaativa;
use Illuminate\Auth\Access\HandlesAuthorization;

class VendaativaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the vendaativa.
     *
     * @param  \App\User  $user
     * @param  \App\Vendaativa  $vendaativa
     * @return mixed
     */
    public function view(User $user, Vendaativa $vendaativa)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create vendaativas.
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
     * Determine whether the user can update the vendaativa.
     *
     * @param  \App\User  $user
     * @param  \App\Vendaativa  $vendaativa
     * @return mixed
     */
    public function update(User $user, Vendaativa $vendaativa)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the vendaativa.
     *
     * @param  \App\User  $user
     * @param  \App\Vendaativa  $vendaativa
     * @return mixed
     */
    public function delete(User $user, Vendaativa $vendaativa)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function work(User $user)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->criar == 1 && $permissoes->editar == 1;
    }

    public function igualdade(User $user, Vendaativa $vendaativa)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa_id = Session::get('empresa_padrao')->id;
        $empresa = $vendaativa->empresa_id == $empresa_id;

        return $empresa && $permissoes->visualizar == 1;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','vendaativa.index')->first();
    }
}
