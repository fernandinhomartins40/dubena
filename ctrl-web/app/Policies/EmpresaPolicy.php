<?php

namespace App\Policies;

use DB;
use Session;
use App\User;
use App\Empresa;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmpresaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the empresa.
     *
     * @param  \App\User  $user
     * @param  \App\Empresa  $empresa
     * @return mixed
     */
    public function view(User $user, Empresa $empresa)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->visualizar == 1;
    }

    /**
     * Determine whether the user can create empresas.
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
     * Determine whether the user can update the empresa.
     *
     * @param  \App\User  $user
     * @param  \App\Empresa  $empresa
     * @return mixed
     */
    public function update(User $user, Empresa $empresa)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->editar == 1;
    }

    /**
     * Determine whether the user is from the support team.
     *
     * @param  \App\User  $user
     * @param  \App\Empresa  $empresa
     * @return mixed
     */
    public function support(User $user)
    {
        return $user->support === "1";
    }

    private function getPermissions()
    {
        return Session::get('permissoes')->where('descricao','empresa.index')->first();
    }
}
