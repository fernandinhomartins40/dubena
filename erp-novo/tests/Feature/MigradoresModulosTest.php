<?php

namespace Tests\Feature;

use App\Etl\Migrators\CadastrosContabeisMigrator;
use App\Etl\Migrators\CaixaMigrator;
use App\Etl\Migrators\CobrancaMigrator;
use App\Etl\Migrators\ComplementosMigrator;
use App\Etl\Migrators\EmpresaConfigMigrator;
use App\Etl\Migrators\EstoqueMigrator;
use App\Etl\Migrators\FinanceiroMigrator;
use App\Etl\Migrators\FiscalMigrator;
use App\Etl\Migrators\SatelitesMigrator;
use App\Etl\Support\MigrationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Migradores dos módulos que estavam sem carga (estoque, financeiro, fiscal,
 * caixa, cobrança, satélites, config da empresa).
 *
 * O que estes testes protegem: a falha que deixou 8 módulos vazios foi
 * silenciosa — o migrador não achava a tabela, engolia a exceção e reportava
 * "0 lidos" como se tivesse dado certo. Aqui garante-se que, SEM legado, o
 * resultado é explícito (aviso dizendo que não há o que migrar), e que o
 * dry-run nunca grava.
 */
class MigradoresModulosTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{0:string, 1:object}> */
    public static function migradores(): array
    {
        return [
            'estoque' => ['estoque', EstoqueMigrator::class],
            'financeiro' => ['financeiro', FinanceiroMigrator::class],
            'fiscal' => ['fiscal', FiscalMigrator::class],
            'caixa' => ['caixa', CaixaMigrator::class],
            'cobranca' => ['cobranca', CobrancaMigrator::class],
            'satelites' => ['satelites', SatelitesMigrator::class],
            'empresa-config' => ['empresa-config', EmpresaConfigMigrator::class],
            'complementos' => ['complementos', ComplementosMigrator::class],
            'cadastros-contabeis' => ['cadastros-contabeis', CadastrosContabeisMigrator::class],
        ];
    }

    /**
     * @dataProvider migradores
     */
    public function test_sem_legado_o_resultado_e_explicito(string $nome, string $classe): void
    {
        /** @var \App\Etl\Contracts\Migrator $m */
        $m = app($classe);

        $this->assertSame($nome, $m->nome());

        $r = $m->migrar(new MigrationContext());

        // Nada gravado — e, o mais importante, o motivo é DITO.
        $this->assertSame(0, $r->gravados);
        $this->assertNotEmpty(
            $r->avisos,
            "{$nome}: sem legado o migrador precisa AVISAR, não reportar sucesso vazio"
        );
    }

    /**
     * @dataProvider migradores
     */
    public function test_dry_run_nunca_grava(string $nome, string $classe): void
    {
        /** @var \App\Etl\Contracts\Migrator $m */
        $m = app($classe);

        $r = $m->migrar(new MigrationContext(dryRun: true));

        $this->assertSame(0, $r->gravados, "{$nome}: dry-run não pode gravar");
    }

    public function test_todos_estao_no_registry_e_a_ordem_resolve(): void
    {
        $nomes = array_map(
            fn ($m) => $m->nome(),
            \App\Etl\MigratorRegistry::resolved()
        );

        foreach (array_column(self::migradores(), 0) as $nome) {
            $this->assertContains($nome, $nomes, "{$nome} não está no MigratorRegistry");
        }

        // Dependências: o que alimenta vem antes de quem consome.
        $pos = array_flip($nomes);
        $this->assertLessThan($pos['fiscal'], $pos['produtos'], 'fiscal depende de produtos');
        $this->assertLessThan($pos['caixa'], $pos['financeiro'], 'caixa depende de financeiro');
        $this->assertLessThan($pos['cobranca'], $pos['financeiro'], 'cobrança depende de financeiro');
        $this->assertLessThan($pos['satelites'], $pos['clientes'], 'satélites dependem de clientes');
    }
}
