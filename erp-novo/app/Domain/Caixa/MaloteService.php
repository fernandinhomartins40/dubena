<?php

namespace App\Domain\Caixa;

use App\Domain\Pedido\EfeitoPedido;
use App\Models\Caixa\Conta;
use App\Models\EmpresaConfig;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use App\Models\Pedido\Pedido;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fechamento de malote (T4.3) — o acerto de valores do entregador.
 *
 * **O que é.** No modelo disk-gás, o entregador sai com mercadoria e volta com
 * dinheiro, cheque e comprovantes de cartão. O malote é a conferência desse
 * retorno: lista os pedidos que ele entregou no período, soma por condição de
 * pagamento, e — uma vez conferido — baixa as parcelas numa conta específica.
 *
 * **Por que é serviço próprio.** O `CaixaService` sabe baixar título; ele não
 * sabe *quais* títulos formam o acerto de um entregador num turno. Essa seleção
 * é a regra do malote, extraída do legado (`FechamentomaloteController@getPedidos`
 * + `processaFinanceiro`): pedidos do período, do setor, do colaborador,
 * excluindo os cancelados — e a baixa vai para `empresa_configs.dados.maloteconta_id`.
 *
 * **O que NÃO faz.** Não altera pedido, não muda situação, não cria financeiro.
 * Confere e baixa. No legado, `store()` mexia no status do pedido junto com a
 * baixa, o que tornava impossível refazer a conferência sem efeito colateral —
 * aqui a baixa é a única escrita.
 *
 * ⚠️ **Condicionada à decisão do dono (T4.3).** O plano exige confirmar se o
 * acerto físico de malote ainda acontece na operação. Esta implementação existe
 * para que, se a resposta for "sim", não seja preciso escrever nada às vésperas
 * do cutover. Ela **não** afirma que o processo continua — a rota está viva mas
 * nada a chama automaticamente. Se a resposta for "não", remover é apagar o
 * service, o controller e 4 linhas de rota.
 */
class MaloteService
{
    public function __construct(private CaixaService $caixa)
    {
    }

    /**
     * Conferência: os pedidos do período e o que cada um ainda deve render.
     *
     * @return array{
     *   pedidos: list<array<string,mixed>>,
     *   por_condicao: list<array<string,mixed>>,
     *   totais: array<string,mixed>
     * }
     */
    public function conferir(
        int $empresaId,
        string $inicio,
        string $fim,
        ?int $setorId = null,
        ?int $entregadorUserId = null,
    ): array {
        $di = Carbon::parse($inicio)->startOfDay();
        $df = Carbon::parse($fim)->endOfDay();

        $pedidos = Pedido::query()
            ->with(['cliente:id,nome', 'situacao', 'condicao'])
            ->where('empresa_id', $empresaId)
            ->whereBetween('datahora', [$di, $df])
            ->when($setorId !== null, fn ($q) => $q->where('setor_id', $setorId))
            ->when($entregadorUserId !== null, fn ($q) => $q->where('entregador_user_id', $entregadorUserId))
            ->orderBy('id')
            ->get()
            // Cancelado não entra no acerto: não houve venda a receber.
            ->reject(fn (Pedido $p) => $p->situacao?->efeito === EfeitoPedido::CANCELADO)
            ->values();

        $parcelasPorPedido = $this->parcelasAbertasDe($pedidos);

        $linhas = [];
        $porCondicao = [];
        $valorTotal = 0.0;
        $aBaixar = 0.0;

        foreach ($pedidos as $p) {
            $abertas = $parcelasPorPedido[$p->id] ?? collect();
            $pendente = round((float) $abertas->sum('valor'), 2);
            $valor = round((float) $p->valor_venda, 2);

            $valorTotal += $valor;
            $aBaixar += $pendente;

            $condId = (int) ($p->condicaopagamento_id ?? 0);
            $condDesc = (string) ($p->condicao->descricao ?? 'Sem condição');

            $porCondicao[$condId] ??= [
                'condicao_id' => $condId, 'condicao' => $condDesc, 'pedidos' => 0, 'valor' => 0.0,
            ];
            $porCondicao[$condId]['pedidos']++;
            $porCondicao[$condId]['valor'] = round($porCondicao[$condId]['valor'] + $valor, 2);

            $linhas[] = [
                'pedido_id' => (int) $p->id,
                'cliente' => $p->cliente->nome ?? '',
                'situacao' => $p->situacao?->descricao ?? '',
                'valor' => $valor,
                'condicao' => $condDesc,
                'parcelas_abertas' => $abertas->count(),
                'valor_a_baixar' => $pendente,
                // Já baixado aparece na conferência mas não entra na baixa: o
                // conferente precisa VER que o pedido existe, senão acha que sumiu.
                'ja_baixado' => $abertas->isEmpty(),
            ];
        }

        return [
            'pedidos' => $linhas,
            'por_condicao' => array_values($porCondicao),
            'totais' => [
                'pedidos' => count($linhas),
                'valor_total' => round($valorTotal, 2),
                'valor_a_baixar' => round($aBaixar, 2),
            ],
        ];
    }

