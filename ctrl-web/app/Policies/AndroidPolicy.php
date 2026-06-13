<?php

namespace App\Policies;

use Session;
use App\User;
use App\Android;
use Illuminate\Auth\Access\HandlesAuthorization;

class AndroidPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the android.
     *
     * @param  \App\User  $user
     * @param  \App\Android  $android
     * @return mixed
     */
    public function view(User $user, Android $android)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create androids.
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
     * Determine whether the user can update the android.
     *
     * @param  \App\User  $user
     * @param  \App\Android  $android
     * @return mixed
     */
    public function update(User $user, Android $android)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the android.
     *
     * @param  \App\User  $user
     * @param  \App\Android  $android
     * @return mixed
     */
    public function delete(User $user, Android $android)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Android $android)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa_id = Session::get('empresa_padrao')->id;
        $empresa = $android->empresa_id == $empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    private function getPermissoes()
    {
        return Session::get('permissoes')->where('descricao','android.index')->first();
    }
}

