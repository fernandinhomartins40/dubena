<?php

namespace App\Domain\Venda;

use App\Domain\Pedido\CanalVenda;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Venda\Events\SolicitacaoRecebida;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Rh\Colaborador;
use App\Models\User;
use App\Models\Venda\PedidoSolicitacao;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Central de Vendas (F3) — o antigo call center, com poder de decisão.
 *
 * **O que é.** O franqueado não fatura: ele solicita, e o atendente cria, aprova
 * o desconto e fatura. Este serviço é a fila dessa decisão.
 *
 * **Por que é irmão do CentralService, não extensão.** O
 * `Domain/Logistica/CentralService` distribui ENTREGA — atribui entregador,
 * redistribui, prioriza. Não cria pedido, não fatura, não aprova desconto. São
 * dois momentos do negócio: um decide *quem leva*, o outro decide *se vende e
 * por quanto*. Herdar um do outro misturaria as duas filas.
 *
 * **O que ele reusa em vez de reimplementar:** `PedidoService::criar` para gerar
 * o pedido, a máquina de estados (`EfeitoPedido`) para faturar, o
 * `AlcadaDescontoService` para saber se o desconto cabia sem aprovação, e o
 * canal `empresa.{id}.central` para o tempo real.
 */
class CentralVendasService
{
    public function __construct(
        private PedidoService $pedidos,
        private AlcadaDescontoService $alcada,
    ) {}

