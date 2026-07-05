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
 * (cliente.user_id) — sem aceitar cliente_id do cliente, fechando o IDOR. Mantém
 * fallback por cliente_id (escopado pela empresa do token) só para compat durante
 * a transição de clientes ainda não vinculados a um usuário.
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
        if ($cliente) {
            return $cliente;
        }

        // Fallback (transição): cliente_id informado, ainda escopado pela empresa.
        $clienteId = (int) $request->query('cliente_id', (string) $request->input('cliente_id', 0));
        if ($clienteId <= 0) {
            abort(422, 'Cliente do app não vinculado. Refaça o login.');
        }

        $cliente = Cliente::query()->where('empresa_id', $user->empresa_id)->find($clienteId);
        if (! $cliente) {
            abort(404, 'Cliente não localizado.');
        }

        return $cliente;
    }
}
