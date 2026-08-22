<?php

namespace App\Console\Commands;

use App\Domain\Identidade\NormalizadorTexto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * identidade:reindexar — (re)constrói os traços de identidade de toda a base.
 *
 * Roda uma vez na implantação e sempre que os pesos/normalizações mudarem: se
 * a regra de fonetização muda, os traços gravados envelhecem e o casamento
 * passa a divergir do que o código faz ao vivo.
 *
 * Escreve em lote com SQL direto — 55 mil clientes via Eloquent levariam
 * minutos e milhões de eventos de model sem nenhum ganho.
 */
class IdentidadeReindexar extends Command
{
    protected $signature = 'identidade:reindexar
        {--empresa= : Limita a uma empresa}
        {--lote=2000 : Tamanho do lote}';

    protected $description = 'Reconstrói os traços de identidade dos clientes (telefone, nome fonético, endereço, documento).';

    public function handle(): int
    {
        $empresa = $this->option('empresa');
        $lote = max(100, (int) $this->option('lote'));

        $total = DB::table('clientes')
            ->when($empresa, fn ($q) => $q->where('empresa_id', $empresa))
            ->count();

        if ($total === 0) {
            $this->info('Nenhum cliente para indexar.');

            return self::SUCCESS;
        }

        $this->info("Reindexando {$total} cliente(s)…");
        $barra = $this->output->createProgressBar($total);

        // Telefones em memória, agrupados por cliente: uma consulta em vez de
        // uma por cliente.
        $telefones = DB::table('clientetelefones')
            ->select('cliente_id', 'telefone')
            ->get()
            ->groupBy('cliente_id');

        $gravados = 0;

        DB::table('clientes')
            ->when($empresa, fn ($q) => $q->where('empresa_id', $empresa))
            ->orderBy('id')
            ->chunk($lote, function ($clientes) use (&$gravados, $telefones, $barra) {
                $ids = collect($clientes)->pluck('id');
                DB::table('cliente_identidades')->whereIn('cliente_id', $ids)->delete();

                $linhas = [];
                foreach ($clientes as $c) {
                    $fones = ($telefones[$c->id] ?? collect())->pluck('telefone')->all();
                    foreach ($this->tracosDe($c, $fones) as [$tipo, $valor]) {
                        $linhas[] = [
                            'empresa_id' => $c->empresa_id,
                            'cliente_id' => $c->id,
                            'tipo' => $tipo,
                            'valor' => $valor,
                            'origem' => 'reindex',
                            'verificado' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    $barra->advance();
                }

                foreach (array_chunk($linhas, 1000) as $pedaco) {
                    DB::table('cliente_identidades')->insert($pedaco);
                    $gravados += count($pedaco);
                }
            });

        $barra->finish();
        $this->newLine(2);
        $this->info("{$gravados} traço(s) gravado(s).");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $telefones
     * @return list<array{0: string, 1: string}>
     */
    private function tracosDe(object $c, array $telefones): array
    {
        $tracos = [];

        if ($nome = NormalizadorTexto::nomeFonetico($c->nome ?? null)) {
            $tracos[] = ['nome_fonetico', $nome];
        }

        // documento(): restaura o zero a esquerda, igual ao IdentidadeCliente.
        $cpf = NormalizadorTexto::documento($c->cpf ?? null, 11);
        if (strlen($cpf) === 11) {
            $tracos[] = ['cpf', $cpf];
        }

        $cnpj = NormalizadorTexto::documento($c->cnpj ?? null, 14);
        if (strlen($cnpj) === 14) {
            $tracos[] = ['cnpj', $cnpj];
        }

        if ($email = NormalizadorTexto::basico($c->email ?? null)) {
            $tracos[] = ['email', $email];
        }

        foreach (array_unique(array_filter(array_map(
            fn ($t) => NormalizadorTexto::telefone($t), $telefones,
        ))) as $fone) {
            $tracos[] = ['telefone', $fone];
        }

        $endereco = NormalizadorTexto::endereco($c->endereco ?? null, $c->numero ?? null);
        if ($endereco !== '' && ! empty($c->cidade_id)) {
            $tracos[] = ['endereco', $c->cidade_id.'|'.$endereco];
        }

        // Deduplica: o mesmo traço não pode repetir (unique da tabela).
        return array_values(array_unique($tracos, SORT_REGULAR));
    }
}
