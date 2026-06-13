<?php

namespace App\Policies;

use Session;
use App\User;
use App\Veiculopneu;
use Illuminate\Auth\Access\HandlesAuthorization;

class VeiculopneuPolicy
{
    use HandlesAuthorization;

    protected $permissoes;
    protected $empresa_id;

    function __construct() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the veiculopneu.
     *
     * @param  \App\User  $user
     * @param  \App\Veiculopneu  $veiculopneu
     * @return mixed
     */
    public function view(User $user, Veiculopneu $veiculopneu)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create veiculopneus.
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
     * Determine whether the user can update the veiculopneu.
     *
     * @param  \App\User  $user
     * @param  \App\Veiculopneu  $veiculopneu
     * @return mixed
     */
    public function update(User $user, Veiculopneu $veiculopneu)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the veiculopneu.
     *
     * @param  \App\User  $user
     * @param  \App\Veiculopneu  $veiculopneu
     * @return mixed
     */
    public function delete(User $user, Veiculopneu $veiculopneu)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Veiculopneu $veiculopneu)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa = $veiculopneu->empresa_id == $this->empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','veiculopneu.index')->first();
    }
}
