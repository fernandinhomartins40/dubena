<?php

namespace App\Policies;

use Session;
use App\User;
use App\Veiculotipo;
use Illuminate\Auth\Access\HandlesAuthorization;

class VeiculotipoPolicy
{
    use HandlesAuthorization;

    protected $grupo_id;
    protected $permissoes;

    function __construct() {
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the veiculotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Veiculotipo  $veiculotipo
     * @return mixed
     */
    public function view(User $user, Veiculotipo $veiculotipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
             return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create veiculotipos.
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
     * Determine whether the user can update the veiculotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Veiculotipo  $veiculotipo
     * @return mixed
     */
    public function update(User $user, Veiculotipo $veiculotipo)
    {

        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
             return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the veiculotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Veiculotipo  $veiculotipo
     * @return mixed
     */
    public function delete(User $user, Veiculotipo $veiculotipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
             return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Veiculotipo $veiculotipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
             return false;

        $grupo = $this->grupo_id == $veiculotipo->grupo_id;

        return $permissoes->visualizar == 1 && $grupo;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','veiculotipo.index')->first();
    }
}
