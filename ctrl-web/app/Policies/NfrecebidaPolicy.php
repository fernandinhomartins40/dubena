<?php

namespace App\Policies;

use Session;
use App\User;
use App\Nfrecebida;
use Illuminate\Auth\Access\HandlesAuthorization;

class NfrecebidaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the nfrecebida.
     *
     * @param  \App\User  $user
     * @param  \App\Nfrecebida  $nfrecebida
     * @return mixed
     */
    public function view(User $user, Nfrecebida $nfrecebida)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create nfrecebidas.
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
     * Determine whether the user can update the nfrecebida.
     *
     * @param  \App\User  $user
     * @param  \App\Nfrecebida  $nfrecebida
     * @return mixed
     */
    public function update(User $user, Nfrecebida $nfrecebida)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the nfrecebida.
     *
     * @param  \App\User  $user
     * @param  \App\Nfrecebida  $nfrecebida
     * @return mixed
     */
    public function delete(User $user, Nfrecebida $nfrecebida)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    /**
     * Determine whether the user can access the contents of other corps
     *
     * @param  \App\User  $user
     * @param  \App\Nfrecebida  $nfrecebida
     * @return mixed
     */
    public function igualdade(User $user, Nfrecebida $nfrecebida)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        $empresa = Session::get('empresa_padrao')->id == $nfrecebida->empresa_id;
        return $permissoes->visualizar == 1 && $empresa;
    }

    /**
     * Determine whether the user can update from form
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function updateForm(User $user)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao', 'nfrecebida.index')->first();
    }
}
