<?php

namespace App\Policies;

use Session;
use App\User;
use App\Pedidomotivoatraso;
use Illuminate\Auth\Access\HandlesAuthorization;

class PedidomotivoatrasoPolicy
{
    use HandlesAuthorization;

    protected $grupo_id;
    protected $permissoes;

    function __construct() {
        $this->permissoes = Session::get('permissoes');
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
    }

    /**
     * Determine whether the user can view the pedidomotivoatraso.
     *
     * @param  \App\User  $user
     * @param  \App\Pedidomotivoatraso  $pedidomotivoatraso
     * @return mixed
     */
    public function view(User $user, Pedidomotivoatraso $pedidomotivoatraso)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create pedidomotivoatrasos.
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
     * Determine whether the user can update the pedidomotivoatraso.
     *
     * @param  \App\User  $user
     * @param  \App\Pedidomotivoatraso  $pedidomotivoatraso
     * @return mixed
     */
    public function update(User $user, Pedidomotivoatraso $pedidomotivoatraso)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the pedidomotivoatraso.
     *
     * @param  \App\User  $user
     * @param  \App\Pedidomotivoatraso  $pedidomotivoatraso
     * @return mixed
     */
    public function delete(User $user, Pedidomotivoatraso $pedidomotivoatraso)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Pedidomotivoatraso $pedidomotivoatraso)
    {
        $grupo = $this->grupo_id == $pedidomotivoatraso->grupo_id;

        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;
        
        return $grupo && $permissoes->visualizar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','pedidomotivoatraso.index')->first();
    }
}
