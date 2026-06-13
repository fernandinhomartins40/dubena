<?php

namespace App\Policies;

use Session;
use App\User;
use App\Boletoremessa;
use Illuminate\Auth\Access\HandlesAuthorization;

class BoletoremessaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the boletoremessa.
     *
     * @param  \App\User  $user
     * @param  \App\Boletoremessa  $boletoremessa
     * @return mixed
     */
    public function view(User $user, Boletoremessa $boletoremessa)
    {
        $permissao = $this->getPermissao();

        if (is_null($permissao))
            return false;

        return $permissao->visualizar == 1;
    }

    /**
     * Determine whether the user can create boletoremessas.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissao = $this->getPermissao();

        if (is_null($permissao))
            return false;

        return $permissao->criar == 1;
    }

    /**
     * Determine whether the user can update the boletoremessa.
     *
     * @param  \App\User  $user
     * @param  \App\Boletoremessa  $boletoremessa
     * @return mixed
     */
    public function update(User $user)
    {
        $permissao = $this->getPermissao();

        if (is_null($permissao))
            return false;

        return $permissao->editar == 1;
    }

    /**
     * Determine whether the user can delete the boletoremessa.
     *
     * @param  \App\User  $user
     * @param  \App\Boletoremessa  $boletoremessa
     * @return mixed
     */
    public function delete(User $user, Boletoremessa $boletoremessa)
    {
        //
    }

    private function getPermissao()
    {
        return Session::get('permissoes')->where('descricao','remessa.index')->first();
    }
}
