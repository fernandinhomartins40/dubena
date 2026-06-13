<?php

namespace App\Policies;

use Session;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct()
    {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the user.
     *
     * @param  \App\User  $user
     * @param  \App\User  $user
     * @return mixed
     */
    public function view(User $user)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->visualizar == 1;
    }

    /**
     * Determine whether the user can create users.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->criar == 1;
    }

    /**
     * Determine whether the user can update the user.
     *
     * @param  \App\User  $user
     * @param  \App\User  $user
     * @return mixed
     */
    public function update(User $user)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->editar == 1;
    }

    /**
     * Determine whether the user can delete the user.
     *
     * @param  \App\User  $user
     * @param  \App\User  $user
     * @return mixed
     */
    public function delete(User $user)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->deletar == 1;
    }

    public function igualdade(User $user)
    {
        $auth_user = \Auth::user()->id;
        return $auth_user == $user->id;
    }

    private function getPermissions()
    {
        return $this->permissoes->where('descricao','user.index')->first();
    }
}
