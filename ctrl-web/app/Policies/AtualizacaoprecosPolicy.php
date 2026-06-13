<?php

namespace App\Policies;

use Session;
use App\User;
use App\Atualizacaoprecos;
use Illuminate\Auth\Access\HandlesAuthorization;

class AtualizacaoprecosPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the atualizacaoprecos.
     *
     * @param  \App\User  $user
     * @param  \App\Atualizacaoprecos  $atualizacaoprecos
     * @return mixed
     */
    public function view(User $user, Atualizacaoprecos $atualizacaoprecos)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes)) return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create atualizacaoprecos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes)) return false;

        return $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can update the atualizacaoprecos.
     *
     * @param  \App\User  $user
     * @param  \App\Atualizacaoprecos  $atualizacaoprecos
     * @return mixed
     */
    public function update(User $user, Atualizacaoprecos $atualizacaoprecos)
    {
        //
    }

    /**
     * Determine whether the user can delete the atualizacaoprecos.
     *
     * @param  \App\User  $user
     * @param  \App\Atualizacaoprecos  $atualizacaoprecos
     * @return mixed
     */
    public function delete(User $user, Atualizacaoprecos $atualizacaoprecos)
    {
        //
    }

    public function igualdade(User $user, Atualizacaoprecos $atualizacaoprecos)
    {
        $permissoes = $this->getPermissoes();

        if (is_null($permissoes)) return false;

        $empresa_id = Session::get('empresa_padrao')->id;
        $empresa = $atualizacaoprecos->empresa_id == $empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','atualizarprecos.index')->first();
    }
}
