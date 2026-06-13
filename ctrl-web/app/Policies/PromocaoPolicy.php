<?php

namespace App\Policies;

use Session;
use App\User;
use App\Promocao;
use Illuminate\Auth\Access\HandlesAuthorization;

class PromocaoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the promocao.
     *
     * @param  \App\User  $user
     * @param  \App\Promocao  $promocao
     * @return mixed
     */
    public function view(User $user, Promocao $promocao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create promocaos.
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
     * Determine whether the user can update the promocao.
     *
     * @param  \App\User  $user
     * @param  \App\Promocao  $promocao
     * @return mixed
     */
    public function update(User $user, Promocao $promocao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the promocao.
     *
     * @param  \App\User  $user
     * @param  \App\Promocao  $promocao
     * @return mixed
     */
    public function delete(User $user, Promocao $promocao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Promocao $promocao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa_id = Session::get('empresa_padrao')->id;
        $empresa = $promocao->empresa_id == $empresa_id;

        return $permissoes->visualizar == 1;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','promocao.index')->first();
    }
}
