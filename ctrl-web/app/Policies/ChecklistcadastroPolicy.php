<?php

namespace App\Policies;

use Session;
use App\User;
use App\Checklistform;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChecklistcadastroPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the checklistform.
     *
     * @param  \App\User  $user
     * @param  \App\Checklistform  $checklistform
     * @return mixed
     */
    public function view(User $user, Checklistform $checklistform)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create checklistforms.
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
     * Determine whether the user can update the checklistform.
     *
     * @param  \App\User  $user
     * @param  \App\Checklistform  $checklistform
     * @return mixed
     */
    public function update(User $user, Checklistform $checklistform)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the checklistform.
     *
     * @param  \App\User  $user
     * @param  \App\Checklistform  $checklistform
     * @return mixed
     */
    public function delete(User $user, Checklistform $checklistform)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Checklistform $checklistform)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        $empresa = $this->empresa_id == $checklistform->empresa_id;

        return $permissoes->visualizar == 1 && $empresa;
    }

    public function acesso(User $user, Checklistform $checklistform)
    {
        $empresas = $user->getEmpresaListAttribute();

        $empresa = $empresas->contains($checklistform->empresa_id);

        return $empresa;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','cadastrochecklist.index')->first();
    }
}
