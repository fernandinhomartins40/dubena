<?php

namespace App\Domain\Fiscal;

use App\Models\Empresa;
use App\Models\Fiscal\NotaFiscal;
use Illuminate\Support\Carbon;

/**
 * SpedFiscalService (C7d) — gera o arquivo da EFD ICMS/IPI (SPED Fiscal) do
 * período. PORTA o leiaute dos registros do legado (Sped/Reg*): cada linha é
 * "|REG|campos...|" na ordem oficial da Receita. Cobre o núcleo:
 *
 *   Bloco 0: 0000 (abertura) · 0001 · 0150 (participantes) · 0200 (itens) · 0990
 *   Bloco C: C001 · C100 (NF) · C170 (itens da NF) · C990
 *   Bloco 9: 9001 · 9900 (totais por registro) · 9990 · 9999
 *
 * Geração de texto pura (sem rede) → testável no CI. Layout = legislado: não se
 * inventa ordem de campo. A validação final é no PVA da Receita (gate externo).
 */
class SpedFiscalService
{
    /** @var list<string> */
    private array $linhas = [];

    /** @var array<string,int> contagem por registro (para o bloco 9) */
    private array $contagem = [];

    /**
     * Gera o conteúdo do SPED Fiscal do período (inclusive) para a empresa.
     */
    public function gerar(Empresa $empresa, string $inicio, string $fim): string
    {
        $this->linhas = [];
        $this->contagem = [];

        $dtIni = Carbon::parse($inicio);
        $dtFim = Carbon::parse($fim);

        $notas = NotaFiscal::query()
            ->with(['itens', 'cliente'])
            ->where('empresa_id', $empresa->id)
            ->where('situacao', SituacaoNota::AUTORIZADA->value)
            ->whereBetween('emitida_em', [$dtIni->copy()->startOfDay(), $dtFim->copy()->endOfDay()])
            ->orderBy('numero')
            ->get();

        // ── Bloco 0 ──
        $this->reg('0000', [
            '017', // COD_VER (leiaute) — 017 ~ versão recente
            '0',   // COD_FIN (0=remessa regular)
            $dtIni->format('dmY'),
            $dtFim->format('dmY'),
            $empresa->razao_social,
            preg_replace('/\D/', '', (string) $empresa->cnpj),
            '', // CPF
            $empresa->uf,
            '', // IE
            '', // COD_MUN
            '', // IM
            '', // SUFRAMA
            '0', // IND_PERFIL
            '1', // IND_ATIV (1=outros)
        ]);
        $this->reg('0001', ['0']); // 0=bloco com dados

        // 0150 — participantes (clientes das notas).
        $participantes = $notas->pluck('cliente')->filter()->unique('id');
        foreach ($participantes as $p) {
            $this->reg('0150', [
                (string) $p->id,
                $p->nome,
                '1058', // COD_PAIS Brasil
                $p->cnpj ? preg_replace('/\D/', '', (string) $p->cnpj) : '',
                $p->cpf ? preg_replace('/\D/', '', (string) $p->cpf) : '',
                '', '', '', '', '',
            ]);
        }

        // 0200 — itens (produtos das notas).
        $produtos = $notas->flatMap->itens->pluck('produto')->filter()->unique('id');
        foreach ($produtos as $prod) {
            $this->reg('0200', [
                (string) $prod->id,
                $prod->descricao,
                '', '', // COD_BARRA, COD_ANT_ITEM
                'UN',
                '00', // TIPO_ITEM (00=mercadoria p/ revenda)
                $prod->ncm ?? '',
                '', '', '', '', '',
            ]);
        }
        $this->fecharBloco('0990', '0');

        // ── Bloco C ──
        $this->reg('C001', ['0']);
        // Acumula o analítico (C190) e a apuração (E110) à medida que percorre as notas.
        $analitico = []; // chave CST|CFOP|ALIQ → somatórios
        $totalDebito = 0.0;
        foreach ($notas as $nota) {
            $this->reg('C100', [
                '1', // IND_OPER (1=saída)
                '1', // IND_EMIT (1=própria)
                (string) ($nota->cliente_id ?? ''),
                (string) $nota->modelo->value,
                '00', // COD_SIT (00=regular)
                (string) $nota->serie,
                (string) $nota->numero,
                (string) $nota->chave,
                $nota->emitida_em?->format('dmY') ?? '',
                $nota->emitida_em?->format('dmY') ?? '',
                $this->num($nota->valor_total),
                '0', // IND_PGTO (0=à vista)
                $this->num($nota->valor_desconto),
                '0', $this->num($nota->valor_produtos),
                '9', // IND_FRT (9=sem frete)
                '0', '0', $this->num($nota->valor_icms),
                $this->num($nota->valor_icms_st),
                $this->num($nota->valor_ipi),
            ]);

            foreach ($nota->itens as $item) {
                $this->reg('C170', [
                    (string) $item->numero_item,
                    (string) ($item->produto_id ?? ''),
                    '', // DESCR_COMPL
                    $this->num($item->quantidade, 3),
                    'UN',
                    $this->num($item->valor_total),
                    $this->num($item->desconto),
                    '0', // IND_MOV
                    $item->cst_icms ?? '',
                    $item->cfop ?? '',
                    '', // COD_NAT
                    $this->num($item->bc_icms),
                    $this->num($item->aliq_icms),
                    $this->num($item->valor_icms),
                ]);

                // Acumula no analítico C190 (chave CST+CFOP+alíquota).
                $cst = $item->cst_icms ?? '';
                $cfop = $item->cfop ?? '';
                $aliq = (float) $item->aliq_icms;
                $k = $cst.'|'.$cfop.'|'.number_format($aliq, 2, '.', '');
                $analitico[$k] ??= ['cst' => $cst, 'cfop' => $cfop, 'aliq' => $aliq, 'vlr' => 0.0, 'bc' => 0.0, 'icms' => 0.0];
                $analitico[$k]['vlr'] += (float) $item->valor_total;
                $analitico[$k]['bc'] += (float) $item->bc_icms;
                $analitico[$k]['icms'] += (float) $item->valor_icms;
                $totalDebito += (float) $item->valor_icms;
            }

            // C190 — registro analítico do documento (por CST/CFOP/alíquota).
            foreach ($analitico as $a) {
                $this->reg('C190', [
                    $a['cst'], $a['cfop'], $this->num($a['aliq']),
                    $this->num($a['vlr']), $this->num($a['bc']), $this->num($a['icms']),
                    '0,00', '0,00', '0,00', '0,00', '',
                ]);
            }
            $analitico = []; // reinicia por documento
        }
        $this->fecharBloco('C990', 'C');

        // ── Bloco E (apuração do ICMS) ──
        $this->reg('E001', ['0']);
        $this->reg('E100', [$dtIni->format('dmY'), $dtFim->format('dmY')]);
        $this->reg('E110', [
            $this->num($totalDebito),  // VL_TOT_DEBITOS
            '0,00',                    // VL_AJ_DEBITOS
            '0,00',                    // VL_TOT_AJ_DEBITOS
            '0,00',                    // VL_ESTORNOS_CRED
            '0,00',                    // VL_TOT_CREDITOS
            '0,00', '0,00', '0,00',    // ajustes/estornos crédito
            '0,00',                    // VL_SLD_CREDOR_ANT
            $this->num($totalDebito),  // VL_SLD_APURADO (devedor)
            '0,00',                    // VL_TOT_DED
            $this->num($totalDebito),  // VL_ICMS_RECOLHER
            '0,00',                    // VL_SLD_CREDOR_TRANSPORTAR
            '0,00',                    // DEB_ESP
        ]);
        $this->fecharBloco('E990', 'E');

        // ── Bloco H (inventário) — saldo por produto, derivado de estoquesaldos ──
        // Quantidade = Σ saldos dos setores da empresa; custo = custo médio do saldo.
        $saldos = \App\Models\Estoque\EstoqueSaldo::query()
            ->where('empresa_id', $empresa->id)
            ->selectRaw('produto_id, sum(quantidade) as qtd, avg(custo_medio) as custo')
            ->groupBy('produto_id')
            ->get()
            ->keyBy('produto_id');

        $valorInventario = $saldos->sum(fn ($s) => (float) $s->qtd * (float) $s->custo);

        $this->reg('H001', ['0']);
        $this->reg('H005', [$dtFim->format('dmY'), $this->num($valorInventario), '01']); // MOT_INV 01=final período
        foreach ($produtos as $prod) {
            $s = $saldos->get($prod->id);
            $qtd = (float) ($s->qtd ?? 0);
            $custo = (float) ($s->custo ?? $prod->custo_medio ?? 0);
            $this->reg('H010', [
                (string) $prod->id, 'UN',
                $this->num($qtd, 3),
                $this->num($custo),
                $this->num($qtd * $custo),
                '0', '', '', '',
            ]);
        }
        $this->fecharBloco('H990', 'H');

        // ── Bloco 9 (encerramento) ──
        $this->bloco9();

        return implode("\r\n", $this->linhas)."\r\n";
    }

