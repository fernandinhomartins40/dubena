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

// Canal da CENTRAL DE LOGÍSTICA (L2): o painel operacional escuta a fila de
// distribuição (novo pedido, atribuição, posição agregada). Mesma barreira de
// tenant do canal de pedidos — só quem pertence à empresa entra.
Broadcast::channel('empresa.{empresaId}.central', function ($user, int $empresaId) {
    return method_exists($user, 'podeAcessarEmpresa') && $user->podeAcessarEmpresa($empresaId);
}, ['guards' => ['sanctum']]);

/**
 * Autoriza um usuário num canal de pedido: precisa ser do MESMO tenant e ser parte
 * do pedido (cliente dono via cliente.user_id, ou entregador/atendente). Closure
 * compartilhada pelos canais pedido.{id} e pedido.{id}.entregador (sem função
 * global, para o arquivo poder ser carregado mais de uma vez sem redeclarar).
 */
$autorizaPedido = function ($user, int $pedidoId): bool {
    // Pedido é tenant-scoped; sem tenant resolvido no broadcasting auth, filtramos
    // explicitamente pela empresa do usuário (defense-in-depth).
    $pedido = Pedido::withoutTenant()
        ->where('id', $pedidoId)
        ->where('empresa_id', $user->empresa_id)
        ->first();

    if ($pedido === null) {
        return false;
    }

    $clienteUserId = Cliente::withoutTenant()->where('id', $pedido->cliente_id)->value('user_id');

    return (int) $clienteUserId === (int) $user->id
        || (int) $pedido->entregador_user_id === (int) $user->id
        || (int) $pedido->atendente_user_id === (int) $user->id;
};

// Canal do pedido: o CLIENTE dono (ou o entregador/colaborador da empresa) escuta
// a evolução de um pedido específico.
Broadcast::channel('pedido.{pedidoId}', fn ($user, int $pedidoId) => $autorizaPedido($user, $pedidoId), ['guards' => ['sanctum']]);

// Canal da POSIÇÃO do entregador de um pedido (P6): mesma regra de posse — o cliente
// dono acompanha o entregador no mapa, em tempo real, só do SEU pedido. Sem isso,
// qualquer autenticado poderia rastrear entregadores alheios.
Broadcast::channel('pedido.{pedidoId}.entregador', fn ($user, int $pedidoId) => $autorizaPedido($user, $pedidoId), ['guards' => ['sanctum']]);
