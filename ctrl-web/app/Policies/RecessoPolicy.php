<?php

namespace App\Policies;

use Session;
use App\User;
use App\Recesso;
use Illuminate\Auth\Access\HandlesAuthorization;

class RecessoPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct() {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the recesso.
     *
     * @param  \App\User  $user
     * @param  \App\Recesso  $recesso
     * @return mixed
     */
    public function view(User $user, Recesso $recesso)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return $permissoes;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create recessos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return $permissoes;

        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the recesso.
     *
     * @param  \App\User  $user
     * @param  \App\Recesso  $recesso
     * @return mixed
     */
    public function update(User $user, Recesso $recesso)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return $permissoes;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the recesso.
     *
     * @param  \App\User  $user
     * @param  \App\Recesso  $recesso
     * @return mixed
     */
    public function delete(User $user, Recesso $recesso)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return $permissoes;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Recesso $recesso)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;
        
        $empresa = $this->empresa_id == $recesso->empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    private function getPermissoes()
    {
        $this->empresa_id =  Session::get('empresa_padrao')->id;
        return $this->permissoes->where('descricao','recessos.index')->first();
    }
}
