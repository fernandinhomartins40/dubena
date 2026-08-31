<?php

namespace App\Domain\Fiscal\Drivers;

use App\Domain\Fiscal\Contracts\SefazDriver;
use App\Models\Fiscal\NotaFiscal;

/**
 * Driver SEFAZ para DEV/HOMOLOG/CI (sem certificado/webservice). Autoriza
 * deterministicamente (chave de 44 dígitos + protocolo fictícios) para que o fluxo
 * montar→calcular→emitir→cancelar seja testável ponta-a-ponta. NÃO usar em
 * produção: lá entra o driver real (porta NFePHP + certificado A1 do tenant).
 */
class FakeSefazDriver implements SefazDriver
{
    /**
     * F5-05 — defesa em profundidade: o fake nao existe em producao.
     *
     * O container ja recusa a configuracao que ativaria um fake em producao
     * (`exigirDriverReal`), e essa e a protecao principal. Esta guarda cobre o
     * caminho que ela nao alcanca: instanciar a classe diretamente, sem passar
     * pela resolucao por interface.
     *
     * Duas travas para o mesmo risco porque o custo de errar aqui e documento fiscal sintetico
     * entrando na operacao real — e o modo de falhar e silencioso: o sistema
     * responde "sucesso" e nenhuma nota sintetica foi autorizada de verdade.
     */
    public function __construct()
    {
        if (app()->isProduction()) {
            throw new \RuntimeException(
                static::class.' e proibido em producao — nenhuma nota sintetica foi autorizada.',
            );
        }
    }

    public function transmitir(NotaFiscal $nota): array
    {
        // Chave de acesso fake determinística (44 dígitos) a partir dos dados da nota.
        $base = sprintf('%02d%04d%014d%02d%03d%09d',
            35, (int) $nota->emitida_em?->format('ym') ?: 0, 0, (int) $nota->modelo->value, $nota->serie, $nota->numero);
        $chave = str_pad(preg_replace('/\D/', '', $base.$nota->id), 44, '0');
        $chave = substr($chave, 0, 44);

        return [
            'autorizada' => true,
            'chave' => $chave,
            'protocolo' => '135'.str_pad((string) $nota->id, 12, '0', STR_PAD_LEFT),
            'motivo' => 'Autorizado o uso da NF-e (ambiente de teste).',
        ];
    }

    public function cancelar(NotaFiscal $nota, string $justificativa): array
    {
        return [
            'cancelada' => true,
            'protocolo' => '101'.str_pad((string) $nota->id, 12, '0', STR_PAD_LEFT),
            'motivo' => 'Cancelamento homologado (ambiente de teste).',
        ];
    }

    public function inutilizar(int $empresaId, int $modelo, int $serie, int $numeroInicial, int $numeroFinal, string $justificativa): array
    {
        return [
            'inutilizada' => true,
            'protocolo' => '102'.str_pad((string) ($numeroInicial + $numeroFinal), 12, '0', STR_PAD_LEFT),
            'motivo' => 'Inutilização homologada (ambiente de teste).',
        ];
    }

    public function cartaCorrecao(NotaFiscal $nota, string $correcao, int $sequencia): array
    {
        return [
            'registrada' => true,
            'protocolo' => '135'.str_pad((string) $nota->id.$sequencia, 12, '0', STR_PAD_LEFT),
            'sequencia' => $sequencia,
            'motivo' => 'Carta de correção registrada (ambiente de teste).',
        ];
    }
}
