<?php

namespace App\Policies;

use Session;
use App\User;
use App\Nfoperacao;
use Illuminate\Auth\Access\HandlesAuthorization;

class NfoperacaoPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->permissoes = Session::get('permissoes');
        $this->empresa_id = Session::get('empresa_padrao')->id;
    }

    /**
     * Determine whether the user can view the nfoperacao.
     *
     * @param  \App\User  $user
     * @param  \App\Nfoperacao  $nfoperacao
     * @return mixed
     */
    public function view(User $user, Nfoperacao $nfoperacao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create nfoperacaos.
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
     * Determine whether the user can update the nfoperacao.
     *
     * @param  \App\User  $user
     * @param  \App\Nfoperacao  $nfoperacao
     * @return mixed
     */
    public function update(User $user, Nfoperacao $nfoperacao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the nfoperacao.
     *
     * @param  \App\User  $user
     * @param  \App\Nfoperacao  $nfoperacao
     * @return mixed
     */
    public function delete(User $user, Nfoperacao $nfoperacao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Nfoperacao $nfoperacao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $grupo = $nfoperacao->empresa_id == $this->empresa_id;
        $perm = $permissoes->visualizar == 1;

        return $grupo && $perm;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','nfoperacao.index')->first();
    }
}
