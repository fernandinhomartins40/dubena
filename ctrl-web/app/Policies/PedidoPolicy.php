<?php

namespace App\Policies;

use Session;
use App\User;
use App\Pedido;
use Illuminate\Auth\Access\HandlesAuthorization;

class PedidoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the pedido.
     *
     * @param  \App\User  $user
     * @param  \App\Pedido  $pedido
     * @return mixed
     */
    public function view(User $user, Pedido $pedido)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create pedidos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the pedido.
     *
     * @param  \App\User  $user
     * @param  \App\Pedido  $pedido
     * @return mixed
     */
    public function update(User $user)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the pedido.
     *
     * @param  \App\User  $user
     * @param  \App\Pedido  $pedido
     * @return mixed
     */
    public function delete(User $user, Pedido $pedido)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    /**
     * Determine whether the user can delete the pedido.
     *
     * @param  \App\User  $user
     * @param  \App\Pedido  $pedido
     * @return mixed
     */
    public function igualdade(User $user, Pedido $pedido)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        $empresas = Session::get('empresas_permitidas');
        $exist = $empresas->contains('id', $pedido->empresa_id);

        return $exist && $permissoes->visualizar == 1;
    }

    public function especial(User $user)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->criar == 1 || $permissoes->editar == 1;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao', 'pedido.index')->first();
    }
}
