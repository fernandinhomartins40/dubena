<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\Financeiro;
use App\Models\Fiscal\NfRecebida;
use App\Models\Produto\Produto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F06 — NF de Entrada via HTTP (expõe a capacidade pronta do NfEntradaService).
 * Importar XML → registra nota+itens; processar → entrada de estoque + financeiro
 * a pagar. Cobre o controller (a lógica de domínio já tem NfEntradaTest).
 */
class NfEntradaApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        return [$user, $empresa];
    }

    private function xml(string $cProd, float $qtd, float $vUn): string
    {
        $vProd = number_format($qtd * $vUn, 2, '.', '');
        $chave = str_pad('351', 44, '7');

        return <<<XML
        <nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
          <NFe><infNFe Id="NFe{$chave}" versao="4.00">
            <ide><nNF>500</nNF><serie>1</serie><dhEmi>2026-06-01T10:00:00-03:00</dhEmi></ide>
            <emit><CNPJ>12345678000199</CNPJ><xNome>Distribuidora X</xNome></emit>
            <det nItem="1"><prod>
              <cProd>{$cProd}</cProd><xProd>Botijao P13</xProd><NCM>27111910</NCM>
              <CFOP>1102</CFOP><qCom>{$qtd}</qCom><vUnCom>{$vUn}</vUnCom><vProd>{$vProd}</vProd>
            </prod></det>
            <total><ICMSTot><vProd>{$vProd}</vProd><vNF>{$vProd}</vNF></ICMSTot></total>
          </infNFe></NFe>
        </nfeProc>
        XML;
    }

    public function test_importa_xml_via_api(): void
    {
        [$user, $empresa] = $this->suporte();
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/fiscal/nf-entrada/importar', [
            'xml' => $this->xml((string) $produto->id, 10, 90),
        ])->assertCreated()
            ->assertJsonPath('data.numero', '500')
            ->assertJsonPath('data.emitente_nome', 'Distribuidora X');

        $this->assertSame(1, NfRecebida::withoutTenant()->count());
    }

    public function test_lista_e_mostra_nf_entrada(): void
    {
        [$user, $empresa] = $this->suporte();
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $imp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/fiscal/nf-entrada/importar', ['xml' => $this->xml((string) $produto->id, 5, 80)])->json('data');

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/fiscal/nf-entrada')->assertOk()->assertJsonPath('meta.total', 1);
        $this->actingAs($user, 'sanctum')->getJson("/api/admin/fiscal/nf-entrada/{$imp['id']}")->assertOk()->assertJsonPath('data.numero', '500');
    }

    public function test_processar_da_entrada_no_estoque_e_gera_financeiro(): void
    {
        [$user, $empresa] = $this->suporte();
        $produto = Produto::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $setor = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $imp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/fiscal/nf-entrada/importar', ['xml' => $this->xml((string) $produto->id, 10, 90)])->json('data');

        $this->actingAs($user, 'sanctum')->postJson("/api/admin/fiscal/nf-entrada/{$imp['id']}/processar", ['setor_id' => $setor->id])
            ->assertOk()->assertJsonPath('data.situacao', 'processada');

        // Estoque entrou e financeiro a pagar foi gerado.
        $this->assertEqualsWithDelta(10.0, app(EstoqueService::class)->saldoDerivado($setor->id, $produto->id), 0.001);
        $this->assertSame(1, Financeiro::withoutTenant()->where('pagarreceber', 'P')->count());

        // Idempotente: processar de novo não duplica.
        $this->actingAs($user, 'sanctum')->postJson("/api/admin/fiscal/nf-entrada/{$imp['id']}/processar", ['setor_id' => $setor->id])->assertOk();
        $this->assertSame(1, Financeiro::withoutTenant()->where('pagarreceber', 'P')->count());
    }

    public function test_exige_permissao(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/admin/fiscal/nf-entrada')->assertForbidden();
    }
}
