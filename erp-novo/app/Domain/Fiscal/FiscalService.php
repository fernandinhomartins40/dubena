<?php

namespace App\Domain\Fiscal;

use App\Domain\Fiscal\Contracts\SefazDriver;
use App\Domain\Shared\NumeroSequencialService;
use App\Models\Fiscal\CartaCorrecao;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\Fiscal\InutilizacaoFiscal;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Pedido\Pedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FiscalService (N9) — orquestra a emissão fiscal. PORTA o cálculo de imposto
 * (CalculoImpostoService) e isola a SEFAZ (SefazDriver). A numeração fiscal é
 * obtida com LOCK (NumeroSequencialService do N0) — anti-duplicidade (a regra
 * crítica trataNumNF do legado).
 */
class FiscalService
{
    public function __construct(
        private CalculoImpostoService $imposto,
        private SefazDriver $sefaz,
        private NumeroSequencialService $sequencia,
        private ResolucaoTributariaService $tributacao,
    ) {}

    /**
     * Monta uma nota (RASCUNHO) a partir de um pedido, calculando os impostos por
     * item. Não numera nem transmite — isso é no emitir().
     *
     * A tributação sai da MATRIZ migrada do legado (nf_impostos/nf_imposto_estados),
     * resolvida por operação fiscal × grupo fiscal do produto × UF origem→destino ×
     * consumidor final. Sem operação ou regra cadastrada, falha fechado: emitir
     * com tributação inventada é mais grave do que impedir o faturamento.
     *
     * @param  int|null  $operacaoFiscalId  operação fiscal (natureza/CFOP) da venda;
     *                                      null usa a operação habilitada do produto.
     */
    public function montarDoPedido(
        Pedido $pedido,
        ModeloDocumento $modelo,
        ?int $operacaoFiscalId = null,
    ): NotaFiscal {
        $pedido->loadMissing('itens.produto', 'cliente');
        if ($pedido->itens->isEmpty()) {
            throw ValidationException::withMessages(['pedido' => 'Pedido sem itens para faturar.']);
        }

        $ufEmitente = strtoupper((string) DB::table('empresas')
            ->where('id', $pedido->empresa_id)->value('uf'));
        $ufDestino = strtoupper((string) ($pedido->cliente->uf ?? '')) ?: $ufEmitente;
        // Consumidor final = pessoa física (sem CNPJ), como no legado.
        $consumidorFinal = blank($pedido->cliente?->cnpj);

        return DB::transaction(function () use (
            $pedido, $modelo, $operacaoFiscalId, $ufEmitente, $ufDestino, $consumidorFinal
        ) {
            $nota = NotaFiscal::create([
                'empresa_id' => $pedido->empresa_id,
                'grupo_id' => $pedido->grupo_id,
                'cliente_id' => $pedido->cliente_id,
                'pedido_id' => $pedido->id,
                'modelo' => $modelo->value,
                'tipo' => 'S',
                'serie' => $this->serie($pedido->empresa_id, $modelo),
                'numero' => 0, // numerado só na emissão (sob lock)
                'situacao' => SituacaoNota::RASCUNHO->value,
            ]);

            $totais = ['prod' => 0.0, 'desc' => 0.0, 'icms' => 0.0, 'ipi' => 0.0, 'pis' => 0.0, 'cofins' => 0.0];

            foreach ($pedido->itens as $i => $item) {
                $produto = $item->produto;

                $grupoFiscal = $this->grupoFiscalDoProduto($produto);
                $operacao = $operacaoFiscalId ?? $this->operacaoDoProduto(
                    (int) $produto->id,
                    (int) $pedido->empresa_id,
                    $grupoFiscal,
                );
                if ($operacao === null) {
                    throw ValidationException::withMessages([
                        'operacao_fiscal' => "Produto #{$produto->id} sem operação fiscal habilitada.",
                    ]);
                }

                $regra = $this->tributacao->regraPara(
                    (int) $pedido->empresa_id,
                    $operacao,
                    $grupoFiscal,
                );
                if (! $regra) {
                    throw ValidationException::withMessages([
                        'imposto' => "Produto #{$produto->id} sem regra na matriz tributária para a operação fiscal #{$operacao}.",
                    ]);
                }

                $tributos = $this->tributacao->resolver(
                    $regra,
                    $ufEmitente,
                    $ufDestino,
                    $consumidorFinal,
                );

                $imp = $this->imposto->calcular(array_merge($tributos, [
                    'quantidade' => (float) $item->quantidade,
                    'valor_unitario' => (float) $item->preco_unitario,
                    'desconto' => (float) $item->desconto,
                    'aliq_ipi' => (float) ($produto->nfe_aliq_ipi ?? 0),
                ]));

                $valorTotal = round((float) $item->quantidade * (float) $item->preco_unitario - (float) $item->desconto, 2);
                $nota->itens()->create(array_merge([
                    'produto_id' => $produto->id,
                    'numero_item' => $i + 1,
                    'quantidade' => $item->quantidade,
                    'valor_unitario' => $item->preco_unitario,
                    'valor_total' => $valorTotal,
                    'desconto' => $item->desconto,
                    'cfop' => $this->cfopDe($operacao, $pedido->grupo_id, $ufEmitente, $ufDestino),
                    'cst_icms' => $tributos['cst_icms'],
                ], $imp->toArray()));

                $totais['prod'] += $valorTotal;
                $totais['desc'] += (float) $item->desconto;
                $totais['icms'] += $imp->valorIcms;
                $totais['ipi'] += $imp->valorIpi;
                $totais['pis'] += $imp->valorPis;
                $totais['cofins'] += $imp->valorCofins;
            }

            $nota->update([
                'valor_produtos' => round($totais['prod'], 2),
                'valor_desconto' => round($totais['desc'], 2),
                'valor_icms' => round($totais['icms'], 2),
                'valor_ipi' => round($totais['ipi'], 2),
                'valor_pis' => round($totais['pis'], 2),
                'valor_cofins' => round($totais['cofins'], 2),
                'valor_total' => round($totais['prod'] + $totais['ipi'], 2),
            ]);

            return $nota->refresh()->load('itens');
        });
    }

