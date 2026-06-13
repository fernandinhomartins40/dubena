<?php

namespace App\Policies;

use App\User;
use App\Mcmm;
use Session;
use Illuminate\Auth\Access\HandlesAuthorization;

class McmmPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }
    /**
     * Determine whether the user can view the mcmm.
     *
     * @param  \App\User  $user
     * @param  \App\Mcmm  $mcmm
     * @return mixed
     */
    public function view(User $user, Mcmm $mcmm)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->visualizar == 1;
    }

    /**
     * Determine whether the user can create mcmms.
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
     * Determine whether the user can update the mcmm.
     *
     * @param  \App\User  $user
     * @param  \App\Mcmm  $mcmm
     * @return mixed
     */
    public function update(User $user, Mcmm $mcmm)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->editar == 1;
    }

    /**
     * Determine whether the user can delete the mcmm.
     *
     * @param  \App\User  $user
     * @param  \App\Mcmm  $mcmm
     * @return mixed
     */
    public function delete(User $user, Mcmm $mcmm)
    {
        if(is_null($this->getPermission()))
            return false;
            
        return $this->getPermission()->deletar == 1;
    }

    public function igualdade(User $user, Mcmm $mcmm)
    {
        $permission = !is_null($this->getPermission()) && $this->getPermission()->visualizar == 1;
        $igualdade = $mcmm->empresa_id == $this->empresa_id;
        
        return $permission && $igualdade;
    }

    private function getPermission()
    {
        return $this->permissoes->where('descricao','mcmm.index')->first();
    }
}
