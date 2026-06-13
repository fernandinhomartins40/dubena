<?php

namespace App\Policies;

use Session;
use App\User;
use App\Conta;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContaPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
        $this->empresa_id = Session::get('empresa_padrao')->id;
    }

    /**
     * Determine whether the user can view the conta.
     *
     * @param  \App\User  $user
     * @param  \App\Conta  $conta
     * @return mixed
     */
    public function view(User $user, Conta $conta)
    {
        $permissoes = $this->getPermissoes('conta.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create contas.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes('conta.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the conta.
     *
     * @param  \App\User  $user
     * @param  \App\Conta  $conta
     * @return mixed
     */
    public function update(User $user, Conta $conta)
    {
        $permissoes = $this->getPermissoes('conta.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the conta.
     *
     * @param  \App\User  $user
     * @param  \App\Conta  $conta
     * @return mixed
     */
    public function delete(User $user, Conta $conta)
    {
        $permissoes = $this->getPermissoes('conta.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Conta $conta)
    {
        $empresa = $this->empresa_id == $conta->empresa_id;
        $permissoes = $this->getPermissoes('conta.index');
        if(is_null($permissoes))
            return false;

        return $empresa;
    }

    //Caixas
    public function viewCaixa(User $user, Conta $conta)
    {
        $permissoes = $this->getPermissoes('caixa.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    public function lanRetroativo(User $user, Conta $conta)
    {
        $contas = $user->contas()->get();

        if(!$contas->contains('conta_id',$conta->id))
            return false;

        $con = $contas->where('conta_id',$conta->id)->first();

        return $con->visualizar == 1;
    }

    private function getPermissoes($menu)
    {
        return $this->permissoes->where('descricao',$menu)->first();
    }
}
