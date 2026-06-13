<?php

namespace App\Policies;

use Session;
use App\User;
use App\Nfemitida;
use Illuminate\Auth\Access\HandlesAuthorization;

class NfemitidaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the nfemitida.
     *
     * @param  \App\User  $user
     * @param  \App\Nfemitida  $nfemitida
     * @return mixed
     */
    public function view(User $user, Nfemitida $nfemitida)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create nfemitidas.
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
     * Determine whether the user can update the nfemitida.
     *
     * @param  \App\User  $user
     * @param  \App\Nfemitida  $nfemitida
     * @return mixed
     */
    public function update(User $user, Nfemitida $nfemitida)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the nfemitida.
     *
     * @param  \App\User  $user
     * @param  \App\Nfemitida  $nfemitida
     * @return mixed
     */
    public function delete(User $user, Nfemitida $nfemitida)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    /**
     * Determine whether the user can access consult/update/new correction cart
     *
     * @param  \App\User  $user
     * @param  \App\Nfemitida  $nfemitida
     * @return mixed
     */
    public function especial(User $user)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->criar == 1 || $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can access consult/update/new correction cart
     *
     * @param  \App\User  $user
     * @param  \App\Nfemitida  $nfemitida
     * @return mixed
     */
    public function igualdade(User $user, Nfemitida $nfemitida)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        $empresas = Session::get('empresas_user');
        $exists = $empresas->contains($nfemitida->empresa_id);

        return $exists && $permissoes->visualizar == 1;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao', 'nfemitida.index')->first();
    }
}
