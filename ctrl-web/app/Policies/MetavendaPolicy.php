<?php

namespace App\Policies;

use Session;
use App\User;
use App\Metavenda;
use Illuminate\Auth\Access\HandlesAuthorization;

class MetavendaPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the metavenda.
     *
     * @param  \App\User  $user
     * @param  \App\Metavenda  $metavenda
     * @return mixed
     */
    public function view(User $user, Metavenda $metavenda)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create metavendas.
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
     * Determine whether the user can update the metavenda.
     *
     * @param  \App\User  $user
     * @param  \App\Metavenda  $metavenda
     * @return mixed
     */
    public function update(User $user, Metavenda $metavenda)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the metavenda.
     *
     * @param  \App\User  $user
     * @param  \App\Metavenda  $metavenda
     * @return mixed
     */
    public function delete(User $user, Metavenda $metavenda)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Metavenda $metavenda)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa = $metavenda->empresa_id == $this->empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','metavenda.index')->first();
    }
}
