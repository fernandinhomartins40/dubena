<?php

namespace App\Policies;

use Session;
use App\User;
use App\Financeiro;
use Illuminate\Auth\Access\HandlesAuthorization;

class FinanceiroPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the financeiro.
     *
     * @param  \App\User  $user
     * @param  \App\Financeiro  $financeiro
     * @return mixed
     */
    public function view(User $user, Financeiro $financeiro)
    {
        $permissoes = $this->getPermissoes('financeiro.createDespesa');

        if (is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1 && $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can view financeiros.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function viewReceita(User $user, Financeiro $financeiro)
    {
        $permissoes = $this->getPermissoes('financeiro.createReceita');

        if (is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1 && $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the financeiro.
     *
     * @param  \App\User  $user
     * @param  \App\Financeiro  $financeiro
     * @return mixed
     */
    public function update(User $user, Financeiro $financeiro)
    {
        //
    }

    /**
     * Determine whether the user can delete the financeiro.
     *
     * @param  \App\User  $user
     * @param  \App\Financeiro  $financeiro
     * @return mixed
     */
    public function delete(User $user, Financeiro $financeiro)
    {
        //
    }

    //Contas a Receber
    public function viewReceber(User $user, Financeiro $financeiro)
    {
        $permissoes = $this->getPermissoes('contasreceber.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    public function agruparReceber(User $user)
    {
        $permissoes = $this->getPermissoes('contasreceber.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    public function alterarRecebimento(User $user)
    {
        $permissoes = $this->getPermissoes('contasreceber.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    public function receber(User $user)
    {
        $permissoes = $this->getPermissoes('contasreceber.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->baixar == 1;
    }

    public function igualdadeReceber(User $user, Financeiro $financeiro)
    {
        $permissoes = $this->getPermissoes('contasreceber.index');

        if (is_null($permissoes))
            return false;

        $empresa_id = Session::get('empresa_padrao')->id;

        $empresa = $financeiro->empresa_id == $empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    //Receber Retroativo == Receber

    public function encontroRecbimento(User $user)
    {
        $permissoes = $this->getPermissoes('contasreceber.index');
        $cheqs      = $this->getPermissoes('chequerecebido.index');

        if (is_null($permissoes) && is_null($cheqs))
            return false;

        return (!is_null($permissoes) && $permissoes->baixar == 1) || (!is_null($cheqs) && $cheqs->criar == 1);
    }

    public function chequeRecebimento(User $user)
    {
        $permissoes = $this->getPermissoes('contasreceber.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    public function estornarRecebimento(User $user)
    {
        $permissoes = $this->getPermissoes('contasreceber.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->baixar == 1 && $permissoes->editar == 1;
    }

    public function cancelarRecebimento(User $user)
    {
        $permissoes = $this->getPermissoes('contasreceber.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    //////// Contas a Pagar
    public function pagar(User $user)
    {
        $permissoes = $this->getPermissoes('contaspagar.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->baixar == 1;
    }

    public function estornarPagamento(User $user)
    {
        $permissoes = $this->getPermissoes('contaspagar.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->baixar == 1 && $permissoes->editar == 1;
    }

    public function agruparPagamento(User $user)
    {
        $permissoes = $this->getPermissoes('contaspagar.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    public function cancelarPagamento(User $user)
    {
        $permissoes = $this->getPermissoes('contaspagar.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function alterarPagamento(User $user)
    {
        $permissoes = $this->getPermissoes('contaspagar.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    public function chequePagamento(User $user)
    {
        $permissoes = $this->getPermissoes('contaspagar.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    public function encontroPagamento(User $user)
    {
        $permissoes = $this->getPermissoes('contaspagar.index');

        if (is_null($permissoes))
            return false;

        return $permissoes->baixar == 1 && $permissoes->editar == 1;
    }

    // * Fechamento de Malotes


    private function getPermissoes($rota)
    {
        return Session::get('permissoes')->where('descricao', $rota)->first();
    }
}
