<?php

namespace App\Policies;

use Session;
use App\User;
use App\Regiao;
use Illuminate\Auth\Access\HandlesAuthorization;

class RegionalPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the regiao.
     *
     * @param  \App\User  $user
     * @param  \App\Regiao  $regiao
     * @return mixed
     */
    public function view(User $user, Regiao $regiao)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->visualizar == 1;
    }

    /**
     * Determine whether the user can create regiaos.
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
     * Determine whether the user can update the regiao.
     *
     * @param  \App\User  $user
     * @param  \App\Regiao  $regiao
     * @return mixed
     */
    public function update(User $user, Regiao $regiao)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->editar == 1;
    }

    /**
     * Determine whether the user can delete the regiao.
     *
     * @param  \App\User  $user
     * @param  \App\Regiao  $regiao
     * @return mixed
     */
    public function delete(User $user, Regiao $regiao)
    {
        if(is_null($this->getPermissions()))
            return false;
            
        return $this->getPermissions()->deletar == 1;
    }

    private function getPermissions()
    {
        return $this->permissoes->where('descricao','regiao.index')->first();
    }
}
