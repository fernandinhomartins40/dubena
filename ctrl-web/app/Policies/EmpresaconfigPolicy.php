<?php

namespace App\Policies;

use Session;
use App\User;
use App\Empresaconfig;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmpresaconfigPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the empresaconfig.
     *
     * @param  \App\User  $user
     * @param  \App\Empresaconfig  $empresaconfig
     * @return mixed
     */
    public function view(User $user, Empresaconfig $empresaconfig)
    {
        $permissoes = $this->getPermissoes('empresaconfig.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create empresaconfigs.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes('empresaconfig.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the empresaconfig.
     *
     * @param  \App\User  $user
     * @param  \App\Empresaconfig  $empresaconfig
     * @return mixed
     */
    public function update(User $user)
    {
        $permissoes = $this->getPermissoes('empresaconfig.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->criar == 1 && $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the empresaconfig.
     *
     * @param  \App\User  $user
     * @param  \App\Empresaconfig  $empresaconfig
     * @return mixed
     */
    public function delete(User $user, Empresaconfig $empresaconfig)
    {
        //
    }

    public function igualdade(User $user, Empresaconfig $empresaconfig)
    {        
        $permissoes = $this->getPermissoes('empresaconfig.index');

        if(is_null($permissoes))
            return false;

        $empresa = $this->empresa_id == $empresaconfig->empresa_id;

        return $empresa && $permissoes->visualizar == 1;
    }

    public function viewSenhaMestre(User $user, Empresaconfig $empresaconfig)
    {
        $permissoes = $this->getPermissoes('empresaconfig.senhamestre');

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1 && $permissoes->criar == 1 && $permissoes->editar == 1;
    }

    public function getPermissoes($index)
    {
        return $this->permissoes->where('descricao',$index)->first();
    }
}
