<?php

use App\Domain\Tenant\TenantAccessDeniedException;
use App\Domain\Tenant\TenantEnvelopeResolver;
use App\Models\Cliente\Cliente;
use App\Models\Pedido\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Str;

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

/**
 * Barreira de tenant do tempo real (F1, item 3 do gate).
 *
 * `podeAcessarEmpresa()` responde pelo modelo LEGADO: vale um vínculo em
 * `empresa_user`, e a flag `support` devolve `true` para qualquer empresa. Num
 * SaaS isso deixa entrar no canal ao vivo de outro tenant quem não tem grant
 * aprovado nenhum — o dado não vaza pela RLS, vaza pelo WebSocket.
 *
 * Com o enforcement ligado a fronteira passa a ser o grant aprovado, resolvido
 * pelo mesmo `TenantEnvelopeResolver` do HTTP e dos jobs. Enquanto o switch
 * estiver desligado o comportamento legado é preservado, para não derrubar a
 * operação atual antes da conversão.
 */
$autorizaEmpresa = function ($user, int $empresaId): bool {
    if (! $user instanceof User) {
        return false;
    }

    if (config('saas_transformation.enforcement.tenant_envelope')) {
        try {
            return app(TenantEnvelopeResolver::class)
                ->resolveFor($user, $empresaId, (string) Str::uuid())
                ->canRead($empresaId);
        } catch (TenantAccessDeniedException) {
            return false; // sem titularidade/membership/grant aprovados: fecha.
        }
    }

    return $user->podeAcessarEmpresa($empresaId);
};

// Canal da empresa: a SPA/admin escuta o fluxo de pedidos da empresa ATIVA.
Broadcast::channel('empresa.{empresaId}.pedidos', fn ($user, int $empresaId) => $autorizaEmpresa($user, $empresaId), ['guards' => ['sanctum']]);

// Canal da CENTRAL DE LOGÍSTICA (L2): o painel operacional escuta a fila de
// distribuição (novo pedido, atribuição, posição agregada). Mesma barreira.
Broadcast::channel('empresa.{empresaId}.central', fn ($user, int $empresaId) => $autorizaEmpresa($user, $empresaId), ['guards' => ['sanctum']]);

/**
 * Autoriza um usuário num canal de pedido: precisa ser do MESMO tenant e ser parte
 * do pedido (cliente dono via cliente.user_id, ou entregador/atendente). Closure
 * compartilhada pelos canais pedido.{id} e pedido.{id}.entregador (sem função
 * global, para o arquivo poder ser carregado mais de uma vez sem redeclarar).
 */
$autorizaPedido = function ($user, int $pedidoId) use ($autorizaEmpresa): bool {
    // Pedido é tenant-scoped; sem tenant resolvido no broadcasting auth, filtramos
    // explicitamente pela empresa do usuário (defense-in-depth).
    $pedido = Pedido::withoutTenant()->find($pedidoId);

    if ($pedido === null) {
        return false;
    }

    // A empresa vem do PEDIDO e é validada contra o grant — filtrar por
    // `$user->empresa_id` só olhava a empresa padrão do usuário e ignorava tanto
    // o multi-empresa quanto, com enforcement ligado, a fronteira aprovada.
    if (! $autorizaEmpresa($user, (int) $pedido->empresa_id)) {
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
