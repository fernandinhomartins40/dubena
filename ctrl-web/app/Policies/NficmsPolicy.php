<?php

namespace App\Policies;

use Session;
use App\User;
use App\Nficms;
use Illuminate\Auth\Access\HandlesAuthorization;

class NficmsPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the nficms.
     *
     * @param  \App\User  $user
     * @param  \App\Nficms  $nficms
     * @return mixed
     */
    public function view(User $user, Nficms $nficms)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create nficms.
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
     * Determine whether the user can update the nficms.
     *
     * @param  \App\User  $user
     * @param  \App\Nficms  $nficms
     * @return mixed
     */
    public function update(User $user, Nficms $nficms)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the nficms.
     *
     * @param  \App\User  $user
     * @param  \App\Nficms  $nficms
     * @return mixed
     */
    public function delete(User $user, Nficms $nficms)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','nficms.index')->first();
    }
}