    /**
     * Fila da Central: solicitações aguardando decisão, mais antigas primeiro
     * (quem esperou mais é atendido antes — o vendedor está parado no cliente).
     *
     * @param  array{situacao?:string, solicitante_user_id?:int}  $filtros
     * @return Collection<int, PedidoSolicitacao>
     */
    public function fila(int $empresaId, array $filtros = []): Collection
    {
        return PedidoSolicitacao::query()
            ->where('empresa_id', $empresaId)
            ->where('situacao', $filtros['situacao'] ?? SituacaoSolicitacao::PENDENTE->value)
            ->when(
                isset($filtros['solicitante_user_id']),
                fn ($q) => $q->where('solicitante_user_id', $filtros['solicitante_user_id'])
            )
            ->with(['cliente:id,nome', 'solicitante:id,name', 'pedido:id'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Registra a solicitação vinda do campo.
     *
     * Não cria Pedido: rascunho não pode mover estoque nem entrar na fila da
     * logística (ver cabeçalho da migration). O desconto pedido fica guardado
     * como intenção — quem decide se vale é a Central.
     *
     * @param  array<string,mixed>  $dados
     */
    public function solicitar(User $solicitante, array $dados): PedidoSolicitacao
    {
        $itens = $this->precificar($dados['itens'] ?? []);
        if ($itens === []) {
            throw ValidationException::withMessages(['itens' => 'Informe ao menos um item.']);
        }

        $solicitacao = PedidoSolicitacao::create([
            'empresa_id' => $solicitante->empresa_id,
            'grupo_id' => $solicitante->grupo_id,
            'solicitante_user_id' => $solicitante->id,
            'colaborador_id' => Colaborador::query()->where('user_id', $solicitante->id)->value('id'),
            'cliente_id' => (int) $dados['cliente_id'],
            'setor_id' => $dados['setor_id'] ?? null,
            'condicaopagamento_id' => $dados['condicaopagamento_id'] ?? null,
            'itens' => $itens,
            'desconto_solicitado' => (float) ($dados['desconto_solicitado'] ?? 0),
            'justificativa' => $dados['justificativa'] ?? null,
            'observacao' => $dados['observacao'] ?? null,
            'situacao' => SituacaoSolicitacao::PENDENTE,
        ]);

        SolicitacaoRecebida::dispatch($solicitacao->load(['cliente:id,nome', 'solicitante:id,name']));

        return $solicitacao;
    }

    /**
     * Aprova e gera o pedido.
     *
     * `$descontoAprovado` nulo = aprova o que foi pedido; um valor menor =
     * contraproposta do atendente (caso comum: pediu 10%, sai com 5%).
     *
     * O desconto entra rateado nos itens marcado como `desconto_aprovado`, para
     * que a alçada do vendedor não o barre de novo — a aprovação É a autorização.
     * O piso `preco_venda_minimo` do produto continua valendo (é limite do
     * produto, não da pessoa) e pode derrubar a aprovação: correto, ninguém deve
     * vender abaixo do mínimo.
     */
    public function aprovar(PedidoSolicitacao $s, User $atendente, ?float $descontoAprovado = null, ?string $motivo = null): PedidoSolicitacao
    {
        $this->exigirPendente($s);

        $desconto = $descontoAprovado ?? (float) $s->desconto_solicitado;
        if ($desconto < 0) {
            throw ValidationException::withMessages(['desconto_aprovado' => 'Desconto não pode ser negativo.']);
        }

        return DB::transaction(function () use ($s, $atendente, $desconto, $motivo) {
            // Trava a linha: dois atendentes decidindo ao mesmo tempo gerariam
            // dois pedidos para a mesma solicitação.
            $fresco = PedidoSolicitacao::query()->lockForUpdate()->findOrFail($s->id);
            $this->exigirPendente($fresco);

            $pedido = $this->pedidos->criar([
                // F3-05: televendas, com fila e roteiro.
                'canal' => CanalVenda::CENTRAL->value,
                'empresa_id' => $fresco->empresa_id,
                'grupo_id' => $fresco->grupo_id,
                'cliente_id' => $fresco->cliente_id,
                'pedidosituacao_id' => $this->situacaoPendenteId((int) $fresco->grupo_id),
                'condicaopagamento_id' => $fresco->condicaopagamento_id,
                'setor_id' => $fresco->setor_id,
                'user_id' => $atendente->id,
                'datahora' => now(),
                'observacao' => $fresco->observacao,
                // A Central autorizou: não reavaliar a alçada do vendedor.
                'desconto_aprovado' => true,
            ], $this->itensComDesconto($fresco, $desconto));

            $fresco->update([
                'situacao' => SituacaoSolicitacao::APROVADA,
                'decidido_por_user_id' => $atendente->id,
                'decidido_em' => now(),
                'desconto_aprovado' => $desconto,
                'motivo_decisao' => $motivo,
                'pedido_id' => $pedido->id,
            ]);

            return $fresco->refresh();
        });
    }

    /** Recusa. Terminal — o vendedor abre outra solicitação se quiser renegociar. */
    public function recusar(PedidoSolicitacao $s, User $atendente, ?string $motivo = null): PedidoSolicitacao
    {
        $this->exigirPendente($s);

        $s->update([
            'situacao' => SituacaoSolicitacao::RECUSADA,
            'decidido_por_user_id' => $atendente->id,
            'decidido_em' => now(),
            'motivo_decisao' => $motivo,
        ]);

        return $s->refresh();
    }

    /** O próprio solicitante desiste (cliente mudou de ideia na porta). */
    public function cancelar(PedidoSolicitacao $s, User $usuario): PedidoSolicitacao
    {
        $this->exigirPendente($s);

        if ((int) $s->solicitante_user_id !== (int) $usuario->id) {
            throw ValidationException::withMessages([
                'solicitacao' => 'Só quem abriu a solicitação pode cancelá-la.',
            ]);
        }

        $s->update([
            'situacao' => SituacaoSolicitacao::CANCELADA,
            'decidido_em' => now(),
        ]);

        return $s->refresh();
    }

    /**
     * Fatura o pedido de uma solicitação aprovada.
     *
     * Não existe `faturar()` no PedidoService — faturar é transitar para uma
     * situação de efeito CONCLUIDO, e é a máquina de estados que baixa estoque e
     * gera financeiro. Usar `mudarSituacao` preserva a idempotência que já existe
     * (`estoque_movimentado`), em vez de duplicar a movimentação aqui.
     */
    public function faturar(PedidoSolicitacao $s, User $atendente): PedidoSolicitacao
    {
        if ($s->situacao !== SituacaoSolicitacao::APROVADA || $s->pedido_id === null) {
            throw ValidationException::withMessages([
                'solicitacao' => 'Só uma solicitação aprovada, com pedido gerado, pode ser faturada.',
            ]);
        }

        $concluido = PedidoSituacao::query()
            ->where('grupo_id', $s->grupo_id)
            ->where('efeito', EfeitoPedido::CONCLUIDO->value)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->first();

        if ($concluido === null) {
            throw ValidationException::withMessages([
                'situacao' => 'Nenhuma situação de efeito CONCLUIDO configurada para o grupo.',
            ]);
        }

        $this->pedidos->mudarSituacao($s->pedido, $concluido->id, $atendente->id);

        return $s->refresh();
    }

    /**
     * Quanto desta solicitação já caberia na alçada de quem pediu — o atendente
     * vê se o vendedor pedia algo fora do normal ou só o que não podia conceder
     * sozinho.
     */
    public function analiseDeAlcada(PedidoSolicitacao $s): array
    {
        $solicitante = $s->solicitante;
        $tetoTotal = 0.0;

        foreach ($s->itens ?? [] as $item) {
            $produtoId = (int) ($item['produto_id'] ?? 0);
            $precoTabela = (float) ($item['preco_unitario'] ?? 0);

            $r = $this->alcada->tetoDoItem($solicitante, [
                'produto_id' => $produtoId,
                'quantidade' => (float) ($item['quantidade'] ?? 0),
                'preco_unitario' => $precoTabela,
                'setor_id' => $s->setor_id,
                'condicaopagamento_id' => $s->condicaopagamento_id,
            ], $precoTabela);

            $tetoTotal += $r['teto'];
        }

        $pedido = (float) $s->desconto_solicitado;

        return [
            'teto_do_solicitante' => round($tetoTotal, 2),
            'desconto_solicitado' => $pedido,
            'excede_em' => round(max($pedido - $tetoTotal, 0), 2),
        ];
    }

    /**
     * Preço vem do CADASTRO, nunca do app.
     *
     * O legado aceita o preço que o aplicativo enviar
     * (`MobileRepository::getPreco:602` — `if ($isAppNf) return $preco`), e é
     * assim que o vendedor define a própria margem. Aqui o app diz o QUE e
     * QUANTO; o servidor diz POR QUANTO. O que o vendedor pode pedir é desconto
     * — que passa pela Central.
     *
     * @param  list<array<string,mixed>>  $itens
     * @return list<array<string,mixed>>
     */
    private function precificar(array $itens): array
    {
        $ids = array_values(array_unique(array_map(fn ($i) => (int) ($i['produto_id'] ?? 0), $itens)));
        $precos = Produto::query()->whereIn('id', $ids)->pluck('preco_venda', 'id');

        return array_values(array_map(fn (array $i) => [
            'produto_id' => (int) $i['produto_id'],
            'quantidade' => (float) $i['quantidade'],
            'preco_unitario' => (float) ($precos[(int) $i['produto_id']] ?? 0),
        ], $itens));
    }

    private function exigirPendente(PedidoSolicitacao $s): void
    {
        if ($s->situacao->encerrada()) {
            throw ValidationException::withMessages([
                'situacao' => 'Esta solicitação já foi decidida.',
            ]);
        }
    }

    /**
     * Rateia o desconto aprovado pelos itens, proporcional ao peso de cada um —
     * mesma regra do cupom (PedidoMobileService::itensDaCotacao), para que o
     * valor apareça distribuído e o total feche.
     *
     * @return list<array<string,mixed>>
     */
    private function itensComDesconto(PedidoSolicitacao $s, float $desconto): array
    {
        $itens = $s->itens ?? [];

        $subtotal = 0.0;
        foreach ($itens as $i) {
            $subtotal += (float) ($i['quantidade'] ?? 0) * (float) ($i['preco_unitario'] ?? 0);
        }

        $resto = $desconto;
        $ultimo = count($itens) - 1;

        return array_values(array_map(function (array $i, int $idx) use ($subtotal, $desconto, &$resto, $ultimo) {
            $total = (float) ($i['quantidade'] ?? 0) * (float) ($i['preco_unitario'] ?? 0);

            // O último item leva o resto: rateio proporcional com arredondamento
            // por item não fecha a soma, e um centavo perdido aqui vira
            // divergência entre o aprovado e o valor do pedido.
            $parte = $idx === $ultimo
                ? round($resto, 2)
                : ($subtotal > 0 ? round($desconto * $total / $subtotal, 2) : 0.0);

            $resto -= $parte;

            return [
                'produto_id' => (int) $i['produto_id'],
                'quantidade' => (float) $i['quantidade'],
                'preco_unitario' => (float) ($i['preco_unitario'] ?? 0),
                'desconto' => max($parte, 0.0),
            ];
        }, $itens, array_keys($itens)));
    }

    private function situacaoPendenteId(int $grupoId): int
    {
        $situacao = PedidoSituacao::query()
            ->where('grupo_id', $grupoId)
            ->where('efeito', EfeitoPedido::PENDENTE->value)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->first();

        if ($situacao === null) {
            throw ValidationException::withMessages([
                'situacao' => 'Nenhuma situação de efeito PENDENTE configurada para o grupo.',
            ]);
        }

        return (int) $situacao->id;
    }
}
