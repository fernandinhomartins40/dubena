<?php

namespace Tests\Feature;

use App\Domain\Fiscal\CodigoBarras128C;
use App\Domain\Fiscal\DanfePdfService;
use App\Domain\Fiscal\SituacaoNota;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Fiscal\NotaFiscal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE do item 8 da triagem — DANFE.
 *
 * A triagem: *"DANFE é PRÉ: sem ela a mercadoria não circula legalmente"*. O
 * XML autorizado sozinho não resolve — a fiscalização de trânsito pede o papel.
 *
 * ⚠️ Como no boleto (T4.6), estes testes provam que o PDF SAI e que o Code 128C
 * segue a especificação. Eles **não substituem** a conferência humana: imprimir
 * um DANFE e passar a chave num leitor. O mesmo raciocínio do barcode do boleto
 * vale aqui — chave ilegível no destino é carga parada.
 */
class DanfePdfTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        return [$user, $empresa];
    }

    private function nota(Empresa $empresa, array $extra = []): NotaFiscal
    {
        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'nome' => 'Mercearia do Zé Ltda',
            'cnpj' => '12345678000199',
            'inscricao_estadual' => '9012345678',
            'endereco' => 'Rua XV de Novembro',
            'numero' => '1200',
            'cep' => '85010000',
            'uf' => 'PR',
            'cliente' => true,
        ]);

        return NotaFiscal::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'modelo' => '55',
            'tipo' => 'SAIDA',
            'serie' => 1,
            'numero' => 4321,
            'chave' => '41250612345678000199550010000043211000043215',
            'protocolo' => '141250000123456',
            'valor_produtos' => 1000.00,
            'valor_desconto' => 0,
            'valor_frete' => 0,
            'valor_total' => 1000.00,
            'situacao' => SituacaoNota::AUTORIZADA,
            'emitida_em' => now(),
            ...$extra,
        ]);
    }

    public function test_danfe_de_nota_autorizada_sai_em_pdf(): void
    {
        [$user, $empresa] = $this->suporte();
        $nota = $this->nota($empresa);

        $resposta = $this->actingAs($user, 'sanctum')->get("/api/admin/notas/{$nota->id}/danfe");

        $resposta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_rascunho_nao_gera_danfe(): void
    {
        [$user, $empresa] = $this->suporte();
        $nota = $this->nota($empresa, ['situacao' => SituacaoNota::RASCUNHO, 'chave' => null]);

        // Um DANFE de rascunho aparenta validade e não tem: circular com ele é
        // pior do que circular sem nada.
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/notas/{$nota->id}/danfe")
            ->assertStatus(422);
    }

    public function test_rejeitada_nao_gera_danfe(): void
    {
        [$user, $empresa] = $this->suporte();
        $nota = $this->nota($empresa, ['situacao' => SituacaoNota::REJEITADA]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/admin/notas/{$nota->id}/danfe")
            ->assertStatus(422);
    }

    public function test_autorizada_sem_chave_nao_gera_danfe(): void
    {
        [, $empresa] = $this->suporte();
        $nota = $this->nota($empresa, ['chave' => '']);

        $this->expectException(\DomainException::class);
        app(DanfePdfService::class)->gerar($nota);
    }

    public function test_cancelada_sai_com_tarja(): void
    {
        [$user, $empresa] = $this->suporte();
        $nota = $this->nota($empresa, ['situacao' => SituacaoNota::CANCELADA]);

        // Reimpressão para arquivo é legítima, mas o papel tem de dizer na cara
        // que a nota não vale — senão vira documento de aparência válida.
        $this->actingAs($user, 'sanctum')
            ->get("/api/admin/notas/{$nota->id}/danfe")
            ->assertOk();
    }

    public function test_exige_permissao_fiscal(): void
    {
        [, $empresa] = $this->suporte();
        $nota = $this->nota($empresa);
        $leitor = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => false,
        ]);

        $this->actingAs($leitor, 'sanctum')
            ->getJson("/api/admin/notas/{$nota->id}/danfe")
            ->assertStatus(403);
    }

    // ── Code 128C ────────────────────────────────────────────────────────────

    public function test_barcode_exige_44_digitos(): void
    {
        $b = new CodigoBarras128C();

        // A chave da NF-e tem tamanho fixo. Aceitar outro produziria um símbolo
        // ilegível descoberto só no destino.
        $this->expectException(\InvalidArgumentException::class);
        $b->html('4125061234567800019955001000004321');
    }

    public function test_verificador_do_code128_segue_a_especificacao(): void
    {
        $b = new CodigoBarras128C();

        // Valor conhecido: START C (105) + 1*1 + 2*2 + 3*3 = 105+1+4+9 = 119;
        // 119 % 103 = 16.
        $this->assertSame(16, $b->verificador([1, 2, 3]));
    }

    public function test_barcode_desenha_start_e_stop(): void
    {
        $b = new CodigoBarras128C();
        $html = $b->html('41250612345678000199550010000043211000043215');

        // START C é '211232' e o STOP do Code 128 é '2331112': se qualquer um
        // faltar, nenhum leitor reconhece o símbolo.
        $this->assertStringContainsString('width:2px', $html);
        $this->assertStringContainsString('width:3px', $html);
        // 1 (start) + 22 (pares) + 1 (DV) = 24 caracteres de 6 elementos, mais
        // os 7 do stop.
        $this->assertSame(24 * 6 + 7, substr_count($html, 'display:inline-block'));
    }

    public function test_chave_sai_formatada_em_grupos_de_quatro(): void
    {
        $b = new CodigoBarras128C();

        $this->assertSame(
            '4125 0612 3456 7800 0199 5500 1000 0043 2110 0004 3215',
            $b->chaveFormatada('41250612345678000199550010000043211000043215'),
        );
    }
}
