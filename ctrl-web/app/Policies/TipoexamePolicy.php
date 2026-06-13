<?php

namespace App\Policies;

use Session;
use App\User;
use App\Tipoexame;
use Illuminate\Auth\Access\HandlesAuthorization;

class TipoexamePolicy
{
    use HandlesAuthorization;

    protected $permissoes;
    
    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the tipoexame.
     *
     * @param  \App\User  $user
     * @param  \App\Tipoexame  $tipoexame
     * @return mixed
     */
    public function view(User $user, Tipoexame $tipoexame)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create tipoexames.
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
     * Determine whether the user can update the tipoexame.
     *
     * @param  \App\User  $user
     * @param  \App\Tipoexame  $tipoexame
     * @return mixed
     */
    public function update(User $user, Tipoexame $tipoexame)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the tipoexame.
     *
     * @param  \App\User  $user
     * @param  \App\Tipoexame  $tipoexame
     * @return mixed
     */
    public function delete(User $user, Tipoexame $tipoexame)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','tipoexame.index')->first();
    }
}
