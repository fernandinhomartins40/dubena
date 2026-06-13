<?php

namespace App\Policies;

use Session;
use App\User;
use App\Spedcontribuicao;
use Illuminate\Auth\Access\HandlesAuthorization;

class SpedcontribuicaoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the spedcontribuicao.
     *
     * @param  \App\User  $user
     * @param  \App\Spedcontribuicao  $spedcontribuicao
     * @return mixed
     */
    public function view(User $user, Spedcontribuicao $spedcontribuicao)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->visualizar == 1;
    }

    /**
     * Determine whether the user can create spedcontribuicaos.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->criar == 1;
    }

    /**
     * Determine whether the user can update the spedcontribuicao.
     *
     * @param  \App\User  $user
     * @param  \App\Spedcontribuicao  $spedcontribuicao
     * @return mixed
     */
    public function update(User $user, Spedcontribuicao $spedcontribuicao)
    {
        if(is_null($this->getPermission()))
            return false;

        return $this->getPermission()->editar == 1;
    }

    /**
     * Determine whether the user can delete the spedcontribuicao.
     *
     * @param  \App\User  $user
     * @param  \App\Spedcontribuicao  $spedcontribuicao
     * @return mixed
     */
    public function delete(User $user, Spedcontribuicao $spedcontribuicao)
    {
        if(is_null($this->getPermission()))
            return false;
            
        return $this->getPermission()->deletar == 1;
    }

    public function igualdade(User $user, Spedcontribuicao $spedcontribuicao)
    {
        $empresa_padrao = Session::get('empresa_padrao')->id;

        if(is_null($this->getPermission()))
            return false;
            
        $empresa = $spedcontribuicao->empresa_id == $empresa_padrao;

        return $this->getPermission()->visualizar == 1 && $empresa;
    }

    private function getPermission()
    {
        return Session::get('permissoes')->where('descricao','spedcontribuicao.index')->first();
    }
}
