<?php

namespace Tests\Caracterizacao;

use Tests\TestCase;
use Tests\Caracterizacao\Support\FixturesFiscais;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Http\Controllers\FechamentomensalgestaoController;

/**
 * Caracterização — FASE 2 (Salto 1, migração de exports).
 *
 * Garante que geraArquivoDRE / geraArquivoBalanco (migrados de Maatwebsite 2.1 +
 * PHPExcel para PhpSpreadsheet) realmente geram os arquivos xlsx e pdf sem erro,
 * exercitando setStyle (borders/fill/alignment), merges, números e o save em
 * storage/exports. Não valida valores fiscais (isso é a emissão homologada) —
 * valida que a MIGRAÇÃO não quebrou a geração do DRE/Balanço.
 *
 * PHPUnit 8.5 / Laravel 6 / PHP 7.4.
 */
class FechamentoExportTest extends TestCase
{
    use DatabaseTransactions;
    use FixturesFiscais;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Database\Eloquent\Model::unsetEventDispatcher();
    }

    /** Monta o shape que geraArquivo* espera: ->data (array) e ->fulldata (objs com ->cabecalho). */
    private function dadosSinteticos($campoDescricao)
    {
        // 5 linhas de header + 3 linhas de conteúdo + 3 de rodapé.
        $linha = function ($cabecalho, $desc, $valor) use ($campoDescricao) {
            $o = (object) ['cabecalho' => $cabecalho, 'valor' => $valor, 'percentual' => 0];
            $o->{$campoDescricao} = $desc;
            return $o;
        };
        $header = [];
        for ($i = 0; $i < 5; $i++) {
            $header[] = (object) ['cabecalho' => 0, 'valor' => '', 'percentual' => '', $campoDescricao => ''];
        }
        $corpo = [
            $linha(2, 'Grupo', ''),
            $linha(1, 'Conta', 100.5),
            $linha(0, 'Item teste', 50.25),
        ];
        $rodape = [];
        for ($i = 0; $i < 3; $i++) {
            $rodape[] = (object) ['cabecalho' => 0, 'valor' => '', 'percentual' => '', $campoDescricao => ''];
        }
        $fulldata = array_merge($header, $corpo, $rodape);

        // data: matriz simples (o fromArray só precisa de linhas/colunas).
        $data = [];
        foreach ($fulldata as $r) {
            $data[] = [' ', $r->{$campoDescricao}, $r->valor, $r->percentual, ' '];
        }
        return (object) ['fulldata' => $fulldata, 'data' => $data];
    }

    public function testGeraArquivoDREXlsxEPdf()
    {
        $this->criarCenarioFiscal();
        $ctrl = new FechamentomensalgestaoController();
        $dre = $this->dadosSinteticos('plano');

        $xlsx = $ctrl->geraArquivoDRE('xlsx', $dre, 'dre_teste_' . uniqid());
        $this->assertNotNull($xlsx, 'DRE xlsx não gerado');
        $this->assertFileExists($xlsx);
        @unlink($xlsx);

        $pdf = $ctrl->geraArquivoDRE('pdf', $dre, 'dre_teste_' . uniqid());
        $this->assertNotNull($pdf, 'DRE pdf não gerado');
        $this->assertFileExists($pdf);
        @unlink($pdf);
    }

    public function testGeraArquivoBalancoXlsxEPdf()
    {
        $this->criarCenarioFiscal();
        $ctrl = new FechamentomensalgestaoController();
        $bal = $this->dadosSinteticos('descricao');

        $xlsx = $ctrl->geraArquivoBalanco('xlsx', $bal, 'bal_teste_' . uniqid());
        $this->assertNotNull($xlsx, 'Balanço xlsx não gerado');
        $this->assertFileExists($xlsx);
        @unlink($xlsx);

        $pdf = $ctrl->geraArquivoBalanco('pdf', $bal, 'bal_teste_' . uniqid());
        $this->assertNotNull($pdf, 'Balanço pdf não gerado');
        $this->assertFileExists($pdf);
        @unlink($pdf);
    }
}
