<?php

namespace App\Policies;

use Session;
use App\User;
use App\Colaboradorcomissao;
use Illuminate\Auth\Access\HandlesAuthorization;

class ColaboradorcomissaoPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the colaboradorcomissao.
     *
     * @param  \App\User  $user
     * @param  \App\Colaboradorcomissao  $colaboradorcomissao
     * @return mixed
     */
    public function view(User $user, Colaboradorcomissao $colaboradorcomissao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create colaboradorcomissaos.
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
     * Determine whether the user can update the colaboradorcomissao.
     *
     * @param  \App\User  $user
     * @param  \App\Colaboradorcomissao  $colaboradorcomissao
     * @return mixed
     */
    public function update(User $user, Colaboradorcomissao $colaboradorcomissao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the colaboradorcomissao.
     *
     * @param  \App\User  $user
     * @param  \App\Colaboradorcomissao  $colaboradorcomissao
     * @return mixed
     */
    public function delete(User $user, Colaboradorcomissao $colaboradorcomissao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Colaboradorcomissao $colaboradorcomissao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;
        
        $empresa = $colaboradorcomissao->empresa_id == $this->empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','colaboradorcomissoes.index')->first();
    }
}
