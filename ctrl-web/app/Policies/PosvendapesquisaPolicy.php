<?php

namespace App\Policies;

use Session;
use App\User;
use App\Posvendapesquisa;
use Illuminate\Auth\Access\HandlesAuthorization;

class PosvendapesquisaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the posvendapesquisa.
     *
     * @param  \App\User  $user
     * @param  \App\Posvendapesquisa  $posvendapesquisa
     * @return mixed
     */
    public function view(User $user, Posvendapesquisa $posvendapesquisa)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1 && $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can create posvendapesquisas.
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
     * Determine whether the user can update the posvendapesquisa.
     *
     * @param  \App\User  $user
     * @param  \App\Posvendapesquisa  $posvendapesquisa
     * @return mixed
     */
    public function update(User $user, Posvendapesquisa $posvendapesquisa)
    {
        //
    }

    /**
     * Determine whether the user can delete the posvendapesquisa.
     *
     * @param  \App\User  $user
     * @param  \App\Posvendapesquisa  $posvendapesquisa
     * @return mixed
     */
    public function delete(User $user, Posvendapesquisa $posvendapesquisa)
    {
        //
    }

    public function igualdade(User $user, Posvendapesquisa $posvendapesquisa)
    {
        //
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','posvenda.index')->first();
    }
}
