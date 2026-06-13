<?php

namespace App\Policies;

use Session;
use App\User;
use App\Bairro;
use Illuminate\Auth\Access\HandlesAuthorization;

class BairroPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the bairro.
     *
     * @param  \App\User  $user
     * @param  \App\Bairro  $bairro
     * @return mixed
     */
    public function view(User $user, Bairro $bairro)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->visualizar == 1;
    }

    /**
     * Determine whether the user can create bairros.
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
     * Determine whether the user can update the bairro.
     *
     * @param  \App\User  $user
     * @param  \App\Bairro  $bairro
     * @return mixed
     */
    public function update(User $user, Bairro $bairro)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->editar == 1;
    }

    /**
     * Determine whether the user can delete the bairro.
     *
     * @param  \App\User  $user
     * @param  \App\Bairro  $bairro
     * @return mixed
     */
    public function delete(User $user, Bairro $bairro)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','bairro.index')->first();
    }
}
