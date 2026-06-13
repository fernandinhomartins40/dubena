<?php

namespace App\Policies;

use Session;
use App\User;
use App\Tipodocumento;
use Illuminate\Auth\Access\HandlesAuthorization;

class TipodocumentoPolicy
{
    use HandlesAuthorization;

    protected $grupo_id;
    protected $permissoes;

    function __construct() {
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the tipodocumento.
     *
     * @param  \App\User  $user
     * @param  \App\Tipodocumento  $tipodocumento
     * @return mixed
     */
    public function view(User $user, Tipodocumento $tipodocumento)
    {
        $permissoes = $this->getPermissoes();
        
        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create tipodocumentos.
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
     * Determine whether the user can update the tipodocumento.
     *
     * @param  \App\User  $user
     * @param  \App\Tipodocumento  $tipodocumento
     * @return mixed
     */
    public function update(User $user, Tipodocumento $tipodocumento)
    {
        $permissoes = $this->getPermissoes();
        
        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the tipodocumento.
     *
     * @param  \App\User  $user
     * @param  \App\Tipodocumento  $tipodocumento
     * @return mixed
     */
    public function delete(User $user, Tipodocumento $tipodocumento)
    {
        $permissoes = $this->getPermissoes();
        
        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Tipodocumento $tipodocumento)
    {
        $permissoes = $this->getPermissoes();
        
        if(is_null($permissoes))
            return false;

        $grupo = $tipodocumento->grupo_id == $this->grupo_id;

        return $permissoes->visualizar == 1 && $grupo;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','tipodocumento.index')->first();
    }
}
