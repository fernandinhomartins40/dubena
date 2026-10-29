<?php

namespace App\Domain\Cobranca;

use App\Domain\Alerta\AlertaService;
use App\Domain\Financeiro\BaixaService;
use App\Domain\Mobile\SituacaoPagamento;
use App\Models\Caixa\ContaMovimento;
use App\Models\Cobranca\PixCobranca;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Financeiro\Financeiro;
use App\Models\Mobile\PagamentoOnline;
use App\Models\Pedido\Pedido;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * O pagamento online do app (PIX ou cartão) e o título do pedido — uma regra só.
 *
 * **O defeito que isto fecha (auditoria PIE, R3-08/R2-02).** O PIX pago e o
 * cartão autorizado não tocavam o financeiro: na entrega nascia um título EM
 * ABERTO do mesmo valor, o `financeiro:notificar-vencidos` cobrava o cliente
 * que já tinha pago e o malote esperava do entregador um dinheiro que estava no
 * banco. Além disso o app gerava PIX até para pedido em dinheiro, dava para
 * pagar o mesmo pedido duas vezes e o cancelamento de pedido pago não deixava
 * rastro do estorno devido.
 *
 * **Regras definidas pelo dono (29/10):**
 *  - o título do pedido pago online nasce BAIXADO (na conclusão); se o PIX
 *    chega depois da conclusão, o webhook baixa o título que já existe;
 *  - PIX só é gerado quando a condição do pedido é PIX (`pix_online`);
 *  - pedido cancelado, já pago ou com cartão em análise não aceita novo
 *    pagamento;
 *  - cancelar pedido pago é ACEITO e vira pendência de estorno na central de
 *    alertas (o estorno no PSP é manual).
 */
final class PagamentoOnlineDoPedido
{
    public const ORIGEM_ALERTA = 'estorno_pagamento_online';

    public function __construct(
        private BaixaService $baixas,
        private AlertaService $alertas,
    ) {}

    /**
     * Pagamento online confirmado do pedido, se houver.
     *
     * @return array{origem: string, valor: float, referencia: string}|null
     */
    public function confirmado(Pedido $pedido): ?array
    {
        $pix = PixCobranca::withoutTenant()
            ->where('pedido_id', $pedido->id)
            ->where('situacao', SituacaoPix::CONCLUIDA->value)
            ->latest('id')->first();
        if ($pix) {
            return ['origem' => 'pix', 'valor' => (float) $pix->valor, 'referencia' => "PIX txid {$pix->txid}"];
        }

        $cartao = PagamentoOnline::withoutTenant()
            ->where('pedido_id', $pedido->id)
            ->whereIn('situacao', [SituacaoPagamento::AUTORIZADO->value, SituacaoPagamento::CAPTURADO->value])
            ->latest('id')->first();
        if ($cartao) {
            return ['origem' => 'cartao', 'valor' => (float) $cartao->valor, 'referencia' => "cartão tid {$cartao->tid}"];
        }

        return null;
    }

    /**
     * Recusa iniciar pagamento online. Chamar DENTRO da transação que vai criar
     * a cobrança: o lock no pedido serializa dois "pagar" simultâneos (duplo
     * toque, reenvio após timeout), e o segundo enxerga o que o primeiro gravou.
     */
    public function exigirPagavel(Pedido $pedido, bool $exigirCondicaoPix = false): void
    {
        $pedido = Pedido::withoutTenant()
            ->with(['situacao' => fn ($q) => $q->withoutGlobalScopes()])
            ->whereKey($pedido->id)->lockForUpdate()->firstOrFail();

        if ($pedido->situacao?->efeito?->cancela()) {
            throw ValidationException::withMessages(['pedido' => 'Pedido cancelado não pode ser pago.']);
        }

        if ($exigirCondicaoPix) {
            $pix = $pedido->condicaopagamento_id
                && (bool) CondicaoPagamento::query()->withoutGlobalScopes()
                    ->whereKey($pedido->condicaopagamento_id)->value('pix_online');
            if (! $pix) {
                throw ValidationException::withMessages([
                    'pedido' => 'A forma de pagamento deste pedido não é PIX: o pagamento é feito na entrega.',
                ]);
            }
        }

        if ($this->confirmado($pedido) !== null) {
            throw ValidationException::withMessages(['pedido' => 'Este pedido já está pago.']);
        }

        $emAnalise = PagamentoOnline::withoutTenant()
            ->where('pedido_id', $pedido->id)
            ->whereIn('situacao', [SituacaoPagamento::PENDENTE->value, SituacaoPagamento::INDETERMINADO->value])
            ->exists();
        if ($emAnalise) {
            throw ValidationException::withMessages([
                'pedido' => 'Há um pagamento com cartão em análise para este pedido. Aguarde a confirmação.',
            ]);
        }
    }

