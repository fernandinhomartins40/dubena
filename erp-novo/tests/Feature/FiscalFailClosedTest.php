<?php

namespace Tests\Feature;

use App\Domain\Fiscal\Drivers\NFePHPSefazDriver;
use App\Domain\Fiscal\XmlNfeBuilder;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Fiscal\ConfigFiscal;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\MunicipioIbge;
use App\Models\Produto\Produto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class FiscalFailClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_xml_real_recusa_item_sem_snapshot_tributario_completo(): void
    {
        $empresa = Empresa::factory()->create();
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        $nota = NotaFiscal::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'modelo' => '55',
            'tipo' => 'S',
            'serie' => 1,
            'numero' => 1,
            'situacao' => 'EMITIDA',
        ]);
        $nota->itens()->create([
            'produto_id' => $produto->id,
            'numero_item' => 1,
            'quantidade' => 1,
            'valor_unitario' => 100,
            'valor_total' => 100,
            'cfop' => '5102',
            'cst_icms' => '00',
        ]);

        try {
            app(XmlNfeBuilder::class)->montar($nota, [], []);
            $this->fail('O XML real aceitou um item sem snapshot tributário completo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('snapshot_fiscal', $e->errors());
        }
    }

    public function test_driver_usa_ambiente_crt_e_municipio_da_empresa_sem_defaults(): void
    {
        $empresa = Empresa::factory()->create([
            'razao_social' => 'Emitente Fiscal Ltda',
            'nome_fantasia' => 'Emitente',
            'cnpj' => '11222333000181',
            'inscricao_estadual' => '1234567890',
            'uf' => 'PR',
            'cep' => '80000000',
            'endereco' => 'Rua Fiscal',
            'numero' => '100',
            'bairro' => 'Centro',
        ]);
        MunicipioIbge::query()->create([
            'cod_ibge' => 4106902,
            'nome' => 'Curitiba',
            'uf' => 'PR',
            'nome_busca' => 'CURITIBA',
            'cod_uf' => 41,
        ]);
        Estado::query()->firstOrCreate(
            ['uf' => 'PR'],
            ['descricao' => 'Paraná', 'cod_ibge' => 41],
        );
        $cidade = Cidade::withoutGrupo()->create([
            'grupo_id' => $empresa->grupo_id,
            'descricao' => 'Curitiba',
            'uf' => 'PR',
            'cod_ibge' => 4106902,
            'municipio_ibge' => 4106902,
            'ativo' => true,
        ]);
        $empresa->update(['cidade_id' => $cidade->id, 'cidade' => 'Curitiba']);
        ConfigFiscal::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'ambiente' => 1,
            'serie_nfe' => 7,
            'serie_nfce' => 9,
            'regime_tributario' => 4,
        ]);
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'uf' => 'PR',
        ]);
        $nota = NotaFiscal::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'modelo' => '55',
            'tipo' => 'S',
            'serie' => 7,
            'numero' => 1,
            'situacao' => 'EMITIDA',
        ]);
        $nota->setAttribute('natureza_operacao', 'VENDA DE MERCADORIA');

        $metodo = new ReflectionMethod(NFePHPSefazDriver::class, 'dadosEmitente');
        $metodo->setAccessible(true);
        $dados = $metodo->invoke(app(NFePHPSefazDriver::class), $nota);

        $this->assertSame(1, $dados['ambiente']);
        $this->assertSame(4, $dados['crt']);
        $this->assertSame(41, $dados['cuf']);
        $this->assertSame(4106902, $dados['cod_municipio']);
        $this->assertSame('PR', $dados['uf']);
    }

    public function test_driver_recusa_cadastro_emitente_incompleto(): void
    {
        $empresa = Empresa::factory()->create([
            'cnpj' => null,
            'inscricao_estadual' => null,
            'endereco' => null,
            'bairro' => null,
        ]);
        ConfigFiscal::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'ambiente' => 2,
            'serie_nfe' => 1,
            'serie_nfce' => 1,
            'regime_tributario' => 1,
        ]);
        $nota = NotaFiscal::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'modelo' => '55',
            'tipo' => 'S',
            'serie' => 1,
            'numero' => 1,
            'situacao' => 'EMITIDA',
        ]);

        $metodo = new ReflectionMethod(NFePHPSefazDriver::class, 'dadosEmitente');
        $metodo->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cadastro fiscal incompleto');
        $metodo->invoke(app(NFePHPSefazDriver::class), $nota);
    }
}
