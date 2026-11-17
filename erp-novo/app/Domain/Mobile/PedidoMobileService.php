<?php

namespace App\Domain\Mobile;

use App\Domain\Cliente\GeocodificarClienteJob;
use App\Domain\Monitora\MonitoraService;
use App\Domain\Pedido\CanalVenda;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Shared\Geo;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteEndereco;
use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Estoque\Setor;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoAvaliacao;
use App\Models\Pedido\PedidoSituacao;
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
        private CotacaoMobileService $cotacao,
        private MonitoraService $geofence,
        private PushService $push,
        private MarketplaceService $marketplace,
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

        // `efeito` vai junto (PIE P2-B-08): o toque na notificação decide entre o
        // acompanhamento (pedido aberto) e o histórico (entregue/cancelado) sem
        // precisar de uma ida à API antes de navegar.
        return $this->push->paraUsuario($userId, $titulo, $corpo, [
            'pedido_id' => (string) $pedido->id,
            'acao' => 'orderUpdated',
            'efeito' => (string) ($efeito?->value ?? ''),
        ]);
    }

    /**
     * Encontra o cliente mais próximo de uma coordenada na empresa (raio em km).
     *
     * PF-1 (auditoria): antes carregava TODOS os clientes da empresa e filtrava em
     * PHP (O(N) por pedido — inviável num tenant com dezenas de milhares). Agora um
     * BOUNDING BOX no SQL (lat/lng BETWEEN …, indexado) traz só os candidatos dentro
     * do quadrado do raio; o Haversine refina em PHP sobre esse conjunto pequeno.
     * Portável (sqlite e Postgres, sem PostGIS).
     */
    public function clientePorGeoloc(int $empresaId, float $lat, float $lng, float $raioKm = 0.15): ?Cliente
    {
        $box = Geo::boundingBox($lat, $raioKm * 1000);

        return Cliente::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $box['lat_delta'], $lat + $box['lat_delta']])
            ->whereBetween('longitude', [$lng - $box['lng_delta'], $lng + $box['lng_delta']])
            ->get()
            ->map(fn (Cliente $c) => [$c, Geo::km($lat, $lng, (float) $c->latitude, (float) $c->longitude)])
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

        // PIE P2-B-02: o endereço escolhido no app manda na coordenada. Antes ele
        // era ignorado — cliente novo do app não tem coordenada no cadastro, e a
        // cerca de entrega era pulada (pedido a 250 km aceito com raio de 5 km).
        // Só endereço do PRÓPRIO cliente: id alheio é 422, não fallback.
        // Sem `endereco_id` (o app só o conhece depois de o usuário favoritar),
        // vale o favorito do cliente — o mesmo que a tela de endereços mostra.
        $endereco = null;
        if (! empty($payload['endereco_id'])) {
            $endereco = ClienteEndereco::query()
                ->where('empresa_id', $empresaId)
                ->where('cliente_id', $cliente->id)
                ->find((int) $payload['endereco_id']);
            if (! $endereco) {
                throw ValidationException::withMessages(['endereco_id' => 'Endereço de entrega não encontrado.']);
            }
        } elseif (! isset($payload['lat'])) {
            $endereco = ClienteEndereco::query()
                ->where('empresa_id', $empresaId)
                ->where('cliente_id', $cliente->id)
                ->where('favorito', true)
                ->first();
        }
        if ($endereco !== null) {
            if ($endereco->latitude !== null && $endereco->longitude !== null) {
                $lat = (float) $endereco->latitude;
                $lng = (float) $endereco->longitude;
            }
        }

        // L0 — GEOCODIFICAÇÃO GARANTIDA: a distribuição inteligente (L3) depende de
        // lat/lng do cliente. Se o cliente ainda não foi geocodificado, enfileira o
        // job agora (assíncrono, não bloqueia). Até resolver, a distribuição por
        // proximidade cai no fallback (região/setor), sem travar o pedido.
        if ($cliente->latitude === null || $cliente->longitude === null) {
            GeocodificarClienteJob::dispatch($cliente->id);
        }

        // Revalida a COBERTURA server-side: a escolha da loja no app é UX, não
        // segurança.
        //
        // Vale para toda revenda que DECLAROU área — não só as do marketplace
        // (F6-05). A flag de canal decide se ela aparece na descoberta pública,
        // e nada mais: a área de entrega que ela desenhou vale nos dois casos.
        $this->validarCobertura($empresaId, $lat, $lng);

        $setor = $this->setorDeEntrega($empresaId, $lat, $lng);
        if (! $setor) {
            throw ValidationException::withMessages(['setor' => 'Nenhum setor de entrega ativo.']);
        }

        // PIE R3-E: o preço Gás do Povo era concedido a quem mandasse
        // `gasdopovo: true` — bastava o campo no payload, sem a revenda ter o
        // programa ligado nem o cliente estar marcado como beneficiário no ERP.
        // Fail-closed em dinheiro: só com as duas marcas que JÁ existem.
        if (! empty($payload['gasdopovo']) && ! $this->gasDoPovoPermitido($empresaId, $cliente)) {
            throw ValidationException::withMessages([
                'gasdopovo' => 'O preço Gás do Povo não está disponível para este cadastro. Fale com a revenda.',
            ]);
        }

        // Regra do legado: 1 pedido PENDENTE por cliente.
        if ($this->temPedidoPendente($cliente->id)) {
            throw ValidationException::withMessages(['pedido' => 'Você já tem um pedido em andamento.']);
        }

        // PREÇO SERVER-SIDE (F3c): a mesma cotação usada no checkout precifica o pedido,
        // por condição de pagamento + cupom. O cliente NUNCA define preço/desconto —
        // ignoramos qualquer preco_unitario que venha do payload (anti-fraude).
        $cotacao = $this->cotacao->cotar($empresaId, $grupoId, [
            'itens' => array_map(fn ($i) => [
                'produto_id' => (int) $i['produto_id'],
                'quantidade' => (float) $i['quantidade'],
            ], $payload['itens'] ?? []),
            'condicao_id' => $payload['condicaopagamento_id'] ?? ($payload['condicao_id'] ?? null),
            'codigo_cupom' => $payload['codigo_cupom'] ?? null,
            'gasdopovo' => (bool) ($payload['gasdopovo'] ?? false),
        ]);

        $itens = $this->itensDaCotacao($cotacao);

        // Situação inicial: a informada, ou a 1ª PENDENTE do grupo (o app não precisa
        // conhecer ids de situação — o servidor resolve o estado inicial do pedido).
        $situacaoId = ! empty($payload['pedidosituacao_id'])
            ? (int) $payload['pedidosituacao_id']
            : $this->situacaoPendenteId($grupoId);

        return $this->pedidos->criar([
            // F3-05: pedido do proprio cliente, sem atendente.
            'canal' => CanalVenda::APP_CLIENTE->value,
            'empresa_id' => $empresaId,
            'grupo_id' => $grupoId,
            'cliente_id' => $cliente->id,
            'pedidosituacao_id' => $situacaoId,
            'condicaopagamento_id' => $payload['condicaopagamento_id'] ?? ($payload['condicao_id'] ?? null),
            'setor_id' => $setor->id,
            'datahora' => now(),
            'observacao' => $payload['observacao'] ?? null,
            // F2 — o desconto daqui é do CUPOM, calculado pela cotação no servidor
            // (o cliente nunca define preço nem desconto). Alçada é teto para o
            // desconto que uma PESSOA concede; promoção da empresa não passa por
            // ela. O piso `preco_venda_minimo` do produto continua valendo.
            'desconto_aprovado' => true,
        ], $itens);
    }

    /**
     * Converte os itens precificados pela cotação no formato do PedidoService, com
     * preço unitário e desconto rateado pelo cupom (proporcional ao total do item).
     * O preço vem 100% do servidor — o PedidoService usa esses valores como verdade.
     *
     * @param  array{itens: list<array<string,mixed>>, subtotal: float, desconto: float}  $cotacao
     * @return list<array<string,mixed>>
     */
    private function itensDaCotacao(array $cotacao): array
    {
        $subtotal = (float) ($cotacao['subtotal'] ?? 0);
        $descontoTotal = (float) ($cotacao['desconto'] ?? 0);

        return array_map(function (array $item) use ($subtotal, $descontoTotal) {
            $totalItem = (float) $item['total'];
            // Rateia o desconto do cupom proporcionalmente ao peso do item no subtotal.
            $desconto = $subtotal > 0 ? round($descontoTotal * $totalItem / $subtotal, 2) : 0.0;

            return [
                'produto_id' => (int) $item['produto_id'],
                'quantidade' => (float) $item['quantidade'],
                'preco_unitario' => (float) $item['preco_unitario'],
                'desconto' => $desconto,
            ];
        }, $cotacao['itens'] ?? []);
    }

    /**
     * O pedido só nasce se a revenda atende o ponto de entrega.
     *
     * ## A cobertura não depende da flag de canal (F6-05)
     *
     * Antes, a validação só rodava quando `app_marketplace_ativo` era verdadeiro
     * — a flag de **canal** governava a cobertura **geográfica**. Uma revenda
     * fora do marketplace aceitava pedido do app de qualquer endereço, inclusive
     * de outra cidade, e a área de entrega que ela mesma desenhou era ignorada.
     *
     * São perguntas diferentes: aparecer na descoberta pública é canal; entregar
     * naquele endereço é cobertura. Quem decide a segunda é a configuração de
     * área da revenda.
     *
     * ## Quem não declarou área não é bloqueado
     *
     * `empresaAtendePonto` devolve `false` tanto para "fora da minha área"
     * quanto para "não configurei área nenhuma" — e a maioria das revendas ainda
     * não desenhou cerca. Validar sem essa distinção derrubaria a operação atual
     * inteira, que é pior que o defeito.
     *
     * Sem coordenada também não bloqueia: a geocodificação é assíncrona, e
     * recusar o pedido enquanto ela não roda puniria o cliente por uma fila
     * interna.
     */
    private function validarCobertura(int $empresaId, ?float $lat, ?float $lng): void
    {
        if ($lat === null || $lng === null) {
            return;
        }

        if (! $this->marketplace->empresaTemCoberturaDeclarada($empresaId)) {
            return;
        }

        if (! $this->marketplace->empresaAtendePonto($empresaId, $lat, $lng)) {
            throw ValidationException::withMessages([
                'endereco' => 'Esta revenda não atende o endereço informado. Escolha outra revenda.',
            ]);
        }
    }

    /**
     * Programa ligado pela revenda (`empresa_configs.dados.app.gaspovo_ativo`,
     * o mesmo que o `GET app/v1/config` expõe) E cliente marcado como
     * beneficiário no cadastro do ERP. Quem marca o beneficiário é a revenda;
     * o app não tem como se autodeclarar (o PUT do perfil não aceita o campo).
     */
    private function gasDoPovoPermitido(int $empresaId, Cliente $cliente): bool
    {
        // `first()`, não `value()`: `value` devolve o JSON cru, sem o cast.
        $dados = (array) (EmpresaConfig::query()->where('empresa_id', $empresaId)->first()?->dados ?? []);
        $ativo = (bool) (((array) ($dados['app'] ?? []))['gaspovo_ativo'] ?? false);

        return $ativo && (bool) $cliente->gasdopovo;
    }

    /** 1ª situação ativa de efeito PENDENTE do grupo (estado inicial de um pedido novo). */
    private function situacaoPendenteId(int $grupoId): int
    {
        $situacao = PedidoSituacao::query()
            ->where('grupo_id', $grupoId)
            ->where('efeito', EfeitoPedido::PENDENTE->value)
            ->where('ativo', true)
            ->orderBy('id')->first();

        if (! $situacao) {
            throw ValidationException::withMessages(['situacao' => 'Nenhuma situação inicial (pendente) configurada.']);
        }

        return $situacao->id;
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

        // As guardas acima dão a mensagem certa no caso comum; a origem
        // conferida sob o lock fecha a corrida com o entregador concluindo.
        return $this->pedidos->mudarSituacao($pedido, $cancelada->id, null, [EfeitoPedido::PENDENTE]);
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
        $pedidos = Pedido::query()
            ->where('empresa_id', $empresaId)
            ->where('cliente_id', $clienteId)
            ->with([
                'situacao:id,descricao,efeito',
                'itens:id,pedido_id,produto_id,quantidade,preco_unitario',
                'itens.produto:id,descricao',
            ])
            ->orderByDesc('datahora')
            ->limit($limite)->get();

        // PIE P2-B-15: o app oferecia "Avaliar" em todo pedido concluído e o
        // servidor recusava a repetição — a 2ª avaliação sumia em silêncio.
        // Uma consulta só para a página inteira (não uma por pedido).
        $avaliados = PedidoAvaliacao::query()
            ->whereIn('pedido_id', $pedidos->pluck('id'))
            ->pluck('pedido_id')->flip();

        return $pedidos
            ->map(fn (Pedido $p) => [
                'id' => $p->id,
                'datahora' => $p->datahora?->toIso8601String(),
                'situacao' => $p->situacao?->descricao,
                'efeito' => $p->situacao?->efeito?->value,
                'avaliado' => $avaliados->has($p->id),
                'valor_venda' => (float) $p->valor_venda,
                'itens' => $p->itens->map(fn ($i) => [
                    'produto_id' => $i->produto_id,
                    'descricao' => $i->produto?->descricao,
                    'quantidade' => (float) $i->quantidade,
                    'preco_unitario' => (float) $i->preco_unitario,
                    'total' => round((float) $i->quantidade * (float) $i->preco_unitario, 2),
                ]),
            ])->all();
    }
}
