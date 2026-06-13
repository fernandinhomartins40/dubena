<?php

namespace App\Policies;

use Session;
use App\User;
use App\Vendaativaocorrenciatipo;
use Illuminate\Auth\Access\HandlesAuthorization;

class OcorrenciatipoVAPolicy
{
    use HandlesAuthorization;

    protected $grupo_id;
    protected $permissoes;

    function __construct() {
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the vendaativaocorrenciatipo.
     *
     * @param  \App\User  $user
     * @param  \App\Vendaativaocorrenciatipo  $vendaativaocorrenciatipo
     * @return mixed
     */
    public function view(User $user, Vendaativaocorrenciatipo $vendaativaocorrenciatipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create vendaativaocorrenciatipos.
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
     * Determine whether the user can update the vendaativaocorrenciatipo.
     *
     * @param  \App\User  $user
     * @param  \App\Vendaativaocorrenciatipo  $vendaativaocorrenciatipo
     * @return mixed
     */
    public function update(User $user, Vendaativaocorrenciatipo $vendaativaocorrenciatipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the vendaativaocorrenciatipo.
     *
     * @param  \App\User  $user
     * @param  \App\Vendaativaocorrenciatipo  $vendaativaocorrenciatipo
     * @return mixed
     */
    public function delete(User $user, Vendaativaocorrenciatipo $vendaativaocorrenciatipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Vendaativaocorrenciatipo $vendaativaocorrenciatipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $grupo = $vendaativaocorrenciatipo->grupo_id == $this->grupo_id;

        return $grupo && $permissoes->visualizar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','vendaativaocorrenciatipos.index')->first();
    }
}
