<?php

namespace App\Policies;

use DB;
use Session;
use App\User;
use Exception;
use App\Cliente;
use App\Menuuser;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientePolicy
{
    use HandlesAuthorization;
    protected $empresa_id;

    public function __construct()
    {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the cliente.
     *
     * @param  \App\User  $user
     * @param  \App\Cliente  $cliente
     * @return mixed
     */
    public function view(User $user, Cliente $cliente)
    {      
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->visualizar == 1;
    }

    /**
     * Determine whether the user can create clientes.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->criar == 1;
    }

    /**
     * Determine whether the user can update the cliente.
     *
     * @param  \App\User  $user
     * @param  \App\Cliente  $cliente
     * @return mixed
     */
    public function update(User $user)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->editar == 1;
    }

    /**
     * Determine whether the user can delete the cliente.
     *
     * @param  \App\User  $user
     * @param  \App\Cliente  $cliente
     * @return mixed
     */
    public function delete(User $user, Cliente $cliente)
    {
        //
    }

    public function igualdade(User $user, Cliente $cliente)
    {
        $permission = !is_null($this->getPermission()) && $this->getPermission()->visualizar == 1;
        $igualdade = $cliente->empresa_id == $this->empresa_id;
        
        return $permission && $igualdade;
    }

    public function igualdadeEdicao(User $user, Cliente $cliente)
    {
        $permission = !is_null($this->getPermission()) && $this->getPermission()->editar == 1;
        $igualdade = $cliente->empresa_id == $this->empresa_id;
        
        return $permission && $igualdade;
    }

    private function getPermission()
    {
        return $this->permissoes->where('descricao','cliente.index')->first();
    }
}