    /**
     * Fecha o malote: baixa as parcelas em aberto dos pedidos informados.
     *
     * @param  list<int>  $pedidoIds
     * @return array{baixadas:int, valor:float, conta_id:int, ignorados:list<int>}
     */
    public function fechar(int $empresaId, array $pedidoIds, ?int $contaId = null, ?int $userId = null): array
    {
        if ($pedidoIds === []) {
            throw ValidationException::withMessages(['pedidos' => 'Selecione ao menos um pedido.']);
        }

        $conta = $contaId ?? $this->contaDoMalote($empresaId);

        if ($conta === null) {
            throw ValidationException::withMessages([
                'conta_id' => 'Nenhuma conta de malote configurada. Defina em Empresas → Contábil, '
                    .'ou informe a conta nesta operação.',
            ]);
        }

        $this->exigirContaDaEmpresa($conta, $empresaId);

        return DB::transaction(function () use ($empresaId, $pedidoIds, $conta, $userId) {
            $pedidos = Pedido::query()
                ->where('empresa_id', $empresaId)
                ->whereIn('id', $pedidoIds)
                ->get();

            $parcelasPorPedido = $this->parcelasAbertasDe($pedidos);

            $itens = [];
            $ignorados = [];
            $valor = 0.0;

            foreach ($pedidos as $p) {
                $abertas = $parcelasPorPedido[$p->id] ?? collect();

                if ($abertas->isEmpty()) {
                    // Já acertado. Não é erro — travar o fechamento inteiro por
                    // isso faria o conferente caçar o pedido na lista. Reporta.
                    $ignorados[] = (int) $p->id;

                    continue;
                }

                foreach ($abertas as $parcela) {
                    $itens[] = ['parcela_id' => (int) $parcela->id];
                    $valor += (float) $parcela->valor;
                }
            }

            if ($itens === []) {
                throw ValidationException::withMessages([
                    'pedidos' => 'Nenhuma parcela em aberto nos pedidos selecionados — o malote já foi fechado.',
                ]);
            }

            $this->caixa->baixarTitulos($conta, $itens, $userId);

            return [
                'baixadas' => count($itens),
                'valor' => round($valor, 2),
                'conta_id' => $conta,
                'ignorados' => $ignorados,
            ];
        });
    }

    /**
     * Parcelas ainda não baixadas, agrupadas por pedido.
     *
     * Uma consulta para todos os pedidos em vez de uma por pedido: o malote de
     * um turno movimentado tem centenas de linhas, e o N+1 aqui seria sentido
     * na tela do conferente.
     *
     * @param  Collection<int,Pedido>  $pedidos
     * @return array<int,Collection<int,FinanceiroParcela>>
     */
    private function parcelasAbertasDe(Collection $pedidos): array
    {
        $financeiroIds = $pedidos->pluck('financeiro_id')->filter()->unique()->values()->all();

        if ($financeiroIds === []) {
            return [];
        }

        $parcelas = FinanceiroParcela::query()
            ->whereIn('financeiro_id', $financeiroIds)
            ->where('baixado', false)
            ->get()
            ->groupBy('financeiro_id');

        // Títulos cancelados não entram: o pedido pode ter financeiro que foi
        // estornado, e baixar uma parcela dele criaria receita que não existe.
        $cancelados = Financeiro::query()
            ->whereIn('id', $financeiroIds)
            ->where('cancelado', true)
            ->pluck('id')
            ->flip();

        $porPedido = [];

        foreach ($pedidos as $p) {
            $finId = (int) ($p->financeiro_id ?? 0);

            $porPedido[$p->id] = ($finId > 0 && ! $cancelados->has($finId))
                ? ($parcelas[$finId] ?? collect())
                : collect();
        }

        return $porPedido;
    }

    /** Conta de malote configurada para a empresa (`dados.maloteconta_id`). */
    private function contaDoMalote(int $empresaId): ?int
    {
        $config = EmpresaConfig::query()->where('empresa_id', $empresaId)->first();
        $id = (int) ($config?->dados['maloteconta_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * A conta tem de ser da mesma empresa.
     *
     * A RLS já isola por tenant, mas quem chama informa o id: sem esta checagem
     * explícita, um id de outra empresa viraria erro obscuro em vez de mensagem
     * clara.
     */
    private function exigirContaDaEmpresa(int $contaId, int $empresaId): void
    {
        $existe = Conta::query()->where('id', $contaId)->where('empresa_id', $empresaId)->exists();

        if (! $existe) {
            throw ValidationException::withMessages(['conta_id' => 'Conta não encontrada nesta empresa.']);
        }
    }
}
