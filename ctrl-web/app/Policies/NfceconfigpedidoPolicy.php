<?php

namespace App\Policies;

use Session;
use App\User;
use App\Nfceconfigpedido;
use Illuminate\Auth\Access\HandlesAuthorization;

class NfceconfigpedidoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the nfceconfigpedido.
     *
     * @param  \App\User  $user
     * @param  \App\Nfceconfigpedido  $nfceconfigpedido
     * @return mixed
     */
    public function view(User $user, Nfceconfigpedido $nfceconfigpedido)
    {
        $permissao = $this->getPermissoes();

        if (is_null($permissao))
            return false;

        return $permissao->visualizar == 1;
    }

    /**
     * Determine whether the user can create nfceconfigpedidos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissao = $this->getPermissoes();

        if (is_null($permissao))
            return false;

        return $permissao->criar == 1;
    }

    /**
     * Determine whether the user can update the nfceconfigpedido.
     *
     * @param  \App\User  $user
     * @param  \App\Nfceconfigpedido  $nfceconfigpedido
     * @return mixed
     */
    public function deleteCreate(User $user, Nfceconfigpedido $nfceconfigpedido)
    {
        //
    }

    /**
     * Determine whether the user can delete the nfceconfigpedido.
     *
     * @param  \App\User  $user
     * @param  \App\Nfceconfigpedido  $nfceconfigpedido
     * @return mixed
     */
    public function delete(User $user, Nfceconfigpedido $nfceconfigpedido)
    {
        $permissao = $this->getPermissoes();

        if (is_null($permissao))
            return false;

        return $permissao->deletar == 1;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','confgNfcePedido.index')->first();
    }
}
