<?php

namespace App\Policies;

use Session;
use App\User;
use App\Chequerecebido;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChequerecebidoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the chequerecebido.
     *
     * @param  \App\User  $user
     * @param  \App\Chequerecebido  $chequerecebido
     * @return mixed
     */
    public function view(User $user, Chequerecebido $chequerecebido)
    {
        $permissoes = $this->getPermissoes('chequerecebido.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create chequerecebidos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes('chequerecebido.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the chequerecebido.
     *
     * @param  \App\User  $user
     * @param  \App\Chequerecebido  $chequerecebido
     * @return mixed
     */
    public function update(User $user, Chequerecebido $chequerecebido)
    {
        $permissoes = $this->getPermissoes('chequerecebido.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    public function editar(User $user)
    {
        $permissoes = $this->getPermissoes('chequerecebido.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the chequerecebido.
     * @param  \App\User  $user
     * @param  \App\Chequerecebido  $chequerecebido
     * @return mixed
     */
    public function delete(User $user, Chequerecebido $chequerecebido)
    {
        $permissoes = $this->getPermissoes('chequerecebido.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function excluir(User $user)
    {
        $permissoes = $this->getPermissoes('chequerecebido.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Chequerecebido $chequerecebido)
    {
        $permissoes = $this->getPermissoes('chequerecebido.index');

        if(is_null($permissoes))
            return false;

        $empresa_id = Session::get('empresa_padrao')->id;
        $empresa = $chequerecebido->empresa_id == $empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    public function baixar(User $user)
    {
        $cheques = $this->getPermissoes('chequerecebido.index');

        if(is_null($cheques))
            return false;

        return $cheques->baixar == 1;
    }

    public function estornar(User $user)
    {
        $cheques = $this->getPermissoes('chequerecebido.index');
        $permis  = $this->getPermissoes('contasreceber.index');

        if(is_null($cheques) || is_null($permis))
            return false;

        $validacao = $cheques->baixar == 1 && $cheques->editar == 1 &&
             $permis->baixar == 1 && $permis->editar == 1;
        return $validacao;
    }

    public function devolver(User $user)
    {
        $cheques = $this->getPermissoes('chequerecebido.index');
        $permis  = $this->getPermissoes('contasreceber.index');

        if(is_null($cheques) || is_null($permis))
            return false;

        return $cheques->editar == 1 && $cheques->baixar == 1 && $permis->baixar == 1;
    }

    public function baixarambos(User $user)
    {
        $cheques = $this->getPermissoes('chequerecebido.index');
        $permis  = $this->getPermissoes('contasreceber.index');

        if(is_null($cheques) || is_null($permis))
            return false;

        $validacao = $cheques->baixar == 1 && $permis->baixar == 1;

        return $validacao;
    }

    private function getPermissoes($rota)
    {
        return Session::get('permissoes')->where('descricao',$rota)->first();
    }
}
