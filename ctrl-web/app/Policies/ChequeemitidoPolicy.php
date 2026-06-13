<?php

namespace App\Policies;

use Session;
use App\User;
use App\Chequeemitido;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChequeemitidoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the chequeemitido.
     *
     * @param  \App\User  $user
     * @param  \App\Chequeemitido  $chequeemitido
     * @return mixed
     */
    public function view(User $user, Chequeemitido $chequeemitido)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create chequeemitidos.
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
     * Determine whether the user can update the chequeemitido.
     *
     * @param  \App\User  $user
     * @param  \App\Chequeemitido  $chequeemitido
     * @return mixed
     */
    public function update(User $user, Chequeemitido $chequeemitido)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can update the chequeemitido.
     *
     * @param  \App\User  $user
     * @param  \App\Chequeemitido  $chequeemitido
     * @return mixed
     */
    public function viewUpdate(User $user)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->baixar == 1 && $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the chequeemitido.
     *
     * @param  \App\User  $user
     * @param  \App\Chequeemitido  $chequeemitido
     * @return mixed
     */
    public function delete(User $user, Chequeemitido $chequeemitido)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    /**
     * Determine whether the user can view delete the chequeemitido.
     *
     * @param  \App\User  $user
     * @param  \App\Chequeemitido  $chequeemitido
     * @return mixed
     */
    public function inutilizar(User $user)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    public function baixar(User $user)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->baixar == 1;
    }

    public function igualdade(User $user, Chequeemitido $chequeemitido)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa_id = Session::get('empresa_padrao')->id;
        $empresa = $chequeemitido->empresa_id == $empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    public function excluir(User $user)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','chequeemitido.index')->first();
    }
}
