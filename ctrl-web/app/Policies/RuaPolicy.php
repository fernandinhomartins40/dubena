<?php

namespace App\Policies;

use Session;
use App\User;
use App\Rua;
use Illuminate\Auth\Access\HandlesAuthorization;

class RuaPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the rua.
     *
     * @param  \App\User  $user
     * @param  \App\Rua  $rua
     * @return mixed
     */
    public function view(User $user, Rua $rua)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->visualizar == 1;
    }

    /**
     * Determine whether the user can create ruas.
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
     * Determine whether the user can update the rua.
     *
     * @param  \App\User  $user
     * @param  \App\Rua  $rua
     * @return mixed
     */
    public function update(User $user, Rua $rua)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->editar == 1;
    }

    /**
     * Determine whether the user can delete the rua.
     *
     * @param  \App\User  $user
     * @param  \App\Rua  $rua
     * @return mixed
     */
    public function delete(User $user, Rua $rua)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','rua.index')->first();
    }
}
