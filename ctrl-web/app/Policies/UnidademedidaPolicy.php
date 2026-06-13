<?php

namespace App\Policies;

use Session;
use App\User;
use App\Unidademedida;
use Illuminate\Auth\Access\HandlesAuthorization;

class UnidademedidaPolicy
{
    use HandlesAuthorization;

    protected $grupo_id;
    protected $permissoes;

    function __construct() {
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the unidademedida.
     *
     * @param  \App\User  $user
     * @param  \App\Unidademedida  $unidademedida
     * @return mixed
     */
    public function view(User $user, Unidademedida $unidademedida)
    {
        $permissoes = $this->getPermissoes();
        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create unidademedidas.
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
     * Determine whether the user can update the unidademedida.
     *
     * @param  \App\User  $user
     * @param  \App\Unidademedida  $unidademedida
     * @return mixed
     */
    public function update(User $user, Unidademedida $unidademedida)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the unidademedida.
     *
     * @param  \App\User  $user
     * @param  \App\Unidademedida  $unidademedida
     * @return mixed
     */
    public function delete(User $user, Unidademedida $unidademedida)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Unidademedida $unidademedida)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $grupo = $unidademedida->grupo_id == $this->grupo_id;

        return $permissoes->visualizar == 1 && $grupo;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','unidademedida.index')->first();
    }
}
