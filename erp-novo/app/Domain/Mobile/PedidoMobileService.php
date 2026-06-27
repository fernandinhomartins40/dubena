<?php

namespace App\Domain\Mobile;

use App\Domain\Monitora\MonitoraService;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Models\Cliente\Cliente;
use App\Models\Crm\Promocao;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoAvaliacao;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use Illuminate\Validation\ValidationException;

/**
 * PedidoMobileService (N10) — porta o MobileAppProcessor do legado: cria pedido a
 * partir do app fazendo o MATCHING de cliente/setor por geolocalização, e delega
 * a criação ao PedidoService (N4) — sem reescrever a regra de venda.
 *
 * Regras de negócio do app portadas do PedidoController legado (app/Api):
 *  - 1 pedido PENDENTE por cliente (legado: "Já existe um pedido pendente");
 *  - cancelar só enquanto não concluído (entregue);
 *  - avaliar 1x por pedido (nota 1–5 ou ignorado), mensagem ≤140.
 * O bridge HTTP getToLink/link/ApiResources do legado é ELIMINADO (monólito).
 */
class PedidoMobileService
{
    public function __construct(
        private PedidoService $pedidos,
        private CatalogoMobileService $catalogo,
        private MonitoraService $geofence,
        private PushService $push,
    ) {}

    /**
     * Notifica o cliente (push) sobre a situação do pedido, mirando os devices do
     * usuário ligado ao cliente (F5). No-op se o cliente não tem usuário vinculado
     * ou se não há credencial FCM (dev/CI). @return int devices alcançados
     */
    public function notificarStatus(Pedido $pedido): int
    {
        $userId = $pedido->cliente?->user_id;
        if (! $userId) {
            return 0;
        }

        $efeito = $pedido->situacao?->efeito;
        [$titulo, $corpo] = match (true) {
            $efeito === EfeitoPedido::CONCLUIDO => ['Pedido entregue', 'Seu pedido foi entregue. Conte-nos como foi!'],
            $efeito === EfeitoPedido::CANCELADO => ['Pedido cancelado', 'Seu pedido foi cancelado.'],
            default => ['Pedido atualizado', 'Seu pedido foi atualizado: '.($pedido->situacao?->descricao ?? '')],
        };

        return $this->push->paraUsuario($userId, $titulo, $corpo, ['pedido_id' => (string) $pedido->id, 'acao' => 'orderUpdated']);
    }

    /**
     * Encontra o cliente mais próximo de uma coordenada na empresa (raio em km).
     * Usa a fórmula de Haversine simplificada (sem PostGIS) — suficiente p/ matching.
     */
    public function clientePorGeoloc(int $empresaId, float $lat, float $lng, float $raioKm = 0.15): ?Cliente
    {
        return Cliente::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->get()
            ->map(fn (Cliente $c) => [$c, $this->distanciaKm($lat, $lng, (float) $c->latitude, (float) $c->longitude)])
            ->filter(fn ($par) => $par[1] <= $raioKm)
            ->sortBy(fn ($par) => $par[1])
            ->first()[0] ?? null;
    }

    /**
     * Setor de entrega. Quando há coordenada, resolve por GEOFENCE (cerca poligonal
     * com setor que contém o ponto — F5); cai para o 1º setor ativo se nenhuma cerca
     * cobrir o ponto ou se não houver coordenada.
     */
    public function setorDeEntrega(int $empresaId, ?float $lat = null, ?float $lng = null): ?Setor
    {
        if ($lat !== null && $lng !== null) {
            $setorId = $this->geofence->setorPorPonto($empresaId, $lat, $lng);
            if ($setorId !== null) {
                $setor = Setor::query()->where('empresa_id', $empresaId)->where('ativo', true)->find($setorId);
                if ($setor) {
                    return $setor;
                }
            }
        }

        return Setor::query()->where('empresa_id', $empresaId)->where('ativo', true)->orderBy('id')->first();
    }

    /**
     * Cria um pedido vindo do app. Resolve cliente (por id ou geoloc) e setor.
     *
     * @param  array<string,mixed>  $payload  cliente_id|lat/lng, pedidosituacao_id, itens[]
     */
    public function criarDoApp(int $empresaId, int $grupoId, array $payload): Pedido
    {
        $cliente = ! empty($payload['cliente_id'])
            ? Cliente::query()->where('empresa_id', $empresaId)->find($payload['cliente_id'])
            : $this->clientePorGeoloc($empresaId, (float) $payload['lat'], (float) $payload['lng']);

        if (! $cliente) {
            throw ValidationException::withMessages(['cliente' => 'Cliente não localizado (id ou geolocalização).']);
        }

        // Coordenada de entrega: a do payload ou a do cliente (para o geofence F5).
        $lat = isset($payload['lat']) ? (float) $payload['lat'] : ($cliente->latitude !== null ? (float) $cliente->latitude : null);
        $lng = isset($payload['lng']) ? (float) $payload['lng'] : ($cliente->longitude !== null ? (float) $cliente->longitude : null);

        $setor = $this->setorDeEntrega($empresaId, $lat, $lng);
        if (! $setor) {
            throw ValidationException::withMessages(['setor' => 'Nenhum setor de entrega ativo.']);
        }

        // Regra do legado: 1 pedido PENDENTE por cliente.
        if ($this->temPedidoPendente($cliente->id)) {
            throw ValidationException::withMessages(['pedido' => 'Você já tem um pedido em andamento.']);
        }

        $itens = $this->aplicarCupomNosItens(
            $payload['itens'] ?? [],
            ! empty($payload['codigo_cupom']) ? $this->catalogo->validarCupom($grupoId, (string) $payload['codigo_cupom']) : null,
        );

        return $this->pedidos->criar([
            'empresa_id' => $empresaId,
            'grupo_id' => $grupoId,
            'cliente_id' => $cliente->id,
            'pedidosituacao_id' => (int) $payload['pedidosituacao_id'],
            'setor_id' => $setor->id,
            'datahora' => now(),
            'observacao' => $payload['observacao'] ?? null,
        ], $itens);
    }

