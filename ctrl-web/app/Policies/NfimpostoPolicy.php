<?php

namespace App\Policies;

use Session;
use App\User;
use App\Nfimposto;
use Illuminate\Auth\Access\HandlesAuthorization;

class NfimpostoPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the nfimposto.
     *
     * @param  \App\User  $user
     * @param  \App\Nfimposto  $nfimposto
     * @return mixed
     */
    public function view(User $user, Nfimposto $nfimposto)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create nfimpostos.
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
     * Determine whether the user can update the nfimposto.
     *
     * @param  \App\User  $user
     * @param  \App\Nfimposto  $nfimposto
     * @return mixed
     */
    public function update(User $user, Nfimposto $nfimposto)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the nfimposto.
     *
     * @param  \App\User  $user
     * @param  \App\Nfimposto  $nfimposto
     * @return mixed
     */
    public function delete(User $user, Nfimposto $nfimposto)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Nfimposto $nfimposto)
    {
        $permissoes = $this->getPermissoes();

        $empresa = $nfimposto->empresa_id == $this->empresa_id;
        $perm = $permissoes->visualizar == 1;

        return $empresa && $perm;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','nfimposto.index')->first();
    }
}
