<?php

namespace App\Policies;

use Session;
use App\User;
use App\Estadocivil;
use Illuminate\Auth\Access\HandlesAuthorization;

class EstadocivilPolicy
{
    use HandlesAuthorization;

    protected $grupo_id;
    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
    }

    /**
     * Determine whether the user can view the estadocivil.
     *
     * @param  \App\User  $user
     * @param  \App\Estadocivil  $estadocivil
     * @return mixed
     */
    public function view(User $user, Estadocivil $estadocivil)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->visualizar == 1;
    }

    /**
     * Determine whether the user can create estadocivils.
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
     * Determine whether the user can update the estadocivil.
     *
     * @param  \App\User  $user
     * @param  \App\Estadocivil  $estadocivil
     * @return mixed
     */
    public function update(User $user, Estadocivil $estadocivil)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->editar == 1;
    }

    /**
     * Determine whether the user can delete the estadocivil.
     *
     * @param  \App\User  $user
     * @param  \App\Estadocivil  $estadocivil
     * @return mixed
     */
    public function delete(User $user, Estadocivil $estadocivil)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->deletar == 1;
    }

    public function igualdade(User $user, Estadocivil $estadocivil)
    {
        $grupo = $estadocivil->grupo_id == $this->grupo_id;
        $permissao = $this->getPermissoes()->visualizar == 1;

        return $grupo && $permissao;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','estadocivil.index')->first();
    }
}
