<?php

namespace App\Policies;

use Session;
use App\User;
use App\Nfcofins;
use Illuminate\Auth\Access\HandlesAuthorization;

class NfcofinsPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the nfcofins.
     *
     * @param  \App\User  $user
     * @param  \App\Nfcofins  $nfcofins
     * @return mixed
     */
    public function view(User $user, Nfcofins $nfcofins)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create nfcofins.
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
     * Determine whether the user can update the nfcofins.
     *
     * @param  \App\User  $user
     * @param  \App\Nfcofins  $nfcofins
     * @return mixed
     */
    public function update(User $user, Nfcofins $nfcofins)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the nfcofins.
     *
     * @param  \App\User  $user
     * @param  \App\Nfcofins  $nfcofins
     * @return mixed
     */
    public function delete(User $user, Nfcofins $nfcofins)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','nfcofins.index')->first();
    }
}
