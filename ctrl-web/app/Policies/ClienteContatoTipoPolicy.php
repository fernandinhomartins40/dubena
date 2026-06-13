<?php

namespace App\Policies;

use Session;
use App\User;
use App\Clientecontatotipo;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClienteContatoTipoPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the clientecontatotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Clientecontatotipo  $clientecontatotipo
     * @return mixed
     */
    public function view(User $user, Clientecontatotipo $clientecontatotipo)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->visualizar == 1;
    }

    /**
     * Determine whether the user can create clientecontatotipos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->criar == 1;
    }

    /**
     * Determine whether the user can update the clientecontatotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Clientecontatotipo  $clientecontatotipo
     * @return mixed
     */
    public function update(User $user, Clientecontatotipo $clientecontatotipo)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->editar == 1;
    }

    /**
     * Determine whether the user can delete the clientecontatotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Clientecontatotipo  $clientecontatotipo
     * @return mixed
     */
    public function delete(User $user, Clientecontatotipo $clientecontatotipo)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->deletar == 1;
    }

    private function getPermissions()
    {
        return $this->permissoes->where('descricao','clientecontatotipo.index')->first();
    }
}