    /**
     * Emite a nota: NUMERA sob lock (anti-duplicidade) e transmite via SefazDriver.
     */
    public function emitir(NotaFiscal $nota): NotaFiscal
    {
        if ($nota->situacao !== SituacaoNota::RASCUNHO) {
            throw ValidationException::withMessages(['nota' => 'Só rascunho pode ser emitido.']);
        }

        return DB::transaction(function () use ($nota) {
            // Numeração sequencial COM LOCK por empresa+modelo+série.
            $chaveSeq = $nota->modelo->chaveSequencia($nota->empresa_id, $nota->serie);
            $numero = $this->sequencia->proximo($chaveSeq);

            $nota->update([
                'numero' => $numero,
                'situacao' => SituacaoNota::EMITIDA->value,
                'emitida_em' => now(),
            ]);

            $resultado = $this->sefaz->transmitir($nota->refresh());

            if ($resultado['autorizada']) {
                $nota->update([
                    'situacao' => SituacaoNota::AUTORIZADA->value,
                    'chave' => $resultado['chave'],
                    'protocolo' => $resultado['protocolo'],
                ]);
            } else {
                $nota->update([
                    'situacao' => SituacaoNota::REJEITADA->value,
                    'motivo_rejeicao' => $resultado['motivo'],
                ]);
            }

            return $nota->refresh();
        });
    }

    /** Emite direto a partir de um pedido (monta + emite). */
    public function emitirDoPedido(Pedido $pedido, ModeloDocumento $modelo): NotaFiscal
    {
        return $this->emitir($this->montarDoPedido($pedido, $modelo));
    }

    public function cancelar(NotaFiscal $nota, string $justificativa): NotaFiscal
    {
        if (! $nota->situacao->podeCancelar()) {
            throw ValidationException::withMessages(['nota' => 'Só nota autorizada pode ser cancelada.']);
        }
        if (mb_strlen($justificativa) < 15) {
            throw ValidationException::withMessages(['justificativa' => 'Justificativa deve ter ao menos 15 caracteres (regra SEFAZ).']);
        }

        $resultado = $this->sefaz->cancelar($nota, $justificativa);
        if ($resultado['cancelada']) {
            $nota->update(['situacao' => SituacaoNota::CANCELADA->value]);
        }

        return $nota->refresh();
    }

    /**
     * Inutiliza uma faixa de numeração (modelo/série/inicial-final). Registra o
     * evento (auditável) e marca homologada conforme o retorno da SEFAZ.
     */
    public function inutilizar(int $empresaId, int $modelo, int $serie, int $numeroInicial, int $numeroFinal, string $justificativa): InutilizacaoFiscal
    {
        if ($numeroFinal < $numeroInicial) {
            throw ValidationException::withMessages(['numero_final' => 'Número final deve ser ≥ inicial.']);
        }
        if (mb_strlen($justificativa) < 15) {
            throw ValidationException::withMessages(['justificativa' => 'Justificativa deve ter ao menos 15 caracteres (regra SEFAZ).']);
        }

        $resultado = $this->sefaz->inutilizar($empresaId, $modelo, $serie, $numeroInicial, $numeroFinal, $justificativa);

        return InutilizacaoFiscal::create([
            'empresa_id' => $empresaId,
            'modelo' => $modelo,
            'serie' => $serie,
            'numero_inicial' => $numeroInicial,
            'numero_final' => $numeroFinal,
            'justificativa' => $justificativa,
            'protocolo' => $resultado['protocolo'] ?? null,
            'homologada' => (bool) ($resultado['inutilizada'] ?? false),
            'motivo' => $resultado['motivo'] ?? null,
        ]);
    }

