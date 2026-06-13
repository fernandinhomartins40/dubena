<?php

namespace App\Policies;

use Session;
use App\User;
use App\Spedcontribuicoescredito;
use Illuminate\Auth\Access\HandlesAuthorization;

class SpedcontribuicoescreditoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the spedcontribuicoescredito.
     *
     * @param  \App\User  $user
     * @param  \App\Spedcontribuicoescredito  $spedcontribuicoescredito
     * @return mixed
     */
    public function view(User $user, Spedcontribuicoescredito $spedcontribuicoescredito)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes)) return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create spedcontribuicoescreditos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes)) return false;

        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the spedcontribuicoescredito.
     *
     * @param  \App\User  $user
     * @param  \App\Spedcontribuicoescredito  $spedcontribuicoescredito
     * @return mixed
     */
    public function update(User $user, Spedcontribuicoescredito $spedcontribuicoescredito)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes)) return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the spedcontribuicoescredito.
     *
     * @param  \App\User  $user
     * @param  \App\Spedcontribuicoescredito  $spedcontribuicoescredito
     * @return mixed
     */
    public function delete(User $user, Spedcontribuicoescredito $spedcontribuicoescredito)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes)) return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Spedcontribuicoescredito $spedcontribuicoescredito)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes)) return false;

        $empresa = Session::get('empresa_padrao')->id == $spedcontribuicoescredito->empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','spedcreditos.index')->first();
    }
}
