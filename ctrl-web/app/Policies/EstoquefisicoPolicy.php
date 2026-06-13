<?php

namespace App\Policies;

use Session;
use App\User;
use App\Estoquefisico;
use Illuminate\Auth\Access\HandlesAuthorization;

class EstoquefisicoPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the estoquefisico.
     *
     * @param  \App\User  $user
     * @param  \App\Estoquefisico  $estoquefisico
     * @return mixed
     */
    public function view(User $user, Estoquefisico $estoquefisico)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create estoquefisicos.
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
     * Determine whether the user can update the estoquefisico.
     *
     * @param  \App\User  $user
     * @param  \App\Estoquefisico  $estoquefisico
     * @return mixed
     */
    public function update(User $user, Estoquefisico $estoquefisico)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the estoquefisico.
     *
     * @param  \App\User  $user
     * @param  \App\Estoquefisico  $estoquefisico
     * @return mixed
     */
    public function delete(User $user, Estoquefisico $estoquefisico)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Estoquefisico $estoquefisico)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa = $this->empresa_id == $estoquefisico->empresa_id;

        return $empresa && $permissoes->visualizar;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','estoquefisico.index')->first();
    }
}
