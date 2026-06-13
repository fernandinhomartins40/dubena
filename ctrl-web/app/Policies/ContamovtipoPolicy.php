<?php

namespace App\Policies;

use Session;
use App\User;
use App\Contamovimentotipo;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContamovtipoPolicy
{
    use HandlesAuthorization;

    protected $grupo_id;
    protected $permissoes;

    function __construct()
    {
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the contamovimentotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Contamovimentotipo  $contamovimentotipo
     * @return mixed
     */
    public function view(User $user, Contamovimentotipo $contamovimentotipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create contamovimentotipos.
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
     * Determine whether the user can update the contamovimentotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Contamovimentotipo  $contamovimentotipo
     * @return mixed
     */
    public function update(User $user, Contamovimentotipo $contamovimentotipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the contamovimentotipo.
     *
     * @param  \App\User  $user
     * @param  \App\Contamovimentotipo  $contamovimentotipo
     * @return mixed
     */
    public function delete(User $user, Contamovimentotipo $contamovimentotipo)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Contamovimentotipo $contamovimentotipo)
    {
        
        $permissoes = $this->getPermissoes();
        $grupo = $this->grupo_id == $contamovimentotipo->grupo_id;

        if(is_null($permissoes))
            return false;

        return $grupo;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','contamovimentotipo.index')->first();
    }
}
