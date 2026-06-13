<?php

namespace App\Policies;

use Session;
use App\User;
use App\Documentotipo;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentotipoPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the documentotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Documentotipo  $documentotipo
     * @return mixed
     */
    public function view(User $user, Documentotipo $documentotipo)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->visualizar == 1;
    }

    /**
     * Determine whether the user can create documentotipos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->criar == 1;
    }

    /**
     * Determine whether the user can update the documentotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Documentotipo  $documentotipo
     * @return mixed
     */
    public function update(User $user, Documentotipo $documentotipo)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->editar == 1;
    }

    /**
     * Determine whether the user can delete the documentotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Documentotipo  $documentotipo
     * @return mixed
     */
    public function delete(User $user, Documentotipo $documentotipo)
    {
        if(is_null($this->getPermissions()))
            return false;

        return $this->getPermissions()->deletar == 1;
    }

    private function getPermissions()
    {
        return $this->permissoes->where('descricao','documentotipo.index')->first();
    }
}
