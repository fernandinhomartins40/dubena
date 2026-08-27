<?php

namespace App\Domain\Cobranca;

use App\Domain\Shared\PdfService;
use App\Models\Cobranca\Boleto;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Impressão do boleto (T4.6 — o bloqueante da família de saídas impressas).
 *
 * A auditoria é direta: *"o boleto em si não é impresso; **sem o PDF o título
 * não pode ser entregue ao cliente**"*. O CNAB do novo já estava completo
 * (remessa, retorno, `app/Domain/Cobranca/Cnab/`) — o título nascia, ia ao banco
 * e voltava, mas nunca virava papel. No modelo disk-gás isso significa cobrança
 * que não chega.
 *
 * O layout segue a ficha de compensação padrão: recibo do sacado destacável em
 * cima, ficha de compensação embaixo, linha digitável em destaque e código de
 * barras I2of5.
 *
 * ⚠️ **Verificação humana obrigatória** antes do go-live: imprimir um boleto e
 * conferir que o leitor do caixa lê o código de barras e que a linha digitável
 * confere. Um boleto com barcode errado é pior que boleto nenhum — o cliente
 * tenta pagar, não consegue, e a culpa recai sobre a revenda.
 */
class BoletoPdfService
{
    public function __construct(
        private PdfService $pdf,
        private CodigoBarrasI25 $barras,
    ) {
    }

    /**
     * Gera o PDF de um boleto.
     *
     * @return string bytes do PDF
     */
    public function gerar(Boleto $boleto): string
    {
        // Busca direta pelos ids: o model `Boleto` só declara a relação
        // `parcela`, e inventar relações aqui seria mudança de escopo.
        $empresa = $boleto->empresa_id !== null
            ? \App\Models\Empresa::query()->find($boleto->empresa_id)
            : null;

        $cliente = $boleto->cliente_id !== null
            ? \App\Models\Cliente\Cliente::withoutTenant()
                ->whereKey($boleto->cliente_id)
                ->where('empresa_id', $boleto->empresa_id)
                ->first()
            : null;

        $valor = 'R$ '.number_format((float) $boleto->valor, 2, ',', '.');
        $vencimento = $boleto->vencimento
            ? \Illuminate\Support\Carbon::parse($boleto->vencimento)->format('d/m/Y')
            : '—';

        $linhaDigitavel = (string) ($boleto->linha_digitavel ?? '');
        $codigoBarras = preg_replace('/\D/', '', (string) ($boleto->codigo_barras ?? '')) ?? '';

        // Um boleto sem código de barras não é pagável. Melhor falhar visível do
        // que entregar ao cliente um papel que o caixa vai recusar.
        $barrasHtml = $codigoBarras !== ''
            ? $this->barras->html($codigoBarras)
            : '<div style="color:#b91c1c;font-size:10px">SEM CÓDIGO DE BARRAS — boleto não pagável</div>';

        $beneficiario = (string) ($empresa->razao_social ?? '');
        $sacado = (string) ($cliente->nome ?? '');
        $documento = (string) ($boleto->nosso_numero ?? $boleto->id);

        $html = $this->recibo($beneficiario, $sacado, $documento, $vencimento, $valor)
            .'<div style="border-top:1px dashed #64748b;margin:10px 0 8px;font-size:8px;color:#64748b">'
            .'corte aqui</div>'
            .$this->fichaCompensacao(
                $beneficiario, $sacado, $documento, $vencimento, $valor,
                $linhaDigitavel, $barrasHtml, (string) ($empresa->cnpj ?? ''),
            );

        return Pdf::loadHTML($this->envelope($html))->setPaper('a4', 'portrait')->output();
    }

    /** Recibo do sacado — a via que fica com o cliente. */
    private function recibo(
        string $beneficiario, string $sacado, string $documento,
        string $vencimento, string $valor,
    ): string {
        return '<table style="width:100%;border:1px solid #1e293b;border-collapse:collapse">'
            .'<tr><td style="padding:6px;font-size:9px;color:#64748b">RECIBO DO SACADO</td>'
            .'<td style="padding:6px;text-align:right;font-size:9px;color:#64748b">Documento nº '.e($documento).'</td></tr>'
            .'<tr><td style="padding:6px;border-top:1px solid #cbd5e1" colspan="2">'
            .'<strong>Beneficiário:</strong> '.e($beneficiario).'</td></tr>'
            .'<tr><td style="padding:6px;border-top:1px solid #cbd5e1" colspan="2">'
            .'<strong>Pagador:</strong> '.e($sacado).'</td></tr>'
            .'<tr><td style="padding:6px;border-top:1px solid #cbd5e1"><strong>Vencimento:</strong> '.e($vencimento).'</td>'
            .'<td style="padding:6px;border-top:1px solid #cbd5e1;text-align:right">'
            .'<strong>Valor: '.e($valor).'</strong></td></tr>'
            .'</table>';
    }

    /** Ficha de compensação — a via que vai ao banco. */
    private function fichaCompensacao(
        string $beneficiario, string $sacado, string $documento, string $vencimento,
        string $valor, string $linhaDigitavel, string $barrasHtml, string $cnpj,
    ): string {
        return '<table style="width:100%;border:1px solid #1e293b;border-collapse:collapse">'
            // A linha digitável é o que o cliente digita no app do banco: fonte
            // monoespaçada e grande, porque errar um dígito invalida o pagamento.
            .'<tr><td colspan="2" style="padding:8px;border-bottom:2px solid #1e293b;'
            .'font-family:DejaVu Sans Mono,monospace;font-size:13px;letter-spacing:.5px;text-align:right">'
            .e($linhaDigitavel).'</td></tr>'
            .'<tr><td style="padding:6px;font-size:10px" colspan="2">'
            .'<strong>Beneficiário:</strong> '.e($beneficiario)
            .($cnpj !== '' ? ' &nbsp; <span style="color:#64748b">CNPJ '.e($cnpj).'</span>' : '')
            .'</td></tr>'
            .'<tr><td style="padding:6px;border-top:1px solid #cbd5e1;font-size:10px">'
            .'<span style="color:#64748b;font-size:8px">NOSSO NÚMERO</span><br>'.e($documento).'</td>'
            .'<td style="padding:6px;border-top:1px solid #cbd5e1;font-size:10px;text-align:right">'
            .'<span style="color:#64748b;font-size:8px">VENCIMENTO</span><br><strong>'.e($vencimento).'</strong></td></tr>'
            .'<tr><td style="padding:6px;border-top:1px solid #cbd5e1;font-size:10px">'
            .'<span style="color:#64748b;font-size:8px">PAGADOR</span><br>'.e($sacado).'</td>'
            .'<td style="padding:6px;border-top:1px solid #cbd5e1;text-align:right">'
            .'<span style="color:#64748b;font-size:8px">VALOR DO DOCUMENTO</span><br>'
            .'<strong style="font-size:14px">'.e($valor).'</strong></td></tr>'
            .'<tr><td colspan="2" style="padding:10px 6px;border-top:2px solid #1e293b">'
            .$barrasHtml.'</td></tr>'
            .'</table>';
    }

    private function envelope(string $corpo): string
    {
        return '<html><head><meta charset="utf-8"><style>'
            .'@page{margin:12mm}'
            // DejaVu Sans: única fonte com acentuação latina embarcada no dompdf.
            // Sem ela, "Beneficiário" sai "Benefici?rio" no papel do cliente.
            .'body{font-family:"DejaVu Sans",sans-serif;font-size:11px;color:#1e293b}'
            .'</style></head><body>'.$corpo.'</body></html>';
    }
}
