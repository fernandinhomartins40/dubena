<?php

namespace App\Domain\Cobranca\Drivers;

use App\Domain\Cobranca\Contracts\BoletoDriver;
use App\Models\Cobranca\Boleto;

/**
 * Driver de boleto para DEV/HOMOLOG/CI (sem banco real). Gera nosso número e uma
 * linha digitável determinística (não-bancável) para que o fluxo
 * geração→remessa→retorno seja testável ponta-a-ponta. NÃO usar em produção:
 * lá entra o driver real por banco (porta eduardokum/laravel-boleto).
 */
class FakeBoletoDriver implements BoletoDriver
{
    /**
     * F5-05 — defesa em profundidade: o fake nao existe em producao.
     *
     * O container ja recusa a configuracao que ativaria um fake em producao
     * (`exigirDriverReal`), e essa e a protecao principal. Esta guarda cobre o
     * caminho que ela nao alcanca: instanciar a classe diretamente, sem passar
     * pela resolucao por interface.
     *
     * Duas travas para o mesmo risco porque o custo de errar aqui e boleto sintetico
     * entrando na operacao real — e o modo de falhar e silencioso: o sistema
     * responde "sucesso" e nenhum boleto sintetico foi gerado de verdade.
     */
    public function __construct()
    {
        if (app()->isProduction()) {
            throw new \RuntimeException(
                static::class.' e proibido em producao — nenhum boleto sintetico foi gerado.',
            );
        }
    }

    public function bancoCodigo(): int
    {
        return 0; // fictício
    }

    public function gerar(Boleto $boleto): array
    {
        $nosso = str_pad((string) $boleto->id, 11, '0', STR_PAD_LEFT);
        $valor = str_pad((string) (int) round((float) $boleto->valor * 100), 10, '0', STR_PAD_LEFT);

        return [
            'nosso_numero' => $nosso,
            'linha_digitavel' => "00000.00000 00000.000000 00000.{$nosso} 0 {$valor}",
            'codigo_barras' => '0000'.$valor.$nosso.'000000000',
            'carteira' => '00',
        ];
    }

    public function linhaRemessa(Boleto $boleto): string
    {
        // Linha CNAB simplificada (campos fixos) — só para o teste de geração.
        return sprintf(
            '1%011s%010d%08s',
            $boleto->nosso_numero ?? '',
            (int) round((float) $boleto->valor * 100),
            $boleto->vencimento?->format('dmy') ?? '',
        );
    }

    public function interpretarRetorno(string $linha): array
    {
        // Formato fake: a linha contém "...CODIGO|VALOR" (ex.: "00012 06|150.00").
        // Pega o último token "NN|valor"; o código é os 2 últimos dígitos antes do '|'.
        $token = trim((string) strrchr(' '.trim($linha), ' '));
        [$cod, $valor] = array_pad(explode('|', $token), 2, null);
        $codigo = substr(preg_replace('/\D/', '', (string) $cod), -2) ?: (string) $cod;
        $map = [
            '02' => ['Entrada confirmada (registrado)', 'REGISTRADO'],
            '06' => ['Liquidação', 'LIQUIDADO'],
            '09' => ['Baixa', 'BAIXADO'],
            '03' => ['Rejeição', 'REJEITADO'],
        ];
        [$descricao, $situacao] = $map[$codigo] ?? ['Ocorrência '.$codigo, 'REGISTRADO'];

        return [
            'codigo' => (string) $codigo,
            'descricao' => $descricao,
            'valor' => $valor !== null ? (float) $valor : null,
            'situacao' => $situacao,
        ];
    }

    public function boletoIdRetorno(string $linha): ?int
    {
        // Harness: os 11 primeiros caracteres sao o nosso numero baseado no id.
        $id = substr($linha, 0, 11);

        return ctype_digit($id) && (int) $id > 0 ? (int) $id : null;
    }
}
