<?php

namespace App\Policies;

use Session;
use App\User;
use App\Centrocusto;
use Illuminate\Auth\Access\HandlesAuthorization;

class CentrocustoPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the centrocusto.
     *
     * @param  \App\User  $user
     * @param  \App\Centrocusto  $centrocusto
     * @return mixed
     */
    public function view(User $user, Centrocusto $centrocusto)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;
        
        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create centrocustos.
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
     * Determine whether the user can update the centrocusto.
     *
     * @param  \App\User  $user
     * @param  \App\Centrocusto  $centrocusto
     * @return mixed
     */
    public function update(User $user, Centrocusto $centrocusto)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;
        
        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the centrocusto.
     *
     * @param  \App\User  $user
     * @param  \App\Centrocusto  $centrocusto
     * @return mixed
     */
    public function delete(User $user, Centrocusto $centrocusto)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;
        
        return $permissoes->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','centrocusto.index')->first();
    }
}
