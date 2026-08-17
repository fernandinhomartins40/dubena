<?php

namespace App\Domain\Satelite;

use App\Domain\Shared\PdfService;
use App\Models\Empresa;
use App\Models\Financeiro\Financeiro;
use App\Models\Satelite\ValeGas;

/**
 * Impressão do vale-gás e da duplicata (item 19 da triagem).
 *
 * **Por que é PRÉ-GO-LIVE.** O vale-gás **é** um documento físico: o cliente
 * paga, recebe o papel, e depois troca o papel por um botijão. Sem impressão o
 * produto simplesmente não existe — não há nada para entregar a quem comprou.
 * A auditoria (§19) registra que venda, baixa e consulta foram migradas, mas a
 * "materialização impressa" não.
 *
 * **Dois documentos distintos, um serviço.**
 *
 * - **Vale** (`vale()`): o cupom que vai para a mão do cliente. Meia página,
 *   porque é o que a impressora do balcão dá e porque uma folha A4 por cupom é
 *   desperdício que o operador nota todo dia.
 * - **Duplicata** (`duplicata()`): quando o vale é vendido a prazo, o título
 *   gerado tem parcelas — a duplicata é a via de cobrança de cada uma. É o
 *   `pedidoDuplicata` do legado.
 *
 * **O que NÃO faz.** Não altera situação, não baixa financeiro, não marca nada
 * como impresso. Imprimir é leitura. O `confirmaImpressao` do legado existia
 * porque lá a impressão disparava a baixa; aqui a baixa tem caminho próprio
 * (`ValeGasService::mudarSituacao`), e acoplar as duas faria uma reimpressão
 * mudar o estado financeiro do vale.
 */
class ValeGasPdfService
{
    public function __construct(private PdfService $pdf)
    {
    }

    /**
     * O cupom que o cliente leva.
     *
     * @throws \DomainException se o vale não estiver em estado que justifique impressão
     */
    public function vale(ValeGas $vale): string
    {
        $this->exigirImprimivel($vale);

        // Vale já resgatado não vira cupom de novo: reimprimi-lo coloca em
        // circulação um papel que aparenta dar direito a um botijão já
        // entregue. A duplicata dele, essa sim, continua imprimível — a
        // dívida sobrevive ao resgate.
        if ($vale->situacao === SituacaoValeGas::UTILIZADO) {
            throw new \DomainException(
                'Vale já utilizado não pode ser reimpresso — o cupom daria direito a uma segunda troca.'
            );
        }

        $vale->loadMissing('cliente');
        $empresa = Empresa::query()->find($vale->empresa_id);

        $corpo = $this->pdf->campos([
            'Código do vale' => (string) $vale->codigo,
            'Cliente' => (string) ($vale->cliente->nome ?? 'Consumidor'),
            'Valor' => 'R$ '.number_format((float) $vale->valor, 2, ',', '.'),
            'Validade' => $vale->validade?->format('d/m/Y') ?? 'Sem validade',
            'Situação' => $vale->situacao?->value ?? '',
        ]);

        // O código repetido em destaque não é decoração: é o que o atendente lê
        // no ato da troca, muitas vezes de um papel amassado no bolso.
        $codigo = e((string) $vale->codigo);
        $destaque = <<<HTML
        <div style="text-align:center;border:2px solid #1e293b;padding:8px;margin:8px 0">
          <div style="font-size:8px;text-transform:uppercase;color:#475569">Apresente este código na troca</div>
          <div style="font-size:22px;font-weight:bold;letter-spacing:3px">{$codigo}</div>
        </div>
        HTML;

        $aviso = $vale->situacao === SituacaoValeGas::EMITIDO
            ? '<div style="text-align:center;font-size:9px;border:1px solid #1e293b;padding:3px;margin-bottom:6px">'
                .'AGUARDANDO PAGAMENTO — válido para troca somente após a quitação</div>'
            : '';

        return $this->pdf->meiaPagina('Vale-Gás', $aviso.$destaque.$corpo.$this->pdf->assinatura('Recebi o vale'), [
            'empresa' => (string) ($empresa->razao_social ?? ''),
            'cnpj' => (string) ($empresa->cnpj ?? ''),
            'endereco' => $this->enderecoDa($empresa),
            'rodape' => 'Este vale dá direito à troca do produto descrito acima, nas condições vigentes na revenda. '
                .'Não é válido como recibo de pagamento.',
        ]);
    }

