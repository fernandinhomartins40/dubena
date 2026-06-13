<?php

namespace App\Policies;

use Session;
use App\User;
use App\Setor;
use Illuminate\Auth\Access\HandlesAuthorization;

class SetorPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the setor.
     *
     * @param  \App\User  $user
     * @param  \App\Setor  $setor
     * @return mixed
     */
    public function view(User $user, Setor $setor)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create setors.
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
     * Determine whether the user can update the setor.
     *
     * @param  \App\User  $user
     * @param  \App\Setor  $setor
     * @return mixed
     */
    public function update(User $user, Setor $setor)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the setor.
     *
     * @param  \App\User  $user
     * @param  \App\Setor  $setor
     * @return mixed
     */
    public function delete(User $user, Setor $setor)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Setor $setor)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa = $this->empresa_id == $setor->empresa_id;

        return $empresa && $permissoes->visualizar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','setor.index')->first();
    }
}
