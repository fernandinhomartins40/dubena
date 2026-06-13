<?php

namespace App\Policies;

use Session;
use App\User;
use App\Inventario;
use Illuminate\Auth\Access\HandlesAuthorization;

class InventarioPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the inventario.
     *
     * @param  \App\User  $user
     * @param  \App\Inventario  $inventario
     * @return mixed
     */
    public function view(User $user, Inventario $inventario)
    {
        $permissoes = $this->getPermissoes();

        if( is_null($permissoes) ) return false;
    
         return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create inventarios.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes();

        if( is_null($permissoes) ) return false;
    
        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the inventario.
     *
     * @param  \App\User  $user
     * @param  \App\Inventario  $inventario
     * @return mixed
     */
    public function update(User $user, Inventario $inventario)
    {
        $permissoes = $this->getPermissoes();

        if( is_null($permissoes) ) return false;
    
        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the inventario.
     *
     * @param  \App\User  $user
     * @param  \App\Inventario  $inventario
     * @return mixed
     */
    public function delete(User $user, Inventario $inventario)
    {
        $permissoes = $this->getPermissoes();

        if( is_null($permissoes) ) return false;
    
        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Inventario $inventario)
    {
        $permissoes = $this->getPermissoes();

        if( is_null($permissoes) ) return false;
        $empresa_id = Session::get('empresa_padrao')->id;

        return $permissoes->visualizar == 1 && $empresa_id == $inventario->empresa_id;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','inventario.index')->first();
    }
}
