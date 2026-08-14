<?php

namespace Tests\Feature;

use App\Domain\Cobranca\SituacaoBoleto;
use App\Domain\Fiscal\SituacaoNota;
use App\Etl\Migrators\CobrancaMigrator;
use App\Etl\Migrators\FiscalMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Situação gravada pelo ETL tem de ser um valor VÁLIDO do enum do domínio.
 *
 * Por que este teste existe: o migrador de cobrança gravava a string "PAGO",
 * que não é um case de `SituacaoBoleto` (o correto é LIQUIDADO). A carga passou
 * sem erro — 21 mil boletos gravados — e o defeito só apareceu muito depois,
 * como um HTTP 500 opaco na tela de boletos: "PAGO is not a valid backing value
 * for enum". O Eloquent só valida no READ.
 *
 * Aqui as situações possíveis são exercitadas contra o enum, no build.
 */
class MigradorEnumValidoTest extends TestCase
{
    use RefreshDatabase;

    /** Chama um método privado do migrador com os argumentos dados. */
    private function chamar(object $migrador, string $metodo, array $args): mixed
    {
        $m = new ReflectionMethod($migrador, $metodo);
        $m->setAccessible(true);

        return $m->invokeArgs($migrador, $args);
    }

    public function test_situacao_do_boleto_e_sempre_um_case_do_enum(): void
    {
        $migrador = app(CobrancaMigrator::class);

        // Combinações que o legado produz: cancelado, com remessa, e nenhum
        // dos dois — cada uma com a parcela baixada ou não.
        $cenarios = [
            [(object) ['cancelado' => '1', 'gerouremessa' => '0'], false],
            [(object) ['cancelado' => '0', 'gerouremessa' => '1'], false],
            [(object) ['cancelado' => '0', 'gerouremessa' => '0'], false],
            [(object) ['cancelado' => '0', 'gerouremessa' => '1'], true],
            [(object) ['cancelado' => null, 'gerouremessa' => null], true],
        ];

        foreach ($cenarios as [$linha, $baixado]) {
            $valor = $this->chamar($migrador, 'situacao', [$linha, $baixado]);

            $this->assertNotNull(
                SituacaoBoleto::tryFrom($valor),
                "situação '{$valor}' não é um case de SituacaoBoleto"
            );
        }
    }

    public function test_situacao_da_nota_e_sempre_um_case_do_enum(): void
    {
        $migrador = app(FiscalMigrator::class);

        $cenarios = [
            (object) ['inutilizarcancelar' => '1'],
            (object) ['cancelamentomotivo' => 'erro de digitação'],
            (object) ['protocoloretornocancelamento' => '123'],
            (object) ['protocolo' => '135240000123456'],
            (object) ['datahoraautorizacao' => '2026-01-01 10:00:00'],
            (object) [],   // nada preenchido → rascunho
        ];

        foreach ($cenarios as $linha) {
            $valor = $this->chamar($migrador, 'situacao', [$linha]);

            $this->assertNotNull(
                SituacaoNota::tryFrom($valor),
                "situação '{$valor}' não é um case de SituacaoNota"
            );
        }
    }
}
