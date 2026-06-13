<?php

namespace App\Policies;

use App\User;
use App\Nfcst;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Session;

class NfcstPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the nfcst.
     *
     * @param  \App\User  $user
     * @param  \App\Nfcst  $nfcst
     * @return mixed
     */
    public function view(User $user, Nfcst $nfcst)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create nfcsts.
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
     * Determine whether the user can update the nfcst.
     *
     * @param  \App\User  $user
     * @param  \App\Nfcst  $nfcst
     * @return mixed
     */
    public function update(User $user, Nfcst $nfcst)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the nfcst.
     *
     * @param  \App\User  $user
     * @param  \App\Nfcst  $nfcst
     * @return mixed
     */
    public function delete(User $user, Nfcst $nfcst)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao', 'nfcst.index')->first();
    }
}
