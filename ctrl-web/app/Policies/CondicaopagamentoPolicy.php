<?php

namespace App\Policies;

use Session;
use App\User;
use App\Condicaopagamento;
use Illuminate\Auth\Access\HandlesAuthorization;

class CondicaopagamentoPolicy
{
    use HandlesAuthorization;

    protected $grupo_id;
    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
    }

    /**
     * Determine whether the user can view the condicaopagamento.
     *
     * @param  \App\User  $user
     * @param  \App\Condicaopagamento  $condicaopagamento
     * @return mixed
     */
    public function view(User $user, Condicaopagamento $condicaopagamento)
    {
        $permissoes = $this->getPermisoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create condicaopagamentos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermisoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the condicaopagamento.
     *
     * @param  \App\User  $user
     * @param  \App\Condicaopagamento  $condicaopagamento
     * @return mixed
     */
    public function update(User $user, Condicaopagamento $condicaopagamento)
    {
        $permissoes = $this->getPermisoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the condicaopagamento.
     *
     * @param  \App\User  $user
     * @param  \App\Condicaopagamento  $condicaopagamento
     * @return mixed
     */
    public function delete(User $user, Condicaopagamento $condicaopagamento)
    {
        $permissoes = $this->getPermisoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Condicaopagamento $condicaopagamento)
    {
        $empresa = $condicaopagamento->grupo_id == $this->grupo_id;
        $permissao = $this->getPermisoes();
        if(is_null($permissao))
            return false;

        return $empresa && $permissao->visualizar == 1;
    }

    private function getPermisoes()
    {
        return $this->permissoes->where('descricao','condicaopagamento.index')->first();
    }
}
