<?php

namespace App\Policies;

use Session;
use App\User;
use App\Cargo;
use Illuminate\Auth\Access\HandlesAuthorization;

class CargoPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the Cargo.
     *
     * @param  \App\User  $user
     * @param  \App\Cargo  $Cargo
     * @return mixed
     */
    public function view(User $user, Cargo $cargo)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->visualizar == 1;
    }

    /**
     * Determine whether the user can create Cargos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->criar == 1;
    }

    /**
     * Determine whether the user can update the Cargo.
     *
     * @param  \App\User  $user
     * @param  \App\Cargo  $Cargo
     * @return mixed
     */
    public function update(User $user, Cargo $cargo)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->editar == 1;
    }

    /**
     * Determine whether the user can delete the Cargo.
     *
     * @param  \App\User  $user
     * @param  \App\Cargo  $Cargo
     * @return mixed
     */
    public function delete(User $user, Cargo $cargo)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','cargo.index')->first();
    }
}
