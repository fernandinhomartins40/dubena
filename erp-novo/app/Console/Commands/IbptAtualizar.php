<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * F09 — Atualiza a tabela IBPT (Lei 12.741) a partir do CSV regional mensal.
 *
 * Fonte (gate): --arquivo=<path> OU IBPT_CSV_URL (download). Sem nenhum dos dois,
 * é NO-OP informativo (não quebra o agendamento em ambientes sem a fonte).
 * Espelha o `ibpt:update`/`UpdateTabelaIbpt` do legado. O CSV IBPT é ';'-delimitado:
 *   ncm;ex;tipo;descricao;nacionalfederal;importadosfederal;estadual;municipal;
 *   vigenciainicio;vigenciafim;chave;versao;fonte
 */
class IbptAtualizar extends Command
{
    protected $signature = 'ibpt:atualizar {--arquivo= : Caminho de um CSV local} {--uf= : UF da tabela}';

    protected $description = 'Importa/atualiza a tabela IBPT (carga tributária por NCM).';

    public function handle(): int
    {
        $uf = strtoupper((string) $this->option('uf')) ?: null;
        $conteudo = $this->obterCsv();

        if ($conteudo === null) {
            $this->warn('IBPT: nenhuma fonte configurada (--arquivo ou IBPT_CSV_URL). Nada a fazer.');

            return self::SUCCESS;
        }

        $linhas = preg_split('/\r\n|\r|\n/', trim($conteudo)) ?: [];
        $importadas = 0;
        $versao = null;

        foreach ($linhas as $i => $linha) {
            if (trim($linha) === '') {
                continue;
            }
            $col = str_getcsv($linha, ';');
            // Pula cabeçalho (primeira coluna não-numérica).
            if ($i === 0 && ! ctype_digit(preg_replace('/\D/', '', $col[0] ?? ''))) {
                continue;
            }
            if (count($col) < 8) {
                continue;
            }

            $ncm = preg_replace('/\D/', '', $col[0] ?? '');
            if ($ncm === '') {
                continue;
            }
            $versao = $col[11] ?? $versao;

            DB::table('ibpt_aliquotas')->updateOrInsert(
                ['ncm' => $ncm, 'uf' => $uf, 'ex' => $col[1] ?: null],
                [
                    'nacional' => $this->dec($col[4] ?? 0),
                    'importado' => $this->dec($col[5] ?? 0),
                    'estadual' => $this->dec($col[6] ?? 0),
                    'municipal' => $this->dec($col[7] ?? 0),
                    'versao' => $col[11] ?? null,
                    'vigencia_inicio' => $this->data($col[8] ?? null),
                    'vigencia_fim' => $this->data($col[9] ?? null),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
            $importadas++;
        }

        $this->info("IBPT atualizado: {$importadas} NCM(s)".($versao ? " (versão {$versao})" : '').'.');

        return self::SUCCESS;
    }

    private function obterCsv(): ?string
    {
        $arquivo = $this->option('arquivo');
        if ($arquivo && is_file($arquivo)) {
            return (string) file_get_contents($arquivo);
        }

        $url = env('IBPT_CSV_URL');
        if ($url) {
            $resp = Http::timeout(30)->get($url);

            return $resp->successful() ? $resp->body() : null;
        }

        return null;
    }

    private function dec(string|float|null $v): float
    {
        return (float) str_replace(',', '.', (string) $v);
    }

    private function data(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        // Aceita dd/mm/aaaa ou aaaa-mm-dd.
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $v, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return preg_match('#^\d{4}-\d{2}-\d{2}$#', $v) ? $v : null;
    }
}
