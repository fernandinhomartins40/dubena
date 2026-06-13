<?php

namespace App\Policies;

use Session;
use App\User;
use App\Nfipi;
use Illuminate\Auth\Access\HandlesAuthorization;

class NfipiPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the nfipi.
     *
     * @param  \App\User  $user
     * @param  \App\Nfipi  $nfipi
     * @return mixed
     */
    public function view(User $user, Nfipi $nfipi)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create nfipis.
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
     * Determine whether the user can update the nfipi.
     *
     * @param  \App\User  $user
     * @param  \App\Nfipi  $nfipi
     * @return mixed
     */
    public function update(User $user, Nfipi $nfipi)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the nfipi.
     *
     * @param  \App\User  $user
     * @param  \App\Nfipi  $nfipi
     * @return mixed
     */
    public function delete(User $user, Nfipi $nfipi)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','nfipi.index')->first();
    }
}
