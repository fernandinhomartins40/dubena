<?php

namespace App\Policies;

use Session;
use App\User;
use App\Sorteio;
use Illuminate\Auth\Access\HandlesAuthorization;

class SorteioPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        // $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }


    /**
     * Determine whether the user can view the Sorteio.
     *
     * @param  \App\User  $user
     * @param  \App\Sorteio  $sorteio
     * @return mixed
     */
    public function view(User $user, Sorteio $sorteio)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create Sorteios.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes))
            return false;

        return $permissoes->criar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao', 'sorteio.index')->first();
    }
}