    /**
     * Distribui o desconto percentual do cupom em cada item (campo `desconto`), que o
     * PedidoService usa para compor valor_total/valor_desconto. Sem cupom, retorna os
     * itens inalterados. O preço unitário é resolvido pelo PedidoService quando ausente.
     *
     * @param  list<array<string,mixed>>  $itens
     * @return list<array<string,mixed>>
     */
    private function aplicarCupomNosItens(array $itens, ?Promocao $cupom): array
    {
        if ($cupom === null) {
            return $itens;
        }

        $pct = (float) ($cupom->desconto_percentual ?? 0);
        if ($pct <= 0) {
            return $itens;
        }

        return array_map(function (array $item) use ($pct) {
            $preco = (float) ($item['preco_unitario'] ?? Produto::query()->whereKey($item['produto_id'])->value('preco_venda') ?? 0);
            $qtd = (float) ($item['quantidade'] ?? 0);
            $item['desconto'] = round($preco * $qtd * $pct / 100, 2);

            return $item;
        }, $itens);
    }

    /** Existe pedido em situação de efeito PENDENTE para o cliente? */
    public function temPedidoPendente(int $clienteId): bool
    {
        return Pedido::query()
            ->where('cliente_id', $clienteId)
            ->whereHas('situacao', fn ($q) => $q->where('efeito', EfeitoPedido::PENDENTE->value))
            ->exists();
    }

    /**
     * Cancela um pedido do cliente. Só é possível enquanto NÃO concretizado
     * (entregue/concluído) — espelha o cancelOrder do legado. Reaproveita o
     * PedidoService (estorno de estoque/financeiro fica a cargo dele).
     */
    public function cancelar(Pedido $pedido): Pedido
    {
        if ($pedido->situacao?->efeito === EfeitoPedido::CONCLUIDO) {
            throw ValidationException::withMessages(['pedido' => 'Pedido já encerrado; não pode ser cancelado.']);
        }
        if ($pedido->situacao?->efeito === EfeitoPedido::CANCELADO) {
            throw ValidationException::withMessages(['pedido' => 'Pedido já está cancelado.']);
        }

        $cancelada = PedidoSituacao::query()
            ->where('grupo_id', $pedido->grupo_id)
            ->where('efeito', EfeitoPedido::CANCELADO->value)
            ->where('ativo', true)
            ->orderBy('id')->first();

        if (! $cancelada) {
            throw ValidationException::withMessages(['situacao' => 'Nenhuma situação de cancelamento configurada.']);
        }

        return $this->pedidos->mudarSituacao($pedido, $cancelada->id);
    }

    /**
     * Registra a avaliação do pedido (nota 1–5 + mensagem) ou marca como ignorada.
     * 1 avaliação por pedido (legado: hasWithOrder).
     *
     * @param  array{rating?:int|null, mensagem?:string|null, ignorado?:bool}  $dados
     */
    public function avaliar(Pedido $pedido, array $dados): PedidoAvaliacao
    {
        if (PedidoAvaliacao::query()->where('pedido_id', $pedido->id)->exists()) {
            throw ValidationException::withMessages(['pedido' => 'Pedido já foi avaliado.']);
        }

        $ignorado = (bool) ($dados['ignorado'] ?? false);
        $mensagem = $dados['mensagem'] ?? null;

        if ($mensagem !== null && mb_strlen($mensagem) > 140) {
            throw ValidationException::withMessages(['mensagem' => 'Mensagem deve ter no máximo 140 caracteres.']);
        }

        $rating = $ignorado ? null : (int) ($dados['rating'] ?? 0);
        if (! $ignorado && ($rating < 1 || $rating > 5)) {
            throw ValidationException::withMessages(['rating' => 'Avalie o pedido de 1 a 5.']);
        }

        return PedidoAvaliacao::create([
            'empresa_id' => $pedido->empresa_id,
            'pedido_id' => $pedido->id,
            'rating' => $rating,
            'mensagem' => $ignorado ? null : $mensagem,
            'ignorado' => $ignorado,
        ]);
    }

    /**
     * Histórico de pedidos do cliente (mais recentes primeiro).
     *
     * @return list<array<string,mixed>>
     */
    public function historico(int $empresaId, int $clienteId, int $limite = 50): array
    {
        return Pedido::query()
            ->where('empresa_id', $empresaId)
            ->where('cliente_id', $clienteId)
            ->with(['situacao:id,descricao,efeito', 'itens:id,pedido_id,produto_id,quantidade,preco_unitario'])
            ->orderByDesc('datahora')
            ->limit($limite)->get()
            ->map(fn (Pedido $p) => [
                'id' => $p->id,
                'datahora' => $p->datahora?->toIso8601String(),
                'situacao' => $p->situacao?->descricao,
                'efeito' => $p->situacao?->efeito?->value,
                'valor_venda' => (float) $p->valor_venda,
                'itens' => $p->itens->map(fn ($i) => [
                    'produto_id' => $i->produto_id,
                    'quantidade' => (float) $i->quantidade,
                    'preco_unitario' => (float) $i->preco_unitario,
                ]),
            ])->all();
    }

    /** Distância Haversine em km. */
    private function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
