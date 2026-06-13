<?php

namespace App\Policies;

use Session;
use App\User;
use App\Telefonetipo;
use Illuminate\Auth\Access\HandlesAuthorization;

class TelefonetipoPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the telefonetipo.
     *
     * @param  \App\User  $user
     * @param  \App\Telefonetipo  $telefonetipo
     * @return mixed
     */
    public function view(User $user, Telefonetipo $telefonetipo)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->visualizar == 1;
    }

    /**
     * Determine whether the user can create telefonetipos.
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
     * Determine whether the user can update the telefonetipo.
     *
     * @param  \App\User  $user
     * @param  \App\Telefonetipo  $telefonetipo
     * @return mixed
     */
    public function update(User $user, Telefonetipo $telefonetipo)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->editar == 1;
    }

    /**
     * Determine whether the user can delete the telefonetipo.
     *
     * @param  \App\User  $user
     * @param  \App\Telefonetipo  $telefonetipo
     * @return mixed
     */
    public function delete(User $user, Telefonetipo $telefonetipo)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','telefonetipo.index')->first();
    }
}
