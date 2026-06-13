<?php

namespace App\Policies;

use Session;
use App\User;
use App\Valegas;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValegasPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the valegas.
     *
     * @param  \App\User  $user
     * @param  \App\Valegas  $valegas
     * @return mixed
     */
    public function view(User $user, Valegas $valegas)
    {
        $permissoes = $this->getPermissoes('valegascancelar.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1 && $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can view valegas.
     *
     * @param  \App\User  $user
     * @param  \App\Valegas  $valegas
     * @return mixed
     */
    public function viewConsulta(User $user, Valegas $valegas)
    {
        $permissoes = $this->getPermissoes('valegasconsulta.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can update the valegas.
     *
     * @param  \App\User  $user
     * @param  \App\Valegas  $valegas
     * @return mixed
     */
    public function update(User $user, Valegas $valegas)
    {
        $permissoes = $this->getPermissoes('valegascancelar.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the valegas.
     *
     * @param  \App\User  $user
     * @param  \App\Valegas  $valegas
     * @return mixed
     */
    public function viewImpressao(User $user, Valegas $valegas)
    {
        $permissoes = $this->getPermissoes('valegas.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1 && $permissoes->editar == 1;
    }

    public function igualdade(User $user, Valegas $valegas)
    {
        $permissoes = $this->getPermissoes('valegascancelar.index');

        $empresa_id = Session::get('empresa_padrao')->id;

        if(is_null($permissoes))
            return false;

        $empresa = $empresa_id == $valegas->empresa_id;

        return $empresa && $permissoes->visualizar == 1 && $permissoes->editar == 1;
    }

    private function getPermissoes($index)
    {
        return Session::get('permissoes')->where('descricao',$index)->first();
    }
}
