<?php

namespace Tests\Feature;

use App\Domain\Cliente\GeocodificarClienteJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PIE-26 — o job de geocodificação só pode chegar ao worker depois do commit.
 * Despachado dentro da transação (cadastro, pedido do app) com a fila em
 * `after_commit=false`, o worker podia rodar antes e não achar o cliente.
 *
 * A fila `sync` da suíte respeita o mesmo contrato da redis: se o job não é
 * after-commit, ele executa na hora, ainda dentro da transação.
 */
class GeocodificacaoAposCommitTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_despachado_na_transacao_so_executa_apos_o_commit(): void
    {
        $executados = 0;
        Queue::before(function () use (&$executados) {
            $executados++;
        });

        DB::transaction(function () use (&$executados) {
            // Id inexistente: o job sai cedo, o que importa é QUANDO ele roda.
            GeocodificarClienteJob::dispatch(999999);
            $this->assertSame(0, $executados, 'o job rodou antes do commit');
        });

        $this->assertSame(1, $executados);
    }
}
