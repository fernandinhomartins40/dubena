<?php

namespace App\Domain\Saas;

use App\Domain\Fiscal\ProntidaoFiscalService;
use App\Models\Empresa;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Financeiro\CondicaoPagamento;
use App\Models\Monitora\Cerca;
use App\Models\Produto\Produto;
use App\Models\Rh\Colaborador;

/**
 * PIE R3-A-11 / R3-G-22 — "pronto para vender?" no Início da revenda.
 *
 * A revenda nova descobria a ordem errando: o Novo pedido não fechava (sem
 * condição), o pedido não concretizava (sem estoque), o app do entregador não
 * entrava. Aqui a lista inteira aparece de uma vez, com o porquê e a tela que
 * resolve. Só LÊ o que já existe — nenhum campo novo, nenhuma marcação manual
 * de "feito" que pudesse mentir.
 */
final class ProntoParaVenderService
{
    public function __construct(private ProntidaoFiscalService $fiscal) {}

    /** @return array{pronto: bool, itens: list<array{chave: string, titulo: string, ok: bool, porque: string, link: string}>} */
    public function verificar(Empresa $empresa): array
    {
        $e = (int) $empresa->id;

        $fiscal = $this->fiscal->verificar($empresa);
        $pendentesFiscais = collect($fiscal['itens'])->where('ok', false)->pluck('titulo')->take(3)->implode(', ');

        $itens = [
            [
                'chave' => 'fiscal', 'titulo' => 'Emissão fiscal configurada', 'ok' => $fiscal['pronto'],
                'porque' => 'Sem certificado, regime e dados do emitente a NFC-e/NF-e é recusada na hora de vender.'
                    .($pendentesFiscais !== '' ? " Falta: {$pendentesFiscais}." : ''),
                'link' => "/empresas/{$e}",
            ],
            [
                'chave' => 'preco', 'titulo' => 'Produto com preço',
                'ok' => Produto::withoutTenant()->where('empresa_id', $e)->where('ativo', true)->where('preco_venda', '>', 0)->exists(),
                'porque' => 'Os produtos iniciais nascem com preço zero: o preço é decisão da revenda. Sem ele o pedido sai de graça.',
                'link' => '/produtos/precos',
            ],
            [
                'chave' => 'estoque', 'titulo' => 'Saldo em estoque',
                'ok' => EstoqueSaldo::withoutTenant()->where('empresa_id', $e)->where('quantidade', '>', 0)->exists(),
                'porque' => 'O pedido só concretiza com mercadoria no estoque. Lance a entrada do que há no depósito.',
                'link' => '/estoque',
            ],
            [
                'chave' => 'entregador', 'titulo' => 'Entregador com acesso ao app',
                'ok' => Colaborador::withoutTenant()->where('empresa_id', $e)->where('ativo', true)->where('entregador', true)->whereNotNull('user_id')->exists(),
                'porque' => 'O app do entregador só entra com um colaborador ativo, marcado como entregador e ligado a um usuário.',
                'link' => '/colaboradores',
            ],
            [
                'chave' => 'area', 'titulo' => 'Área de entrega',
                // Mesma regra do marketplace: cerca ativa OU raio a partir da matriz.
                'ok' => Cerca::withoutTenant()->where('empresa_id', $e)->where('ativo', true)->exists()
                    || ($empresa->latitude !== null && $empresa->longitude !== null && (float) $empresa->raio_entrega_km > 0),
                'porque' => 'Sem cerca ou raio de entrega o app do consumidor não acha a revenda no endereço dele.',
                'link' => '/monitora',
            ],
            [
                'chave' => 'condicao', 'titulo' => 'Condição de pagamento',
                'ok' => CondicaoPagamento::withoutGrupo()->where('grupo_id', $empresa->grupo_id)->where('ativo', true)->exists(),
                'porque' => 'Sem condição de pagamento ativa o Novo pedido não fecha e o app não tem como cobrar.',
                'link' => '/configuracoes?tab=financeiro',
            ],
        ];

        return ['pronto' => collect($itens)->every(fn ($i) => $i['ok']), 'itens' => $itens];
    }
}
