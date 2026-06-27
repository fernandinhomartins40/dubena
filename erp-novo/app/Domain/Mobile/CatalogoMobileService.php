<?php

namespace App\Domain\Mobile;

use App\Models\Crm\Promocao;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Produto\Produto;
use Illuminate\Validation\ValidationException;

/**
 * CatalogoMobileService (F4) — dados que o app consome ao abrir (produtos, preços e
 * condições de pagamento) e validação de cupom. Substitui os getToOrder/getToLink +
 * coupon do legado (app/Api): no monólito o app lê o catálogo do ERP direto, sem o
 * bridge HTTP de reconciliação.
 */
class CatalogoMobileService
{
    /**
     * Pacote de abertura do app (equivalente ao app/init + root do legado):
     * produtos com preço normal e Gás do Povo, e condições de pagamento ativas.
     *
     * @return array{produtos: list<array<string,mixed>>, condicoes: list<array<string,mixed>>}
     */
    public function init(int $empresaId, int $grupoId, bool $apenasGasPovo = false): array
    {
        return [
            'produtos' => $this->produtos($empresaId, $apenasGasPovo),
            'condicoes' => $this->condicoes($grupoId),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function produtos(int $empresaId, bool $apenasGasPovo = false): array
    {
        return Produto::query()
            ->where('empresa_id', $empresaId)->where('ativo', true)
            ->when($apenasGasPovo, fn ($q) => $q->whereNotNull('preco_gasdopovo'))
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'preco_venda', 'preco_gasdopovo'])
            ->map(fn (Produto $p) => [
                'id' => $p->id,
                'descricao' => $p->descricao,
                'preco' => (float) $p->preco_venda,
                'preco_gasdopovo' => $p->preco_gasdopovo !== null ? (float) $p->preco_gasdopovo : null,
            ])->all();
    }

    /** @return list<array<string,mixed>> */
    public function condicoes(int $grupoId): array
    {
        return CondicaoPagamento::query()
            ->where('grupo_id', $grupoId)->where('ativo', true)
            ->orderBy('descricao')
            ->get(['id', 'descricao', 'num_parcelas', 'a_vista'])
            ->map(fn (CondicaoPagamento $c) => [
                'id' => $c->id,
                'descricao' => $c->descricao,
                'num_parcelas' => (int) $c->num_parcelas,
                'a_vista' => (bool) $c->a_vista,
            ])->all();
    }

    /**
     * Valida um cupom (promoção com código) vigente do grupo. Lança ValidationException
     * quando inválido/expirado — o app mostra a mensagem.
     */
    public function validarCupom(int $grupoId, string $codigo): Promocao
    {
        $promo = Promocao::query()
            ->where('grupo_id', $grupoId)
            ->where('codigo', $codigo)
            ->vigente()
            ->first();

        if (! $promo) {
            throw ValidationException::withMessages(['cupom' => 'Cupom inválido ou expirado.']);
        }

        return $promo;
    }

    /** Aplica o desconto percentual da promoção sobre um valor. */
    public function aplicarDesconto(Promocao $promo, float $valor): float
    {
        $pct = (float) ($promo->desconto_percentual ?? 0);

        return round($valor * (1 - $pct / 100), 2);
    }
}
