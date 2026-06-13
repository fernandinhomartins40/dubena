<?php

namespace App\Policies;

use Session;
use App\User;
use App\EmpresasGrupo;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmpresasgrupoPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the empresasGrupo.
     *
     * @param  \App\User  $user
     * @param  \App\EmpresasGrupo  $empresasGrupo
     * @return mixed
     */
    public function view(User $user, EmpresasGrupo $empresasGrupo)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->visualizar == 1;
    }

    /**
     * Determine whether the user can create empresasGrupos.
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
     * Determine whether the user can update the empresasGrupo.
     *
     * @param  \App\User  $user
     * @param  \App\EmpresasGrupo  $empresasGrupo
     * @return mixed
     */
    public function update(User $user, EmpresasGrupo $empresasGrupo)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->editar == 1;
    }

    /**
     * Determine whether the user can delete the empresasGrupo.
     *
     * @param  \App\User  $user
     * @param  \App\EmpresasGrupo  $empresasGrupo
     * @return mixed
     */
    public function delete(User $user, EmpresasGrupo $empresasGrupo)
    {
        //
    }

    private function getPermissions()
    {
        return $this->permissoes->where('descricao','empresas_grupo.index')->first();
    }
}
