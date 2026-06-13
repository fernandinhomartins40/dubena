<?php

namespace App\Policies;

use Session;
use App\User;
use App\Cidade;
use Illuminate\Auth\Access\HandlesAuthorization;

class CidadePolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the cidade.
     *
     * @param  \App\User  $user
     * @param  \App\Cidade  $cidade
     * @return mixed
     */
    public function view(User $user, Cidade $cidade)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->visualizar == 1;
    }

    /**
     * Determine whether the user can create cidades.
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
     * Determine whether the user can update the cidade.
     *
     * @param  \App\User  $user
     * @param  \App\Cidade  $cidade
     * @return mixed
     */
    public function update(User $user, Cidade $cidade)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->editar == 1;
    }

    /**
     * Determine whether the user can delete the cidade.
     *
     * @param  \App\User  $user
     * @param  \App\Cidade  $cidade
     * @return mixed
     */
    public function delete(User $user, Cidade $cidade)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','cidade.index')->first();
    }
}
