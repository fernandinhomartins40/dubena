<?php

namespace App\Policies;

use Session;
use App\User;
use App\Appnotification;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppnotificationPolicy
{
    use HandlesAuthorization;

    // protected $empresa_id;
    protected $permissoes;

    function __construct() {
        // $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the appnotification.
     *
     * @param  \App\User  $user
     * @param  \App\Appnotification  $appnotification
     * @return mixed
     */
    public function view(User $user, Appnotification $appnotification)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create appnotifications.
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
     * Determine whether the user can update the appnotification.
     *
     * @param  \App\User  $user
     * @param  \App\Appnotification  $appnotification
     * @return mixed
     */
    public function update(User $user, Appnotification $appnotification)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the appnotification.
     *
     * @param  \App\User  $user
     * @param  \App\Appnotification  $appnotification
     * @return mixed
     */
    public function delete(User $user, Appnotification $appnotification)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    // public function igualdade(User $user, Appnotification $appnotification)
    // {
    //     $permissoes = $this->getPermissoes();

    //     if(is_null($permissoes))
    //         return false;

    //     $empresa = $appnotification->empresa_id == $this->empresa_id;

    //     return $empresa && $permissoes->visualizar == 1;
    // }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','appnotification.index')->first();
    }
}
