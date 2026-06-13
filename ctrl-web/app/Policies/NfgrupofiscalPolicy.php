<?php

namespace App\Policies;

use Session;
use App\User;
use App\Nfgrupofiscal;
use Illuminate\Auth\Access\HandlesAuthorization;

class NfgrupofiscalPolicy
{
    use HandlesAuthorization;

    protected $empresa_id;
    protected $permissoes;

    function __construct() {
        $this->permissoes = Session::get('permissoes');
        $this->empresa_id = Session::get('empresa_padrao')->id;
    }

    /**
     * Determine whether the user can view the nfgrupofiscal.
     *
     * @param  \App\User  $user
     * @param  \App\Nfgrupofiscal  $nfgrupofiscal
     * @return mixed
     */
    public function view(User $user, Nfgrupofiscal $nfgrupofiscal)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create nfgrupofiscals.
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
     * Determine whether the user can update the nfgrupofiscal.
     *
     * @param  \App\User  $user
     * @param  \App\Nfgrupofiscal  $nfgrupofiscal
     * @return mixed
     */
    public function update(User $user, Nfgrupofiscal $nfgrupofiscal)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the nfgrupofiscal.
     *
     * @param  \App\User  $user
     * @param  \App\Nfgrupofiscal  $nfgrupofiscal
     * @return mixed
     */
    public function delete(User $user, Nfgrupofiscal $nfgrupofiscal)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Nfgrupofiscal $nfgrupofiscal)
    {
        $permissoes = $this->getPermissoes();
        $empresa = $this->empresa_id == $nfgrupofiscal->empresa_id;
        $perm = !is_null($permissoes) && $permissoes->visualizar == 1;

        return $empresa && $perm;
    }

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','grupofiscal.index')->first();
    }
}
