<?php

namespace App\Domain\Fiscal;

use Illuminate\Support\Facades\DB;

/**
 * IbptService (C7a) — cálculo do tributo aproximado (Lei 12.741/2012, "De olho no
 * imposto"): mostra ao consumidor a carga tributária federal/estadual/municipal
 * estimada sobre o valor do item, por NCM.
 *
 * A tabela oficial do IBPT é um CSV mensal regional (download = gate externo);
 * carregamos da tabela `ibpt_aliquotas` quando populada, com fallback a uma
 * alíquota-padrão conservadora. O cálculo (valor × alíquota) é puro e testável.
 */
class IbptService
{
    /** Alíquotas-padrão (%) quando a NCM não está na tabela carregada. */
    private const PADRAO = ['nacional' => 12.0, 'importado' => 18.0, 'estadual' => 18.0, 'municipal' => 0.0];

    /**
     * Retorna a carga tributária aproximada de um item.
     *
     * @return array{
     *   ncm:string, valor:float, origem:string,
     *   aliq_nacional:float, aliq_estadual:float, aliq_municipal:float,
     *   valor_tributos:float, fonte:string
     * }
     */
    public function calcular(string $ncm, float $valor, string $origem = 'nacional'): array
    {
        $ncm = preg_replace('/\D/', '', $ncm);
        $linha = $this->aliquotas($ncm);

        $aliqFederal = $origem === 'importado' ? (float) $linha['importado'] : (float) $linha['nacional'];
        $aliqEstadual = (float) $linha['estadual'];
        $aliqMunicipal = (float) $linha['municipal'];

        $tributos = round($valor * ($aliqFederal + $aliqEstadual + $aliqMunicipal) / 100, 2);

        return [
            'ncm' => $ncm,
            'valor' => round($valor, 2),
            'origem' => $origem,
            'aliq_nacional' => $aliqFederal,
            'aliq_estadual' => $aliqEstadual,
            'aliq_municipal' => $aliqMunicipal,
            'valor_tributos' => $tributos,
            'fonte' => $linha['_fonte'],
        ];
    }

    /**
     * Busca alíquotas da tabela IBPT carregada (se existir e tiver a NCM); senão
     * usa o padrão. A tabela é opcional — não quebra sem ela.
     *
     * @return array<string,mixed>
     */
    private function aliquotas(string $ncm): array
    {
        if (DB::getSchemaBuilder()->hasTable('ibpt_aliquotas')) {
            $reg = DB::table('ibpt_aliquotas')->where('ncm', $ncm)->first();
            if ($reg) {
                return [
                    'nacional' => $reg->nacional,
                    'importado' => $reg->importado,
                    'estadual' => $reg->estadual,
                    'municipal' => $reg->municipal ?? 0,
                    '_fonte' => 'IBPT',
                ];
            }
        }

        return array_merge(self::PADRAO, ['_fonte' => 'padrão']);
    }
}
