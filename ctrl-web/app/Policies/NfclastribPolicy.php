<?php

namespace App\Policies;

use App\User;
use App\Nfclastrib;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Session;

class NfclastribPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }
    /**
     * Determine whether the user can view the nfclastrib.
     *
     * @param  \App\User  $user
     * @param  \App\Nfclastrib  $nfclastrib
     * @return mixed
     */
    public function view(User $user, Nfclastrib $nfclastrib)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create nfclastribs.
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
     * Determine whether the user can update the nfclastrib.
     *
     * @param  \App\User  $user
     * @param  \App\Nfclastrib  $nfclastrib
     * @return mixed
     */
    public function update(User $user, Nfclastrib $nfclastrib)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the nfclastrib.
     *
     * @param  \App\User  $user
     * @param  \App\Nfclastrib  $nfclastrib
     * @return mixed
     */
    public function delete(User $user, Nfclastrib $nfclastrib)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao', 'nfclastrib.index')->first();
    }
}
