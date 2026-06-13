<?php

namespace App\Policies;

use Session;
use App\User;
use App\Veiculoentradasaida;
use Illuminate\Auth\Access\HandlesAuthorization;

class VeiculoentradasaidaPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the Veiculoentradasaida.
     *
     * @param  \App\User  $user
     * @param  \App\Veiculoentradasaida  $Veiculoentradasaida
     * @return mixed
     */
    public function view(User $user, Veiculoentradasaida $veiculoentradasaida)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create Veiculoentradasaida.
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
     * Determine whether the user can update the Veiculoentradasaida.
     *
     * @param  \App\User  $user
     * @param  \App\Veiculoentradasaida  $Veiculoentradasaida
     * @return mixed
     */
    public function update(User $user, Veiculoentradasaida $veiculoentradasaida)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the Veiculoentradasaida.
     *
     * @param  \App\User  $user
     * @param  \App\Veiculoentradasaida  $Veiculoentradasaida
     * @return mixed
     */
    public function delete(User $user, Veiculoentradasaida $veiculoentradasaida)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Veiculoentradasaida $veiculoentradasaida)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa = $this->empresa_id == $veiculoentradasaida->empresa_id;

        return $empresa && $permissoes->visualizar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','veiculoentradasaida.index')->first();
    }
}
