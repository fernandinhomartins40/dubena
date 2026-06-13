<?php

namespace App\Policies;

use Session;
use App\User;
use App\Nfpis;
use Illuminate\Auth\Access\HandlesAuthorization;

class NfpisPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct() {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the nfpis.
     *
     * @param  \App\User  $user
     * @param  \App\Nfpis  $nfpis
     * @return mixed
     */
    public function view(User $user, Nfpis $nfpis)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;
        
        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create nfpis.
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
     * Determine whether the user can update the nfpis.
     *
     * @param  \App\User  $user
     * @param  \App\Nfpis  $nfpis
     * @return mixed
     */
    public function update(User $user, Nfpis $nfpis)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;
        
        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the nfpis.
     *
     * @param  \App\User  $user
     * @param  \App\Nfpis  $nfpis
     * @return mixed
     */
    public function delete(User $user, Nfpis $nfpis)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;
        
        return $permissoes->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','nfpis.index')->first();
    }
}
