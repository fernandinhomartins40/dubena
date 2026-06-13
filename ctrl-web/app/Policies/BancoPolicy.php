<?php

namespace App\Policies;

use Session;
use App\User;
use App\Banco;
use Illuminate\Auth\Access\HandlesAuthorization;

class BancoPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the banco.
     *
     * @param  \App\User  $user
     * @param  \App\Banco  $banco
     * @return mixed
     */
    public function view(User $user, Banco $banco)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create bancos.
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
     * Determine whether the user can update the banco.
     *
     * @param  \App\User  $user
     * @param  \App\Banco  $banco
     * @return mixed
     */
    public function update(User $user, Banco $banco)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the banco.
     *
     * @param  \App\User  $user
     * @param  \App\Banco  $banco
     * @return mixed
     */
    public function delete(User $user, Banco $banco)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','banco.index')->first();
    }
}
