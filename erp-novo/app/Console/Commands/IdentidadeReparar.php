<?php

namespace App\Console\Commands;

use App\Domain\Identidade\IdentidadeCliente;
use App\Models\Cliente\Cliente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * F6-06A — repara os traços de identidade de quem foi escrito fora do observer.
 *
 * ## Por que existe
 *
 * O `ClienteIdentidadeObserver` cobre todo caminho de escrita **do Eloquent**.
 * O que ele não alcança é `DB::table()->insert()` — e a ponte NFWEB gravava
 * telefone assim, o que este mesmo commit corrigiu.
 *
 * Corrigir a escrita resolve daqui para a frente. Os clientes já cadastrados por
 * aquele caminho continuam **invisíveis ao motor de identidade**: têm telefone
 * na tabela e nenhum traço. O próximo cadastro com o mesmo número vira duplicata
 * sem sequer ser comparado — que é justamente o problema que o motor existe para
 * resolver.
 *
 * ## Reconstrói, não inventa
 *
 * Os traços são **derivados** do que o cliente já tem: nome, documento,
 * telefone, endereço. Reconstruí-los não cria informação nova nem decide nada —
 * refaz o cálculo que deveria ter rodado na escrita.
 *
 * Por isso é seguro rodar quantas vezes for preciso, e por isso a operação é
 * idempotente: `sincronizar` reescreve o conjunto de traços do cliente.
 *
 * `--dry-run` por padrão? **Não.** Aqui o "faz nada" é o estado ruim: um cliente
 * sem traço já está errado, e a correção não tem como piorar. Mas o comando
 * relata o que vai tocar antes de tocar.
 */
class IdentidadeReparar extends Command
{
    protected $signature = 'identidade:reparar
        {--empresa= : repara só esta empresa}
        {--dry-run : lista quantos seriam reparados, sem escrever}';

    protected $description = 'Reconstrói os traços de identidade de clientes sem traço (F6-06A).';

    public function handle(IdentidadeCliente $identidade): int
    {
        $empresaId = $this->option('empresa') !== null ? (int) $this->option('empresa') : null;

        // Clientes SEM nenhum traço. É o recorte certo: quem tem traço já passou
        // pelo motor, e resincronizar a base inteira seria caro sem ganho.
        $semTraco = Cliente::withoutTenant()
            ->when($empresaId !== null, fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereNotExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('cliente_identidades')
                ->whereColumn('cliente_identidades.cliente_id', 'clientes.id'))
            ->orderBy('id');

        $total = (clone $semTraco)->count();

        if ($total === 0) {
            $this->info('Nenhum cliente sem traço de identidade.');

            return self::SUCCESS;
        }

        $this->warn("{$total} cliente(s) sem traço de identidade.");

        if ($this->option('dry-run')) {
            $this->line('Dry-run: nada foi escrito.');

            return self::SUCCESS;
        }

        $reparados = 0;
        $falhas = 0;

        // `chunkById` e não `get()`: a base real tem dezenas de milhares de
        // clientes, e carregar todos de uma vez estoura a memória do processo —
        // o mesmo motivo pelo qual o CI precisou de 512M para a suíte.
        $semTraco->chunkById(200, function ($clientes) use ($identidade, &$reparados, &$falhas) {
            foreach ($clientes as $cliente) {
                try {
                    $identidade->sincronizar($cliente);
                    $reparados++;
                } catch (\Throwable $e) {
                    // Um cliente com dado impossível não pode parar o reparo dos
                    // outros — mas some do relatório se for engolido em silêncio.
                    $falhas++;
                    $this->error("cliente #{$cliente->id}: ".$e->getMessage());
                }
            }
        });

        $this->info("{$reparados} cliente(s) reparado(s).");

        if ($falhas > 0) {
            $this->error("{$falhas} falha(s) — veja as mensagens acima.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
