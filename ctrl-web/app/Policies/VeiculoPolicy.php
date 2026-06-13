<?php

namespace App\Policies;

use Session;
use App\User;
use App\Veiculo;
use Illuminate\Auth\Access\HandlesAuthorization;

class VeiculoPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the veiculo.
     *
     * @param  \App\User  $user
     * @param  \App\Veiculo  $veiculo
     * @return mixed
     */
    public function view(User $user, Veiculo $veiculo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create veiculos.
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
     * Determine whether the user can update the veiculo.
     *
     * @param  \App\User  $user
     * @param  \App\Veiculo  $veiculo
     * @return mixed
     */
    public function update(User $user, Veiculo $veiculo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the veiculo.
     *
     * @param  \App\User  $user
     * @param  \App\Veiculo  $veiculo
     * @return mixed
     */
    public function delete(User $user, Veiculo $veiculo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Veiculo $veiculo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa = $this->empresa_id == $veiculo->empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','veiculo.index')->first();
    }
}