    /**
     * Registra uma Carta de Correção (CCE) sobre uma nota autorizada. A sequência
     * é auto-incrementada por nota (nSeqEvento). Correção mínima 15 caracteres.
     */
    public function cartaCorrecao(NotaFiscal $nota, string $correcao): CartaCorrecao
    {
        if (! $nota->situacao->autorizada()) {
            throw ValidationException::withMessages(['nota' => 'Só nota autorizada aceita carta de correção.']);
        }
        if (mb_strlen($correcao) < 15) {
            throw ValidationException::withMessages(['correcao' => 'Correção deve ter ao menos 15 caracteres (regra SEFAZ).']);
        }

        return DB::transaction(function () use ($nota, $correcao) {
            $sequencia = (int) CartaCorrecao::query()->where('nota_fiscal_id', $nota->id)->max('sequencia') + 1;
            $resultado = $this->sefaz->cartaCorrecao($nota, $correcao, $sequencia);

            return CartaCorrecao::create([
                'empresa_id' => $nota->empresa_id,
                'nota_fiscal_id' => $nota->id,
                'sequencia' => $resultado['sequencia'] ?? $sequencia,
                'correcao' => $correcao,
                'protocolo' => $resultado['protocolo'] ?? null,
                'registrada' => (bool) ($resultado['registrada'] ?? false),
                'motivo' => $resultado['motivo'] ?? null,
            ]);
        });
    }

    /**
     * Operação fiscal habilitada para o produto (`produto_operacao_fiscal`, o
     * NFOPERACAOPRODUTOS do legado). Um produto costuma ter várias (venda de
     * vasilhame, venda de GLP a consumidor final, troca...) e nem toda operação
     * tem regra para o grupo fiscal DESTE produto.
     *
     * Por isso a escolha automática prefere, nesta ordem: (1) operação com regra
     * específica do grupo fiscal do produto; (2) operação com regra coringa;
     * (3) menor CFOP. Sem esse desempate, um produto com quatro operações poderia
     * cair justamente na única sem regra e ser tributado pelo padrão — com a regra
     * correta cadastrada ao lado.
     */
    private function operacaoDoProduto(int $produtoId, int $empresaId, ?int $grupoFiscalId): ?int
    {
        return DB::table('produto_operacao_fiscal as pof')
            ->join('operacoes_fiscais as o', 'o.id', '=', 'pof.operacao_fiscal_id')
            ->leftJoin('nf_impostos as ni', function ($j) use ($empresaId, $grupoFiscalId) {
                $j->on('ni.operacao_fiscal_id', '=', 'pof.operacao_fiscal_id')
                    ->where('ni.empresa_id', '=', $empresaId)
                    ->where(fn ($q) => $q
                        ->where('ni.grupo_fiscal_id', '=', $grupoFiscalId)
                        ->orWhereNull('ni.grupo_fiscal_id'));
            })
            ->where('pof.produto_id', $produtoId)
            ->orderByRaw('ni.id IS NULL')                 // com regra primeiro
            ->orderByRaw('ni.grupo_fiscal_id IS NULL')    // específica antes da coringa
            ->orderBy('o.cfop')
            ->value('pof.operacao_fiscal_id');
    }

    private function grupoFiscalDoProduto(mixed $produto): ?int
    {
        $grupo = $produto->grupo_fiscal_id ?? null;

        return $grupo === null ? null : (int) $grupo;
    }

    /**
     * CFOP da operação fiscal, ajustado ao destino: o cadastro guarda o CFOP
     * interno (5xxx) e a venda interestadual usa a família 6xxx — a mesma
     * conversão que o legado faz na emissão.
     */
    private function cfopDe(int $operacaoId, int $grupoId, string $ufEmitente, string $ufDestino): string
    {
        $cfop = DB::table('operacoes_fiscais')
            ->where('id', $operacaoId)
            ->where('grupo_id', $grupoId)
            ->where('ativo', true)
            ->value('cfop');
        if (! is_string($cfop) || ! preg_match('/^[1-7][0-9]{3}$/', $cfop)) {
            throw ValidationException::withMessages([
                'cfop' => "Operação fiscal #{$operacaoId} sem CFOP válido.",
            ]);
        }

        if ($ufEmitente !== '' && $ufDestino !== '' && $ufEmitente !== $ufDestino
            && str_starts_with($cfop, '5')) {
            $cfop = '6'.substr($cfop, 1);
        }

        return $cfop;
    }

    private function serie(int $empresaId, ModeloDocumento $modelo): int
    {
        $config = ConfigFiscal::withoutTenant()->where('empresa_id', $empresaId)->first();
        if (! $config) {
            throw ValidationException::withMessages([
                'config_fiscal' => 'Configuração fiscal não cadastrada para a empresa.',
            ]);
        }

        $serie = $modelo === ModeloDocumento::NFCE ? $config->serie_nfce : $config->serie_nfe;
        if ((int) $serie <= 0) {
            throw ValidationException::withMessages([
                'serie' => 'Série fiscal inválida para o modelo solicitado.',
            ]);
        }

        return (int) $serie;
    }
}
