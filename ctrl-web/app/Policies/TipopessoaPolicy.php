<?php

namespace App\Policies;

use Session;
use App\User;
use App\Tipopessoa;
use Illuminate\Auth\Access\HandlesAuthorization;

class TipopessoaPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the tipopessoa.
     *
     * @param  \App\User  $user
     * @param  \App\Tipopessoa  $tipopessoa
     * @return mixed
     */
    public function view(User $user, Tipopessoa $tipopessoa)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->visualizar == 1;
    }

    /**
     * Determine whether the user can create tipopessoas.
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
     * Determine whether the user can update the tipopessoa.
     *
     * @param  \App\User  $user
     * @param  \App\Tipopessoa  $tipopessoa
     * @return mixed
     */
    public function update(User $user, Tipopessoa $tipopessoa)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->editar == 1;
    }

    /**
     * Determine whether the user can delete the tipopessoa.
     *
     * @param  \App\User  $user
     * @param  \App\Tipopessoa  $tipopessoa
     * @return mixed
     */
    public function delete(User $user, Tipopessoa $tipopessoa)
    {
        if(is_null($this->getPermissoes()))
            return false;

        return $this->getPermissoes()->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','tipopessoa.index')->first();
    }
}
