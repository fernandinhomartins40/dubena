<?php

use App\Models\Cliente\Cliente;
use App\Models\Pedido\Pedido;
use Illuminate\Support\Facades\Broadcast;

/*
| Autorização de canais de broadcasting (P5) — a 2ª barreira do tempo real.
|
| Canais PRIVADOS namespaced por tenant/entidade. A autorização aqui é o que
| impede o vazamento entre empresas no tempo real: um usuário só entra no canal
| da SUA empresa / do SEU pedido. Sem isso, qualquer autenticado poderia escutar
| eventos de outro tenant. Espelha o sigilo garantido pela RLS no lado dos dados.
*/

// A rota /broadcasting/auth é registrada em bootstrap/app.php (withBroadcasting)
// com o guard 'sanctum' — senão o callback receberia user nulo e a checagem de
// posse não valeria. `authorize` falha com 403 quando o callback retorna false.

// Canal da empresa: a SPA/admin escuta o fluxo de pedidos da empresa ATIVA.
// Autoriza se o usuário pertence à empresa (própria ou multi-empresa permitida).
Broadcast::channel('empresa.{empresaId}.pedidos', function ($user, int $empresaId) {
    return method_exists($user, 'podeAcessarEmpresa') && $user->podeAcessarEmpresa($empresaId);
}, ['guards' => ['sanctum']]);

// Canal do pedido: o CLIENTE dono (ou o entregador/colaborador da empresa) escuta
// a evolução de um pedido específico. Valida posse dentro do tenant.
Broadcast::channel('pedido.{pedidoId}', function ($user, int $pedidoId) {
    // Pedido é tenant-scoped; sem tenant resolvido no broadcasting auth, filtramos
    // explicitamente pela empresa do usuário (defense-in-depth).
    $pedido = Pedido::withoutTenant()
        ->where('id', $pedidoId)
        ->where('empresa_id', $user->empresa_id)
        ->first();

    if ($pedido === null) {
        return false;
    }

    // Cliente dono do pedido (via cliente.user_id) OU entregador/atendente do pedido.
    $clienteUserId = Cliente::withoutTenant()->where('id', $pedido->cliente_id)->value('user_id');

    return (int) $clienteUserId === (int) $user->id
        || (int) $pedido->entregador_user_id === (int) $user->id
        || (int) $pedido->atendente_user_id === (int) $user->id;
}, ['guards' => ['sanctum']]);
