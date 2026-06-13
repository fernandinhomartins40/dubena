<?php

namespace App\Policies;

use Session;
use App\User;
use App\ConfiguracoesGerais;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConfiguracoesGeraisPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the ConfiguracoesGerais.
     *
     * @param  \App\User  $user
     * @param  \App\ConfiguracoesGerais  $ConfiguracoesGerais
     * @return mixed
     */
    public function view(User $user, ConfiguracoesGerais $ConfiguracoesGerais)
    {
        $permissoes = $this->getPermissoes('configuracoesGerais.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create ConfiguracoesGeraiss.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes('configuracoesGerais.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the ConfiguracoesGerais.
     *
     * @param  \App\User  $user
     * @param  \App\ConfiguracoesGerais  $ConfiguracoesGerais
     * @return mixed
     */
    public function update(User $user)
    {
        $permissoes = $this->getPermissoes('configuracoesGerais.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->criar == 1 && $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the ConfiguracoesGerais.
     *
     * @param  \App\User  $user
     * @param  \App\ConfiguracoesGerais  $ConfiguracoesGerais
     * @return mixed
     */
    public function delete(User $user, ConfiguracoesGerais $ConfiguracoesGerais)
    {
        //
    }

    public function igualdade(User $user, ConfiguracoesGerais $ConfiguracoesGerais)
    {        
        $permissoes = $this->getPermissoes('configuracoesGerais.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    public function getPermissoes($index)
    {
        return $this->permissoes->where('descricao',$index)->first();
    }
}
