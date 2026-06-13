<?php

namespace App\Policies;

use App\Cupom;
use Session;
use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CuponsPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the cupom.
     *
     * @param  \App\User  $user
     * @param  \App\Cupom  $cupom
     * @return mixed
     */
    public function view(User $user, Cupom $cupom)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create cupom.
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
     * Determine whether the user can update the cupom.
     *
     * @param  \App\User  $user
     * @param  \App\Cupom  $cupom
     * @return mixed
     */
    public function update(User $user, Cupom $cupom)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the cupom.
     *
     * @param  \App\User  $user
     * @param  \App\Cupom  $cupom
     * @return mixed
     */
    public function delete(User $user, Cupom $cupom)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Cupom $cupom)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa_id = Session::get('empresa_padrao')->id;
        $empresa = $cupom->empresa_id == $empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','cupons.index')->first();
    }
}

