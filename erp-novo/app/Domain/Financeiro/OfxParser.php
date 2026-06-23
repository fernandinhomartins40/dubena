<?php

namespace App\Domain\Financeiro;

use Illuminate\Support\Carbon;

/**
 * OfxParser (C8) — parser focado de extrato OFX (bancário).
 *
 * OFX é SGML-ish; para conciliação interessam os blocos <STMTTRN> (transações):
 * tipo (TRNTYPE), data (DTPOSTED), valor (TRNAMT), id (FITID) e memo. Parser
 * leve (sem dependência externa), tolerante a OFX 1.x (SGML) e 2.x (XML).
 */
class OfxParser
{
    /**
     * Extrai as transações do conteúdo OFX.
     *
     * @return list<array{fitid:string, tipo:string, data:?string, valor:float, descricao:string}>
     */
    public function transacoes(string $ofx): array
    {
        $transacoes = [];

        if (preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/si', $ofx, $blocos)) {
            foreach ($blocos[1] as $bloco) {
                $transacoes[] = [
                    'fitid' => $this->campo($bloco, 'FITID'),
                    'tipo' => $this->campo($bloco, 'TRNTYPE'),
                    'data' => $this->data($this->campo($bloco, 'DTPOSTED')),
                    'valor' => (float) str_replace(',', '.', $this->campo($bloco, 'TRNAMT')),
                    'descricao' => trim($this->campo($bloco, 'MEMO') ?: $this->campo($bloco, 'NAME')),
                ];
            }
        }

        return $transacoes;
    }

    /** Lê um campo OFX, aceitando o estilo SGML (<TAG>valor) e XML (<TAG>valor</TAG>). */
    private function campo(string $bloco, string $tag): string
    {
        // XML: <TAG>valor</TAG>
        if (preg_match('/<'.$tag.'>(.*?)<\/'.$tag.'>/si', $bloco, $m)) {
            return trim($m[1]);
        }
        // SGML: <TAG>valor (até o fim da linha ou próxima tag).
        if (preg_match('/<'.$tag.'>([^<\r\n]*)/si', $bloco, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    /** DTPOSTED vem como YYYYMMDD[HHMMSS][.fff][TZ]; pega a data. */
    private function data(string $raw): ?string
    {
        if (strlen($raw) < 8) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Ymd', substr($raw, 0, 8))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
