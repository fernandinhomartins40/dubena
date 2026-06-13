<?php

namespace App\Policies;

use Session;
use App\User;
use App\Recessotipo;
use Illuminate\Auth\Access\HandlesAuthorization;

class RecessotipoPolicy
{
    use HandlesAuthorization;

    protected $grupo_id;
    protected $permissoes;

    /**
     * Determine whether the user can view the recessotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Recessotipo  $recessotipo
     * @return mixed
     */
    public function view(User $user, Recessotipo $recessotipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create recessotipos.
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
     * Determine whether the user can update the recessotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Recessotipo  $recessotipo
     * @return mixed
     */
    public function update(User $user, Recessotipo $recessotipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the recessotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Recessotipo  $recessotipo
     * @return mixed
     */
    public function delete(User $user, Recessotipo $recessotipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Recessotipo $recessotipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $grupo = $recessotipo->grupo_id == Session::get('empresa_padrao')->grupo_id;

        return $permissoes->visualizar == 1 && $grupo;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','tiporecessos.index')->first();
    }
}
