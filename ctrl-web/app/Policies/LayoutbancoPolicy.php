<?php

namespace App\Policies;

use Session;
use App\User;
use App\Layoutbanco;
use Illuminate\Auth\Access\HandlesAuthorization;

class LayoutbancoPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the layoutbanco.
     *
     * @param  \App\User  $user
     * @param  \App\Layoutbanco  $layoutbanco
     * @return mixed
     */
    public function view(User $user, Layoutbanco $layoutbanco)
    {
        if(is_null($this->getPermissions()))
            return false;
        
        return $this->getPermissions()->visualizar == 1;
    }

    /**
     * Determine whether the user can create layoutbancos.
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
     * Determine whether the user can update the layoutbanco.
     *
     * @param  \App\User  $user
     * @param  \App\Layoutbanco  $layoutbanco
     * @return mixed
     */
    public function update(User $user, Layoutbanco $layoutbanco)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->editar == 1;
    }

    /**
     * Determine whether the user can delete the layoutbanco.
     *
     * @param  \App\User  $user
     * @param  \App\Layoutbanco  $layoutbanco
     * @return mixed
     */
    public function delete(User $user, Layoutbanco $layoutbanco)
    {
        if(is_null($this->getPermissions()))
            return false;
        
        return $this->getPermissions()->deletar == 1;
    }

    private function getPermissions()
    {
        return $this->permissoes->where('descricao','layoutbancos.index')->first();
    }
}
