<?php

namespace App\Policies;

use Session;
use App\User;
use App\Pedidooperacao;
use Illuminate\Auth\Access\HandlesAuthorization;

class PedidooperacaoPolicy
{
    use HandlesAuthorization;

    protected $grupo_id;
    protected $permissoes;

    function __construct() {
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        $this->permissoes = Session::get('permissoes');
    }

    /**
     * Determine whether the user can view the pedidooperacao.
     *
     * @param  \App\User  $user
     * @param  \App\Pedidooperacao  $pedidooperacao
     * @return mixed
     */
    public function view(User $user, Pedidooperacao $pedidooperacao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->visualizar == 1;
    }

    /**
     * Determine whether the user can create pedidooperacaos.
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
     * Determine whether the user can update the pedidooperacao.
     *
     * @param  \App\User  $user
     * @param  \App\Pedidooperacao  $pedidooperacao
     * @return mixed
     */
    public function update(User $user, Pedidooperacao $pedidooperacao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->editar == 1;
    }

    /**
     * Determine whether the user can delete the pedidooperacao.
     *
     * @param  \App\User  $user
     * @param  \App\Pedidooperacao  $pedidooperacao
     * @return mixed
     */
    public function delete(User $user, Pedidooperacao $pedidooperacao)
    {
        $permissoes = $this->getPermissoes();

        if(is_null($permissoes))
            return false;

        return $permissoes->deletar == 1;
    }

    public function igualdade(User $user, Pedidooperacao $pedidooperacao)
    {
        $permissoes = $this->getPermissoes();

        $grupo = $this->grupo_id == $pedidooperacao->grupo_id;

        if(is_null($permissoes))
            return false;

        return $grupo && $permissoes->visualizar == 1;
    }    

    private function getPermissoes()
    {
        return $this->permissoes->where('descricao','pedidooperacao.index')->first();
    }
}
