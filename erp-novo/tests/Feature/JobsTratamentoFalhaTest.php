<?php

namespace Tests\Feature;

use App\Domain\Cliente\GeocodificarClienteJob;
use App\Domain\Geografico\ImportarLogradourosJob;
use App\Domain\Logistica\Jobs\AtribuirPedidoJob;
use App\Domain\Mobile\Jobs\EnviarPushJob;
use App\Domain\Relatorio\NotificarEstoqueBaixoJob;
use App\Jobs\ExecutarMigracaoJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use ReflectionClass;
use Tests\TestCase;

/**
 * T5.0/T5.1 — tratamento de falha nos jobs de fila.
 *
 * A seção 5 da auditoria afirmava que "os 6 jobs todos declaram `$tries`". Era
 * falso, e mais errado do que o crítico chegou a registrar: **nenhum** dos 4
 * jobs de domínio tinha `failed()`, e `GeocodificarClienteJob` tinha `$tries`
 * decorativo — engolia a exceção num `catch`, então as 3 tentativas nunca
 * aconteciam.
 *
 * Este teste existe para que a regressão não passe silenciosa: um job novo sem
 * tratamento de falha reprova aqui, e não seis meses depois quando alguém for
 * investigar por que um alerta nunca chegou.
 */
class JobsTratamentoFalhaTest extends TestCase
{
    /** @return list<class-string> */
    private function jobs(): array
    {
        return [
            NotificarEstoqueBaixoJob::class,
            GeocodificarClienteJob::class,
            AtribuirPedidoJob::class,
            EnviarPushJob::class,
            ImportarLogradourosJob::class,
            ExecutarMigracaoJob::class,
        ];
    }

    public function test_todo_job_declara_limite_de_tentativas(): void
    {
        foreach ($this->jobs() as $classe) {
            $r = new ReflectionClass($classe);

            $this->assertTrue(
                $r->hasProperty('tries'),
                "{$classe} não declara \$tries: sem limite explícito, uma falha "
                .'persistente reenfileira o job indefinidamente.',
            );
        }
    }

    public function test_todo_job_trata_a_desistencia(): void
    {
        foreach ($this->jobs() as $classe) {
            $r = new ReflectionClass($classe);

            $this->assertTrue(
                $r->hasMethod('failed'),
                "{$classe} não implementa failed(): quando as tentativas se "
                .'esgotam, a única evidência fica em `failed_jobs`, que ninguém '
                .'lê no dia a dia.',
            );
        }
    }

    public function test_jobs_de_integracao_externa_tem_backoff_e_timeout(): void
    {
        // Estes três chamam serviço de terceiro (push, Google Maps) ou rodam
        // cálculo pesado. Sem backoff, as tentativas se atropelam; sem timeout,
        // uma chamada pendurada segura o worker e atrasa a fila inteira.
        foreach ([NotificarEstoqueBaixoJob::class, GeocodificarClienteJob::class, EnviarPushJob::class] as $classe) {
            $r = new ReflectionClass($classe);

            $this->assertTrue($r->hasProperty('backoff'), "{$classe} sem \$backoff.");
            $this->assertTrue($r->hasProperty('timeout'), "{$classe} sem \$timeout.");
        }
    }

    public function test_o_geocode_relanca_a_excecao(): void
    {
        // O defeito original: `catch` que só logava fazia o job "suceder" na
        // primeira tentativa. `$tries = 3` virava decoração e o cliente ficava
        // permanentemente sem coordenada — invisível no mapa da central.
        $fonte = file_get_contents((new ReflectionClass(GeocodificarClienteJob::class))->getFileName());

        $this->assertStringContainsString(
            'throw $e;',
            $fonte,
            'GeocodificarClienteJob voltou a engolir a exceção: as tentativas param de acontecer.',
        );
    }

    public function test_todos_sao_shouldqueue(): void
    {
        foreach ($this->jobs() as $classe) {
            $this->assertContains(
                ShouldQueue::class,
                class_implements($classe) ?: [],
                "{$classe} deixou de ser ShouldQueue.",
            );
        }
    }

    public function test_importacao_geografica_carrega_contrato_de_envelope(): void
    {
        $source = file_get_contents((new ReflectionClass(ImportarLogradourosJob::class))->getFileName());

        $this->assertStringContainsString('TenantEnvelopeJob', $source);
        $this->assertStringContainsString('captureTenantEnvelope', $source);
        $this->assertStringContainsString('withinTenantEnvelope', $source);
        $this->assertStringContainsString('sem TenantEnvelope serializado', $source);
    }
}
