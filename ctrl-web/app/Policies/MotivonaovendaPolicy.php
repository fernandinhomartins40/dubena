<?php

namespace App\Policies;

use Session;
use App\User;
use App\Motivonaovenda;
use Illuminate\Auth\Access\HandlesAuthorization;

class MotivonaovendaPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct() {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the motivonaovenda.
     *
     * @param  \App\User  $user
     * @param  \App\Motivonaovenda  $motivonaovenda
     * @return mixed
     */
    public function view(User $user, Motivonaovenda $motivonaovenda)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create motivonaovendas.
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
     * Determine whether the user can update the motivonaovenda.
     *
     * @param  \App\User  $user
     * @param  \App\Motivonaovenda  $motivonaovenda
     * @return mixed
     */
    public function update(User $user, Motivonaovenda $motivonaovenda)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the motivonaovenda.
     *
     * @param  \App\User  $user
     * @param  \App\Motivonaovenda  $motivonaovenda
     * @return mixed
     */
    public function delete(User $user, Motivonaovenda $motivonaovenda)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','motivonaovenda.index')->first();
    }
}
