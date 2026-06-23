<?php

namespace App\Domain\Fiscal;

use App\Models\Empresa;
use App\Models\Fiscal\NotaFiscal;
use Illuminate\Support\Carbon;

/**
 * SpedContribuicoesService (C7d) — gera a EFD-Contribuições (PIS/COFINS) do
 * período. Porta o núcleo dos registros legislados, no mesmo estilo "|REG|...|":
 *
 *   Bloco 0: 0000 (abertura) · 0001 · 0990
 *   Bloco A/C: C001 · C100 (doc) · C170 (itens com CST/BC/alíquota/valor PIS e COFINS) · C990
 *   Bloco M (apuração): M001 · M200 (consolidação PIS) · M600 (consolidação COFINS) · M990
 *   Bloco 9: 9001 · 9900 · 9990 · 9999
 *
 * Geração de texto pura (sem rede) → testável no CI. A validação final é no PVA
 * da Receita (gate externo). Apuração = soma dos valores de PIS/COFINS das notas
 * autorizadas (regime cumulativo simplificado; o crédito por entrada entra por
 * extensão quando houver NF-e de compra escriturada).
 */
class SpedContribuicoesService
{
    /** @var list<string> */
    private array $linhas = [];

    /** @var array<string,int> */
    private array $contagem = [];

    public function gerar(Empresa $empresa, string $inicio, string $fim): string
    {
        $this->linhas = [];
        $this->contagem = [];

        $dtIni = Carbon::parse($inicio);
        $dtFim = Carbon::parse($fim);

        $notas = NotaFiscal::query()
            ->with('itens')
            ->where('empresa_id', $empresa->id)
            ->where('situacao', SituacaoNota::AUTORIZADA->value)
            ->whereBetween('emitida_em', [$dtIni->copy()->startOfDay(), $dtFim->copy()->endOfDay()])
            ->orderBy('numero')
            ->get();

        // ── Bloco 0 ──
        $this->reg('0000', [
            '006',                          // COD_VER do leiaute EFD-Contribuições
            '0',                            // TIPO_ESCRIT (0=original)
            '',                             // IND_SIT_ESP
            '',                             // NUM_REC_ANTERIOR
            $dtIni->format('dmY'),
            $dtFim->format('dmY'),
            $empresa->razao_social,
            preg_replace('/\D/', '', (string) $empresa->cnpj),
            $empresa->uf,
            '',                             // COD_MUN
            '',                             // SUFRAMA
            '0',                            // IND_NAT_PJ
            '1',                            // IND_ATIV
        ]);
        $this->reg('0001', ['0']);
        $this->fecharBloco('0990', '0');

        // ── Bloco C (documentos fiscais) ──
        $this->reg('C001', ['0']);
        foreach ($notas as $nota) {
            $this->reg('C100', [
                '1', '1',                                    // IND_OPER (saída), IND_EMIT (própria)
                (string) ($nota->cliente_id ?? ''),
                (string) ($nota->modelo->value ?? $nota->modelo),
                '00',
                (string) $nota->serie,
                (string) $nota->numero,
                (string) $nota->chave,
                $nota->emitida_em?->format('dmY') ?? '',
                $this->num($nota->valor_total),
            ]);
            foreach ($nota->itens as $item) {
                $this->reg('C170', [
                    (string) $item->numero_item,
                    (string) ($item->produto_id ?? ''),
                    $this->num($item->valor_total),
                    $item->cst_pis ?? '01',                  // CST PIS
                    $this->num($item->valor_total),          // BC PIS
                    $this->num($item->aliq_pis),
                    $this->num($item->valor_pis),
                    $item->cst_cofins ?? '01',               // CST COFINS
                    $this->num($item->valor_total),          // BC COFINS
                    $this->num($item->aliq_cofins),
                    $this->num($item->valor_cofins),
                ]);
            }
        }
        $this->fecharBloco('C990', 'C');

        // ── Bloco M (apuração consolidada) ──
        $totalPis = round((float) $notas->sum('valor_pis'), 2);
        $totalCofins = round((float) $notas->sum('valor_cofins'), 2);
        $this->reg('M001', ['0']);
        // M200 — consolidação da contribuição PIS do período.
        $this->reg('M200', [
            $this->num($totalPis),  // VL_TOT_CONT_NC_PER
            '0,00', '0,00', '0,00', // créditos descontados (extensão)
            $this->num($totalPis),  // VL_TOT_CONT_NC_DEV
            '0,00',
            $this->num($totalPis),  // VL_TOT_CONT_PER (a recolher)
        ]);
        // M600 — consolidação da COFINS do período.
        $this->reg('M600', [
            $this->num($totalCofins),
            '0,00', '0,00', '0,00',
            $this->num($totalCofins),
            '0,00',
            $this->num($totalCofins),
        ]);
        $this->fecharBloco('M990', 'M');

        // ── Bloco 9 ──
        $this->bloco9();

        return implode("\r\n", $this->linhas)."\r\n";
    }

    private function reg(string $codigo, array $campos): void
    {
        array_unshift($campos, $codigo);
        $this->linhas[] = '|'.implode('|', array_map(fn ($c) => (string) $c, $campos)).'|';
        $this->contagem[$codigo] = ($this->contagem[$codigo] ?? 0) + 1;
    }

    private function fecharBloco(string $codigoFecha, string $letraBloco): void
    {
        $qtd = 0;
        foreach ($this->contagem as $reg => $n) {
            if (str_starts_with($reg, $letraBloco)) {
                $qtd += $n;
            }
        }
        $this->reg($codigoFecha, [(string) ($qtd + 1)]);
    }

    private function bloco9(): void
    {
        $this->reg('9001', ['0']);
        $snapshot = $this->contagem;
        $qtd9900 = count($snapshot) + 4;
        foreach ($snapshot as $reg => $n) {
            $this->reg('9900', [$reg, (string) $n]);
        }
        $this->reg('9900', ['9900', (string) $qtd9900]);
        $this->reg('9900', ['9001', '1']);
        $this->reg('9900', ['9990', '1']);
        $this->reg('9900', ['9999', '1']);

        $qtdBloco9 = 0;
        foreach ($this->contagem as $reg => $n) {
            if (str_starts_with($reg, '9')) {
                $qtdBloco9 += $n;
            }
        }
        $this->reg('9990', [(string) ($qtdBloco9 + 1)]);
        $this->reg('9999', [(string) (count($this->linhas) + 1)]);
    }

    private function num(mixed $v, int $dec = 2): string
    {
        return number_format((float) $v, $dec, ',', '');
    }
}
