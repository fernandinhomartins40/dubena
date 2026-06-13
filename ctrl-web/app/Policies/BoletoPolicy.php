<?php

namespace App\Policies;

use Session;
use App\User;
use App\Boleto;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoletoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the boleto.
     *
     * @param  \App\User  $user
     * @param  \App\Boleto  $boleto
     * @return mixed
     */
    public function view(User $user, Boleto $boleto)
    {
        $permissoes = $this->getPermissao('boleto.index');

        if (is_null($permissoes))
            return false;
        
        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create boletos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissao('boleto.index');

        if (is_null($permissoes))
            return false;
        
        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the boleto.
     *
     * @param  \App\User  $user
     * @param  \App\Boleto  $boleto
     * @return mixed
     */
    public function update(User $user)
    {
        $permissoes = $this->getPermissao('boleto.index');

        if (is_null($permissoes))
            return false;
        
        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the boleto.
     *
     * @param  \App\User  $user
     * @param  \App\Boleto  $boleto
     * @return mixed
     */
    public function delete(User $user)
    {
        $permissoes = $this->getPermissao('boleto.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    private function getPermissao($index)
    {
        return Session::get('permissoes')->where('descricao',$index)->first();
    }
}
