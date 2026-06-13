<?php

namespace App\Policies;

use App\User;
use Session;
use App\Nfsituacao;
use Illuminate\Auth\Access\HandlesAuthorization;

class NfsituacaoPolicy
{

    use HandlesAuthorization;

    protected $permissoes;

    function __construct()
    {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the nfsituacao.
     *
     * @param  \App\User  $user
     * @param  \App\Nfsituacao  $nfsituacao
     * @return mixed
     */
    public function view(User $user, Nfsituacao $nfsituacao)
    {
        if (is_null($this->getPermission()))
            return false;

        return $this->getPermission()->visualizar == 1;
    }

    /**
     * Determine whether the user can create nfsituacaos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        if (is_null($this->getPermission()))
            return false;

        return $this->getPermission()->criar == 1;
    }

    /**
     * Determine whether the user can update the nfsituacao.
     *
     * @param  \App\User  $user
     * @param  \App\Nfsituacao  $nfsituacao
     * @return mixed
     */
    public function update(User $user, Nfsituacao $nfsituacao)
    {
        if (is_null($this->getPermission()))
            return false;

        return $this->getPermission()->editar == 1;
    }

    /**
     * Determine whether the user can delete the nfsituacao.
     *
     * @param  \App\User  $user
     * @param  \App\Nfsituacao  $nfsituacao
     * @return mixed
     */
    public function delete(User $user, Nfsituacao $nfsituacao)
    {
        if (is_null($this->getPermission()))
            return false;

        return $this->getPermission()->deletar == 1;
    }

    public function igualdade(User $user, Nfsituacao $nfsituacao)
    {
        $permission = !is_null($this->getPermission()) && $this->getPermission()->visualizar == 1;
        $igualdade = $nfsituacao->empresa_id == $this->empresa_id;

        return $permission && $igualdade;
    }

    private function getPermission()
    {
        return $this->permissoes->where('descricao', 'nfsituacao.index')->first();
    }

}
