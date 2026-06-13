<?php

namespace App\Policies;

use Session;
use App\User;
use App\Estoquesetor;
use Illuminate\Auth\Access\HandlesAuthorization;

class EstoquesetorPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the estoquesetor.
     *
     * @param  \App\User  $user
     * @param  \App\Estoquesetor  $estoquesetor
     * @return mixed
     */
    public function view(User $user, Estoquesetor $estoquesetor)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create estoquesetors.
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
     * Determine whether the user can update the estoquesetor.
     *
     * @param  \App\User  $user
     * @param  \App\Estoquesetor  $estoquesetor
     * @return mixed
     */
    public function update(User $user)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the estoquesetor.
     *
     * @param  \App\User  $user
     * @param  \App\Estoquesetor  $estoquesetor
     * @return mixed
     */
    public function delete(User $user, Estoquesetor $estoquesetor)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Estoquesetor $estoquesetor)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa = $this->empresa_id == $estoquesetor->empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','consultaestoquesetor.index')->first();
    }
}