    /**
     * Baixa o título do pedido com o pagamento online. Valor divergente (o
     * pedido mudou depois de pago) não baixa: vira alerta para conciliar — baixar
     * pelo valor pago esconderia a diferença, e pelo valor do título inventaria
     * dinheiro.
     *
     * @param  array{origem: string, valor: float, referencia: string}  $pagamento
     */
    public function baixarTitulo(Pedido $pedido, Financeiro $titulo, array $pagamento): void
    {
        if (abs((float) $titulo->valor - $pagamento['valor']) > 0.009) {
            Log::error('Pagamento online do pedido difere do titulo — nao baixado.', [
                'pedido_id' => $pedido->id, 'financeiro_id' => $titulo->id,
                'valor_titulo' => (float) $titulo->valor, 'valor_pago' => $pagamento['valor'],
            ]);
            $this->alertar($pedido, 'conciliar', 'Pagamento online diferente do título do pedido', sprintf(
                'Pedido #%d: pago R$ %s (%s), título de R$ %s. O título ficou em aberto para conciliação.',
                $pedido->id, number_format($pagamento['valor'], 2, ',', '.'), $pagamento['referencia'],
                number_format((float) $titulo->valor, 2, ',', '.'),
            ), $pagamento);

            return;
        }

        foreach ($titulo->parcelas()->where('baixado', false)->get() as $parcela) {
            $this->baixas->baixar(
                (int) $parcela->id,
                (int) $titulo->empresa_id,
                (float) $parcela->valor,
                $pagamento['origem'],
                reentregaIdempotente: true,
            );
        }
    }

    /** Baixa o título do pedido, se ele já existe (pagamento que chegou depois da conclusão). */
    public function baixarTituloSeExistir(Pedido $pedido): void
    {
        $pagamento = $this->confirmado($pedido);
        if ($pagamento === null) {
            return;
        }

        if ($pedido->situacao?->efeito?->cancela()) {
            // Pago depois de cancelado: o dinheiro entrou e não há venda.
            $this->alertarEstorno($pedido, $pagamento);

            return;
        }

        $titulo = $this->tituloVivo($pedido);
        if ($titulo !== null) {
            $this->baixarTitulo($pedido, $titulo, $pagamento);
        }
    }

    /**
     * Cancelamento de pedido pago: aceito, e o estorno vira pendência.
     *
     * Se o título já tinha nascido baixado pelo pagamento online, as parcelas
     * são reabertas para o cancelamento do título seguir — exceto as que têm
     * movimento de caixa (essas foram recebidas no balcão e seguem a regra
     * normal: estornar o caixa antes).
     */
    public function aoCancelar(Pedido $pedido): void
    {
        $pagamento = $this->confirmado($pedido);
        if ($pagamento === null) {
            return;
        }

        $titulo = $this->tituloVivo($pedido);
        if ($titulo !== null) {
            foreach ($titulo->parcelas()->where('baixado', true)->get() as $parcela) {
                $noCaixa = ContaMovimento::withoutTenant()->where('financeiroparcela_id', $parcela->id)->exists();
                if (! $noCaixa) {
                    $this->baixas->reabrir((int) $parcela->id, (int) $titulo->empresa_id, "cancelamento:{$pagamento['origem']}");
                }
            }
        }

        $this->alertarEstorno($pedido, $pagamento);
    }

    private function tituloVivo(Pedido $pedido): ?Financeiro
    {
        return Financeiro::withoutTenant()
            ->where('empresa_id', $pedido->empresa_id)
            ->where('origem', 'pedido')
            ->where('origem_id', $pedido->id)
            ->where('cancelado', false)
            ->first();
    }

    /** @param  array{origem: string, valor: float, referencia: string}  $pagamento */
    private function alertarEstorno(Pedido $pedido, array $pagamento): void
    {
        $this->alertar($pedido, 'estorno', 'Estornar pagamento de pedido cancelado', sprintf(
            'Pedido #%d foi cancelado com pagamento online confirmado: devolver R$ %s ao cliente no PSP (%s).',
            $pedido->id, number_format($pagamento['valor'], 2, ',', '.'), $pagamento['referencia'],
        ), $pagamento);
    }

    /** @param  array{origem: string, valor: float, referencia: string}  $pagamento */
    private function alertar(Pedido $pedido, string $motivo, string $titulo, string $descricao, array $pagamento): void
    {
        $this->alertas->registrar(
            (int) $pedido->empresa_id,
            (int) $pedido->grupo_id,
            self::ORIGEM_ALERTA,
            // Um alerta por pedido e motivo: reprocessar não duplica.
            self::ORIGEM_ALERTA.":{$motivo}:pedido:{$pedido->id}",
            [
                'severidade' => 'ALTA',
                'titulo' => $titulo,
                'descricao' => $descricao,
                'cliente_id' => $pedido->cliente_id,
                'dados' => ['pedido_id' => $pedido->id] + $pagamento,
            ],
        );
    }
}
