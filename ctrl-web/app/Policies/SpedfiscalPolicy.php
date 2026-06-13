<?php

namespace App\Policies;

use Session;
use App\User;
use App\Spedfiscal;
use Illuminate\Auth\Access\HandlesAuthorization;

class SpedfiscalPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the spedfiscal.
     *
     * @param  \App\User  $user
     * @param  \App\Spedfiscal  $spedfiscal
     * @return mixed
     */
    public function view(User $user, Spedfiscal $spedfiscal)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->visualizar == 1;
    }

    /**
     * Determine whether the user can create spedfiscals.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->criar == 1;
    }

    /**
     * Determine whether the user can update the spedfiscal.
     *
     * @param  \App\User  $user
     * @param  \App\Spedfiscal  $spedfiscal
     * @return mixed
     */
    public function update(User $user, Spedfiscal $spedfiscal)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->editar == 1;
    }

    /**
     * Determine whether the user can delete the spedfiscal.
     *
     * @param  \App\User  $user
     * @param  \App\Spedfiscal  $spedfiscal
     * @return mixed
     */
    public function delete(User $user, Spedfiscal $spedfiscal)
    {
        if(is_null($this->getPermission()))
            return false;
            
        return $this->getPermission()->deletar == 1;
    }

    public function igualdade(User $user, Spedfiscal $spedfiscal)
    {
        $empresa_padrao = Session::get('empresa_padrao')->id;

        if(is_null($this->getPermission()))
            return false;
            
        $empresa = $spedfiscal->empresa_id == $empresa_padrao;

        return $this->getPermission()->visualizar == 1 && $empresa;
    }

    private function getPermission()
    {
        return Session::get('permissoes')->where('descricao','spedfiscal.index')->first();
    }
}