    /** Adiciona um registro "|REG|campos...|" e conta para o bloco 9. */
    private function reg(string $codigo, array $campos): void
    {
        array_unshift($campos, $codigo);
        $this->linhas[] = '|'.implode('|', array_map(fn ($c) => (string) $c, $campos)).'|';
        $this->contagem[$codigo] = ($this->contagem[$codigo] ?? 0) + 1;
    }

    /** Fecha um bloco (registro X990 com a quantidade de linhas do bloco). */
    private function fecharBloco(string $codigoFecha, string $letraBloco): void
    {
        // Conta as linhas do bloco já emitidas + o próprio X990.
        $qtd = 0;
        foreach ($this->contagem as $reg => $n) {
            if (str_starts_with($reg, $letraBloco)) {
                $qtd += $n;
            }
        }
        $qtd += 1; // o próprio registro de fechamento
        $this->reg($codigoFecha, [(string) $qtd]);
    }

    /** Bloco 9: 9001, 9900 (totais por registro), 9990, 9999. */
    private function bloco9(): void
    {
        $this->reg('9001', ['0']);

        // 9900 para cada registro existente (inclui os 9xxx que vamos contar).
        $snapshot = $this->contagem;
        // Prevê os 9900 (um por registro distinto + ele mesmo) + 9990 + 9999.
        $regsDistintos = array_keys($snapshot);
        $qtd9900 = count($regsDistintos) + 3; // +9900(de 9900) +9990 +9999 contam abaixo

        foreach ($snapshot as $reg => $n) {
            $this->reg('9900', [$reg, (string) $n]);
        }
        // 9900 para os próprios registros do bloco 9.
        $this->reg('9900', ['9900', (string) ($qtd9900)]);
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

    /** Número no formato SPED (vírgula decimal, sem milhar). */
    private function num(mixed $v, int $dec = 2): string
    {
        return number_format((float) $v, $dec, ',', '');
    }
}
