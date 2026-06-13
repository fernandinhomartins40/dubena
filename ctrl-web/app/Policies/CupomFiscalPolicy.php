<?php

namespace App\Policies;

use Session;
use App\User;
use App\CupomFiscal;
use Illuminate\Auth\Access\HandlesAuthorization;

class CupomFiscalPolicy
{
    use HandlesAuthorization;

    /**
     * @param User $user
     * @param CupomFiscal $cupomFiscal
     * @return bool
     */
    public function view(User $user, CupomFiscal $cupomFiscal)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create cupomFiscals.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the cupomFiscal.
     *
     * @param  \App\User  $user
     * @param  \App\CupomFiscal  $cupomFiscal
     * @return mixed
     */
    public function update(User $user, CupomFiscal $cupomFiscal)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the cupomFiscal.
     *
     * @param  \App\User  $user
     * @param  \App\CupomFiscal  $cupomFiscal
     * @return mixed
     */
    public function delete(User $user, CupomFiscal $cupomFiscal)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    /**
     * Determine whether the user can access consult/update/new correction cart
     *
     * @param User $user
     * @return bool
     */
    public function especial(User $user)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->criar == 1 || $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can access consult/update/new correction cart
     *
     * @param  \App\User  $user
     * @param  \App\CupomFiscal  $cupomFiscal
     * @return mixed
     */
    public function igualdade(User $user, CupomFiscal $cupomFiscal)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        $empresas = Session::get('empresas_user');
        $exists = $empresas->contains($cupomFiscal->empresa_id);

        return $exists && $permissoes->visualizar == 1;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao', 'satcfe.index')->first();
    }
}
