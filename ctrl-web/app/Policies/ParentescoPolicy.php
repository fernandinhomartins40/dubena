<?php

namespace App\Policies;

use Session;
use App\User;
use App\Parentesco;
use Illuminate\Auth\Access\HandlesAuthorization;

class ParentescoPolicy
{
    use HandlesAuthorization;

    protected $grupo_id;
    protected $permissoes;

    function __construct()
    {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the parentesco.
     *
     * @param  \App\User  $user
     * @param  \App\Parentesco  $parentesco
     * @return mixed
     */
    public function view(User $user, Parentesco $parentesco)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create parentescos.
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
     * Determine whether the user can update the parentesco.
     *
     * @param  \App\User  $user
     * @param  \App\Parentesco  $parentesco
     * @return mixed
     */
    public function update(User $user, Parentesco $parentesco)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the parentesco.
     *
     * @param  \App\User  $user
     * @param  \App\Parentesco  $parentesco
     * @return mixed
     */
    public function delete(User $user, Parentesco $parentesco)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function getPermissoes()
    {
        return $this->permissoes->where('descricao','parentesco.index')->first();
    }
}
