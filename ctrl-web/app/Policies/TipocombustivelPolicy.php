<?php

namespace App\Policies;

use Session;
use App\User;
use App\Tipocombustivel;
use Illuminate\Auth\Access\HandlesAuthorization;

class TipocombustivelPolicy
{
    use HandlesAuthorization;

    protected $grupo_id;
    protected $permissoes;

    function __construct() {
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $this->permissoes = Session::Get('permissoes');
    }

    /**
     * Determine whether the user can view the tipocombustivel.
     *
     * @param  \App\User  $user
     * @param  \App\Tipocombustivel  $tipocombustivel
     * @return mixed
     */
    public function view(User $user, Tipocombustivel $tipocombustivel)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create tipocombustivels.
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
     * Determine whether the user can update the tipocombustivel.
     *
     * @param  \App\User  $user
     * @param  \App\Tipocombustivel  $tipocombustivel
     * @return mixed
     */
    public function update(User $user, Tipocombustivel $tipocombustivel)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the tipocombustivel.
     *
     * @param  \App\User  $user
     * @param  \App\Tipocombustivel  $tipocombustivel
     * @return mixed
     */
    public function delete(User $user, Tipocombustivel $tipocombustivel)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }
    
    public function igualdade(User $user, Tipocombustivel $tipocombustivel)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $grupo = $this->grupo_id == $tipocombustivel->grupo_id;

        return $permissoes->visualizar == 1 && $grupo;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','tipocombustivel.index')->first();
    }
}
