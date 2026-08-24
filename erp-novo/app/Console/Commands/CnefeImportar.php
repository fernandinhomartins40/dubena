<?php

namespace App\Console\Commands;

use App\Models\Geografico\ImportacaoCnefe;
use App\Models\Geografico\LogradouroOficial;
use App\Models\Geografico\MunicipioIbge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * cnefe:importar — carrega o CSV gerado por `scripts/cnefe_importar.py`.
 *
 * A divisão de trabalho: o Python baixa e agrega (é onde estão os GB e o
 * parsing de latin-1); o PHP grava e mantém o controle do que já entrou. Assim
 * o download pesado roda fora do container da aplicação, e o comando aqui é
 * rápido e idempotente.
 *
 * Somente leitura por default.
 */
class CnefeImportar extends Command
{
    protected $signature = 'cnefe:importar
        {arquivo : CSV gerado pelo scripts/cnefe_importar.py}
        {--aplicar : Grava no banco. Sem esta flag, somente leitura.}';

    protected $description = 'Carrega logradouros oficiais do CNEFE (IBGE) a partir do CSV.';

    /** Lote do upsert: acima disto o Postgres reclama do número de parâmetros. */
    private const LOTE = 500;

    public function handle(): int
    {
        $arquivo = (string) $this->argument('arquivo');

        if (! is_readable($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");

            return self::FAILURE;
        }

        $handle = fopen($arquivo, 'r');
        $cabecalho = fgetcsv($handle);

        if ($cabecalho === false || ! in_array('nome_busca', $cabecalho, true)) {
            $this->error('CSV sem o cabeçalho esperado. Gere-o com scripts/cnefe_importar.py.');
            fclose($handle);

            return self::FAILURE;
        }

        $aplicar = (bool) $this->option('aplicar');
        $lote = [];
        $total = 0;
        $porMunicipio = [];
        $agora = now();

        while (($linha = fgetcsv($handle)) !== false) {
            $d = array_combine($cabecalho, $linha);

            if (empty($d['nome_busca']) || empty($d['cod_ibge'])) {
                continue;
            }

            $cod = (int) $d['cod_ibge'];
            $porMunicipio[$cod] ??= ['logradouros' => 0, 'bairros' => [], 'enderecos' => 0];
            $porMunicipio[$cod]['logradouros']++;
            $porMunicipio[$cod]['enderecos'] += (int) ($d['enderecos'] ?? 0);
            if (! empty($d['bairro'])) {
                $porMunicipio[$cod]['bairros'][mb_strtoupper($d['bairro'])] = true;
            }

            $lote[] = [
                'cod_ibge' => $cod,
                'tipo' => $this->corta($d['tipo'] ?? '', 30),
                'nome' => $this->corta($d['nome'] ?? '', 255),
                'bairro' => $this->corta($d['bairro'] ?? '', 255) ?: null,
                'cep' => $this->corta($d['cep'] ?? '', 8) ?: null,
                'nome_busca' => $this->corta($d['nome_busca'], 255),
                'numero_min' => $d['numero_min'] !== '' ? (int) $d['numero_min'] : null,
                'numero_max' => $d['numero_max'] !== '' ? (int) $d['numero_max'] : null,
                'enderecos' => (int) ($d['enderecos'] ?? 0),
                'latitude' => $d['latitude'] !== '' ? (float) $d['latitude'] : null,
                'longitude' => $d['longitude'] !== '' ? (float) $d['longitude'] : null,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
            $total++;

            if ($aplicar && count($lote) >= self::LOTE) {
                $this->gravar($lote);
                $lote = [];
            }
        }

        fclose($handle);

        if ($aplicar && $lote !== []) {
            $this->gravar($lote);
        }

        $this->table(
            ['Município', 'UF', 'Logradouros', 'Bairros', 'Endereços'],
            array_map(function ($cod, $d) {
                $m = MunicipioIbge::query()->find($cod);

                return [
                    $m?->nome ?? $cod,
                    $m?->uf ?? '—',
                    $d['logradouros'],
                    count($d['bairros']),
                    $d['enderecos'],
                ];
            }, array_keys($porMunicipio), $porMunicipio),
        );

        if (! $aplicar) {
            $this->newLine();
            $this->warn("Somente leitura: {$total} linha(s) lidas, nada gravado. Use --aplicar.");

            return self::SUCCESS;
        }

        foreach ($porMunicipio as $cod => $d) {
            $m = MunicipioIbge::query()->find($cod);

            ImportacaoCnefe::query()->updateOrCreate(
                ['cod_ibge' => $cod],
                [
                    'municipio' => $m?->nome ?? (string) $cod,
                    'uf' => $m?->uf ?? '--',
                    'logradouros' => $d['logradouros'],
                    'bairros' => count($d['bairros']),
                    'enderecos' => $d['enderecos'],
                ],
            );
        }

        $this->info("{$total} logradouro(s) oficiais gravados em ".count($porMunicipio).' município(s).');
        $this->line('Agora rode `logradouros:normalizar <cidade>` para comparar com o cadastro manual.');

        return self::SUCCESS;
    }

    /** @param  list<array<string,mixed>>  $lote */
    private function gravar(array $lote): void
    {
        // Reimportar o mesmo município atualiza em vez de duplicar — o CNEFE
        // ganha edições novas e a chave (município, nome, bairro) é estável.
        DB::transaction(fn () => LogradouroOficial::query()->upsert(
            $lote,
            ['cod_ibge', 'nome_busca', 'bairro'],
            ['tipo', 'nome', 'cep', 'numero_min', 'numero_max', 'enderecos', 'latitude', 'longitude', 'updated_at'],
        ));
    }

    private function corta(string $v, int $n): string
    {
        return mb_substr(trim($v), 0, $n);
    }
}
