<?php

namespace App\Policies;

use Session;
use App\User;
use App\Colaborador;
use Illuminate\Auth\Access\HandlesAuthorization;

class ColaboradorPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;
    
    function __construct()
    {
        $this->permissoes = Session::get('permissoes');
        $this->empresa_id = Session::get('empresa_padrao')->id;
    }

    /**
     * Determine whether the user can view the colaborador.
     *
     * @param  \App\User  $user
     * @param  \App\Colaborador  $colaborador
     * @return mixed
     */
    public function view(User $user, Colaborador $colaborador)
    {
        if(is_null($this->getPermissoes()))
            return false;
        
        return $this->getPermissoes()->visualizar == 1;
    }

    /**
     * Determine whether the user can create colaboradors.
     *
     * @param  \App\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        if(is_null($this->getPermissoes()))
            return false;
        
        return $this->getPermissoes()->criar == 1;
    }

    /**
     * Determine whether the user can update the colaborador.
     *
     * @param  \App\User  $user
     * @param  \App\Colaborador  $colaborador
     * @return mixed
     */
    public function update(User $user, Colaborador $colaborador)
    {
        if(is_null($this->getPermissoes()))
            return false;
        
        return $this->getPermissoes()->editar == 1;
    }

    /**
     * Determine whether the user can delete the colaborador.
     *
     * @param  \App\User  $user
     * @param  \App\Colaborador  $colaborador
     * @return mixed
     */
    public function delete(User $user, Colaborador $colaborador)
    {
        if(is_null($this->getPermissoes()))
            return false;
        
        return $this->getPermissoes()->deletar == 1;
    }

    public function igualdade(User $user, Colaborador $colaborador)
    {
        $empresa = $this->empresa_id == $colaborador->empresa_id;
        $permissao = !is_null($this->getPermissoes()) && $this->getPermissoes()->visualizar == 1;

        return $empresa && $permissao;
    }

    public function igualdadeEdicao(User $user, Colaborador $colaborador)
    {
        $empresa = $this->empresa_id == $colaborador->empresa_id;
        $permissao = !is_null($this->getPermissoes()) && $this->getPermissoes()->editar == 1;
        return $empresa && $permissao;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','colaborador.index')->first();
    }
}
