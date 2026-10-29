<?php

namespace App\Http\Controllers\Api\Mobile\Concerns;

use App\Models\Cliente\Cliente;
use Illuminate\Http\Request;

/**
 * Resolução do cliente do app (B-1) — compartilhada pelos sub-controllers do app
 * do cliente (Loja/Perfil/Endereço/Pedido), que antes viviam num único
 * AppClienteController de 572 linhas.
 *
 * Após a F1 (auth real), o caminho seguro é DERIVAR o cliente do token
 * (cliente.user_id) — sem aceitar cliente_id do cliente, fechando o IDOR.
 *
 * PIE R1-02: existia um fallback por `cliente_id` "para a transição". Ele era o
 * próprio IDOR: qualquer autenticado da empresa (inclusive staff sem permissão
 * de cliente — o token do painel é curinga e passa no `approle`) lia CPF,
 * e-mail, nascimento e telefones de QUALQUER cliente por
 * `GET /app/v1/perfil?cliente_id=`. A transição acabou: todo login do app
 * vincula `cliente.user_id` (ClienteAuthService::garantirUsuario), e nenhum
 * app envia `cliente_id`.
 */
trait ResolveClienteDoApp
{
    protected function clienteDoUsuario(Request $request): Cliente
    {
        $user = $request->user();

        $cliente = Cliente::query()
            ->where('empresa_id', $user->empresa_id)
            ->where('user_id', $user->id)
            ->first();
        if (! $cliente) {
            abort(422, 'Cliente do app não vinculado. Refaça o login.');
        }

        return $cliente;
    }
}
