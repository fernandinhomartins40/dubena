<?php

namespace App\Policies;

use Session;
use App\User;
use App\Valegasvenda;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValegasvendaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the valegasvenda.
     *
     * @param  \App\User  $user
     * @param  \App\Valegasvenda  $valegasvenda
     * @return mixed
     */
    public function view(User $user, Valegasvenda $valegasvenda)
    {
        $permissoes = $this->getPermissoes('vendavalegas.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create valegasvendas.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes('vendavalegas.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the valegasvenda.
     *
     * @param  \App\User  $user
     * @param  \App\Valegasvenda  $valegasvenda
     * @return mixed
     */
    public function update(User $user, Valegasvenda $valegasvenda)
    {
        //
    }

    /**
     * Determine whether the user can delete the valegasvenda.
     *
     * @param  \App\User  $user
     * @param  \App\Valegasvenda  $valegasvenda
     * @return mixed
     */
    public function delete(User $user, Valegasvenda $valegasvenda)
    {
        $permissoes = $this->getPermissoes('vendavalegas.index');

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Valegasvenda $valegasvenda)
    {
        $permissoes = $this->getPermissoes('vendavalegas.index');
        $empresa_id = Session::get('empresa_padrao')->id;

        if(is_null($permissoes))
            return false;

        $empresa = $empresa_id == $valegasvenda->empresa_id;

        return $empresa && $permissoes->visualizar == 1;
    }

    public function imprimirGas(User $user)
    {
        $permissoes = $this->getPermissoes('valegas.index');

        if(is_null($permissoes))
            return false;

        
        return $permissoes->visualizar == 1 && $permissoes->editar == 1;
    }

    private function getPermissoes($index)
    {
        return Session::get('permissoes')->where('descricao',$index)->first();
    }
}
