<?php

namespace App\Policies;

use Session;
use App\User;
use App\Cidade;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Permissões de Cidade. Fonte legada: flags em `menuusers` para o menu
 * 'cidade.index'. Originalmente lidas só da Session('permissoes') (populada no
 * login do AdminLTE). FASE 3: o painel Filament também usa esta Policy, mas roda
 * fora daquele fluxo — então, se a Session não tiver as permissões, caímos para
 * a consulta direta ao banco (User::podeNoMenu), preservando o comportamento.
 */
class CidadePolicy
{
    use HandlesAuthorization;

    private const ROTA = 'cidade.index';

    public function viewAny(User $user)
    {
        return $this->pode($user, 'visualizar');
    }

    public function view(User $user, Cidade $cidade = null)
    {
        return $this->pode($user, 'visualizar');
    }

    public function create(User $user)
    {
        return $this->pode($user, 'criar');
    }

    public function update(User $user, Cidade $cidade = null)
    {
        return $this->pode($user, 'editar');
    }

    public function delete(User $user, Cidade $cidade = null)
    {
        return $this->pode($user, 'deletar');
    }

    public function deleteAny(User $user)
    {
        return $this->pode($user, 'deletar');
    }

    /**
     * Resolve a flag ('visualizar'|'criar'|'editar'|'deletar') primeiro pela
     * Session (caminho legado AdminLTE) e, na ausência dela, pelo banco.
     */
    private function pode(User $user, string $flag): bool
    {
        $permissoes = Session::get('permissoes');

        if ($permissoes !== null) {
            $p = $permissoes->where('descricao', self::ROTA)->first();
            return $p !== null && (int) $p->{$flag} === 1;
        }

        return $user->podeNoMenu(self::ROTA, $flag);
    }
}
