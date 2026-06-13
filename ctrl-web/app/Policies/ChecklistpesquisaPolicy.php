<?php

namespace App\Policies;

use Session;
use App\User;
use App\Checklistpesquisa;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChecklistpesquisaPolicy
{
    use HandlesAuthorization;

    protected $permissoes;

    function __construct() {
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the checklistpesquisa.
     *
     * @param  \App\User  $user
     * @param  \App\Checklistpesquisa  $checklistpesquisa
     * @return mixed
     */
    public function view(User $user, Checklistpesquisa $checklistpesquisa)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1 && $permissoes->criar == 1;
    }

    /**
     * Determine whether the user can create checklistpesquisas.
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
     * Determine whether the user can update the checklistpesquisa.
     *
     * @param  \App\User  $user
     * @param  \App\Checklistpesquisa  $checklistpesquisa
     * @return mixed
     */
    public function update(User $user, Checklistpesquisa $checklistpesquisa)
    {
        //
    }

    /**
     * Determine whether the user can delete the checklistpesquisa.
     *
     * @param  \App\User  $user
     * @param  \App\Checklistpesquisa  $checklistpesquisa
     * @return mixed
     */
    public function delete(User $user, Checklistpesquisa $checklistpesquisa)
    {
        //
    }

    public function acesso(User $user, Checklistpesquisa $checklistpesquisa)
    {
        $empresas = $user->getEmpresaListAttribute();
        $permissoes = $this->getPermissoes();
        
        if(is_null($permissoes))
            return false;

        $empresa = $empresas->contains($checklistpesquisa->empresa_id);

        return $empresa && $permissoes->visualizar == 1;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','checklist.index')->first();
    }
}