    /**
     * A duplicata do vale vendido a prazo — uma via por parcela, na mesma folha.
     *
     * @throws \DomainException se o vale não tiver título financeiro
     */
    public function duplicata(ValeGas $vale): string
    {
        $this->exigirImprimivel($vale);

        if ($vale->financeiro_id === null) {
            // Vale à vista não gera duplicata: não há o que cobrar depois.
            // Devolver uma duplicata vazia seria pior — o operador entregaria
            // ao cliente um documento de cobrança que não corresponde a dívida.
            throw new \DomainException('Este vale não tem título financeiro — não há duplicata a imprimir.');
        }

        $titulo = Financeiro::query()->with(['parcelas', 'cliente'])->find($vale->financeiro_id);

        if ($titulo === null) {
            throw new \DomainException('Título financeiro do vale não encontrado.');
        }

        $empresa = Empresa::query()->find($vale->empresa_id);
        $parcelas = $titulo->parcelas->sortBy('numero')->values();

        if ($parcelas->isEmpty()) {
            throw new \DomainException('O título deste vale não tem parcelas — não há duplicata a imprimir.');
        }

        $total = $parcelas->count();

        $linhas = $parcelas->map(fn ($p) => [
            "{$p->numero}/{$total}",
            $p->vencimento?->format('d/m/Y') ?? '',
            'R$ '.number_format((float) $p->valor, 2, ',', '.'),
            $p->baixado ? 'PAGA em '.($p->datahora_baixa?->format('d/m/Y') ?? '') : 'EM ABERTO',
        ])->all();

        $corpo = $this->pdf->campos([
            'Sacado' => (string) ($titulo->cliente->nome ?? ''),
            'Documento' => (string) ($titulo->documento ?: "VG-{$vale->codigo}"),
            'Referente a' => "Vale-gás {$vale->codigo}",
            'Valor total' => 'R$ '.number_format((float) $titulo->valor, 2, ',', '.'),
            'Emissão' => $titulo->data_emissao?->format('d/m/Y') ?? '',
        ])
        .$this->pdf->itens(['Parcela', 'Vencimento', 'Valor', 'Situação'], $linhas)
        .$this->pdf->assinatura('Aceite do sacado');

        return $this->pdf->documento("Duplicata — Vale-Gás {$vale->codigo}", $corpo, [
            'empresa' => (string) ($empresa->razao_social ?? ''),
            'cnpj' => (string) ($empresa->cnpj ?? ''),
            'endereco' => $this->enderecoDa($empresa),
            'rodape' => 'Documento de cobrança. As parcelas marcadas como PAGAS não devem ser cobradas novamente.',
        ]);
    }

    /**
     * Vale cancelado ou expirado não vira papel.
     *
     * Um cupom cancelado impresso é indistinguível de um válido na mão do
     * cliente — o atendente do balcão não tem como saber, e a revenda entrega
     * um botijão contra um vale que não vale. Diferente do DANFE (que se
     * reimprime com tarja para arquivo), o vale não tem uso legítimo depois de
     * cancelado: ele é o próprio instrumento de troca.
     */
    private function exigirImprimivel(ValeGas $vale): void
    {
        if (in_array($vale->situacao, [SituacaoValeGas::CANCELADO, SituacaoValeGas::EXPIRADO], true)) {
            throw new \DomainException(
                "Vale {$vale->situacao->value} não pode ser impresso: o papel seria indistinguível de um vale válido."
            );
        }
    }

    private function enderecoDa(?Empresa $empresa): string
    {
        if ($empresa === null) {
            return '';
        }

        return trim(sprintf(
            '%s, %s - %s - %s/%s',
            $empresa->endereco ?? '', $empresa->numero ?? '',
            $empresa->bairro ?? '', $empresa->cidade ?? '', $empresa->uf ?? '',
        ), ' -,/');
    }
}
