<?php

namespace Tests\Migration;

use App\Etl\Migrators\EmpresasMigrator;
use App\Etl\Support\MigrationContext;
use App\Models\Fiscal\ConfigFiscal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A carga guardava o fiscal da empresa em `empresa_configs.dados.fiscal`, que a
 * emissão não lê; `config_fiscais` (onde ela lê) ficava vazia nas 7 empresas
 * da homologação. E copiar "produção" para fora de produção armaria emissão
 * real num ambiente de teste.
 */
class EmpresaConfigFiscalCargaTest extends TestCase
{
    use RefreshDatabase;

    private string $legadoConn = 'legado_teste';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set("database.connections.{$this->legadoConn}", [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        $leg = DB::connection($this->legadoConn);
        $leg->statement('create table empresasgrupos (id integer, descricao text, ativo text)');
        $leg->statement('create table empresas (id integer, grupo_id integer, razaosocial text, cnpj text, ativo text, inscricao_estadual text, inscricao_municipal text,
            nfetipoambiente text, nfcetipoambiente text, nfecrt text, nfeserie text, nfceserie text,
            nfcetokenid text, nfcetoken text, nfcetokenid_prod text, nfcetoken_prod text)');
        $leg->table('empresasgrupos')->insert(['id' => 2, 'descricao' => 'Rede', 'ativo' => '1']);
        $leg->table('empresas')->insert([
            'id' => 2, 'grupo_id' => 2, 'razaosocial' => 'Revenda', 'cnpj' => '04.190.715/0001-05', 'ativo' => '1', 'inscricao_estadual' => '9012345678', 'inscricao_municipal' => '5555',
            'nfetipoambiente' => '1', 'nfcetipoambiente' => '1', 'nfecrt' => '3', 'nfeserie' => '1', 'nfceserie' => '2',
            'nfcetokenid' => '000002', 'nfcetoken' => 'CSC-HOMOLOG', 'nfcetokenid_prod' => '000001', 'nfcetoken_prod' => 'CSC-PROD',
        ]);
    }

    private function carregar(): array
    {
        return (new EmpresasMigrator)->migrar(new MigrationContext(conexaoLegado: $this->legadoConn))->avisos;
    }

    public function test_fora_de_producao_vai_para_homologacao_com_o_csc_de_homologacao(): void
    {
        $avisos = $this->carregar();

        $f = ConfigFiscal::withoutTenant()->where('empresa_id', 2)->firstOrFail();
        $this->assertSame(2, (int) $f->ambiente, 'produção do legado não pode virar emissão real num ambiente de teste');
        $this->assertSame(3, (int) $f->regime_tributario);
        $this->assertSame(2, (int) $f->serie_nfce);
        $this->assertSame(['000002', 'CSC-HOMOLOG'], [$f->csc_id, $f->csc_token]);
        $this->assertStringContainsString('gravada(s) em HOMOLOGAÇÃO', implode(' | ', $avisos));
    }

    /** O dump traz a coluna com sublinhado; só a grafia sem ele era lida. */
    public function test_inscricao_estadual_do_dump_e_migrada(): void
    {
        $this->carregar();

        $empresa = \App\Models\Empresa::query()->withoutGlobalScopes()->find(2);
        $this->assertSame('9012345678', $empresa->inscricao_estadual);
        $this->assertSame('5555', $empresa->inscricao_municipal);
    }

    public function test_em_producao_mantem_producao_e_usa_o_csc_de_producao(): void
    {
        $this->app['env'] = 'production';
        $this->carregar();

        $f = ConfigFiscal::withoutTenant()->where('empresa_id', 2)->firstOrFail();
        $this->assertSame(1, (int) $f->ambiente);
        $this->assertSame(['000001', 'CSC-PROD'], [$f->csc_id, $f->csc_token]);
    }

    /** Recarga não desfaz o que alguém ajustou na tela. */
    public function test_recarga_nao_sobrescreve_o_ajuste_feito_aqui(): void
    {
        $this->carregar();
        ConfigFiscal::withoutTenant()->where('empresa_id', 2)->update(['csc_id' => 'AJUSTADO']);

        $this->carregar();

        $this->assertSame('AJUSTADO', ConfigFiscal::withoutTenant()->where('empresa_id', 2)->value('csc_id'));
    }
}
