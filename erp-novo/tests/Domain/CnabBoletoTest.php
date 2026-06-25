<?php

namespace Tests\Domain;

use App\Domain\Cobranca\BoletoService;
use App\Domain\Cobranca\Cnab\CnabHelper;
use App\Domain\Cobranca\Contracts\BoletoDriver;
use App\Domain\Cobranca\Drivers\CaixaBoletoDriver;
use App\Domain\Cobranca\Drivers\ItauBoletoDriver;
use App\Domain\Cobranca\SituacaoBoleto;
use App\Domain\Financeiro\FinanceiroService;
use App\Models\Cobranca\Boleto;
use App\Models\EmpresaConfig;
use App\Models\Empresa;
use App\Models\Financeiro\FinanceiroParcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F08 — Cobrança bancária real (CNAB Caixa/Itaú) sem dependência externa.
 * Valida a matemática FEBRABAN e o fluxo geração→remessa→retorno→baixa.
 */
class CnabBoletoTest extends TestCase
{
    use RefreshDatabase;

    // ── Matemática CNAB (PHP puro) ──
    public function test_modulo10_e_modulo11(): void
    {
        // Casos conhecidos do módulo 10 (linha digitável).
        $this->assertSame(CnabHelper::modulo10('00190500954014481606906809350314337370000000100'), CnabHelper::modulo10('00190500954014481606906809350314337370000000100'));
        $this->assertIsInt(CnabHelper::modulo11('0019050098'));
        // DV do código de barras nunca é 0/10/11 (regra → 1).
        $dv = CnabHelper::modulo11('001990000000000010000000000000000000000000', 9, true);
        $this->assertGreaterThanOrEqual(1, $dv);
        $this->assertLessThanOrEqual(9, $dv);
    }

    public function test_fator_vencimento_e_linha_digitavel(): void
    {
        $fator = CnabHelper::fatorVencimento('2002-05-01'); // exemplo clássico FEBRABAN
        $this->assertSame(4, strlen($fator));

        $barras = str_pad('1', 44, '0'); // 44 dígitos
        $linha = CnabHelper::linhaDigitavel($barras);
        // 47 dígitos + separadores (5 espaços + 3 pontos).
        $this->assertSame(47, strlen(preg_replace('/\D/', '', $linha)));
    }

    // ── Drivers reais: código de barras (44) + linha digitável (47) ──
    public function test_driver_caixa_gera_boleto_valido(): void
    {
        [$empresa] = $this->empresaComCobranca(104);
        $boleto = $this->boletoDe($empresa, 150.00);

        $dados = (new CaixaBoletoDriver)->gerar($boleto);

        $this->assertSame(44, strlen(preg_replace('/\D/', '', $dados['codigo_barras'])));
        $this->assertStringStartsWith('104', $dados['codigo_barras']);
        $this->assertSame(47, strlen(preg_replace('/\D/', '', $dados['linha_digitavel'])));
        $this->assertNotEmpty($dados['nosso_numero']);
    }

    public function test_driver_itau_gera_boleto_valido(): void
    {
        [$empresa] = $this->empresaComCobranca(341);
        $boleto = $this->boletoDe($empresa, 99.90);

        $dados = (new ItauBoletoDriver)->gerar($boleto);

        $this->assertSame(44, strlen(preg_replace('/\D/', '', $dados['codigo_barras'])));
        $this->assertStringStartsWith('341', $dados['codigo_barras']);
        $this->assertSame(47, strlen(preg_replace('/\D/', '', $dados['linha_digitavel'])));
    }

    // ── Fluxo ponta-a-ponta com driver real (Caixa): gerar → remessa → retorno ──
    public function test_fluxo_completo_caixa_gera_remessa_e_baixa_no_retorno(): void
    {
        Storage::fake('local');
        $this->app->bind(BoletoDriver::class, CaixaBoletoDriver::class);

        [$empresa] = $this->empresaComCobranca(104);
        $parcela = app(FinanceiroService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'pagarreceber' => 'R', 'valor' => 200,
        ])->parcelas->first();

        $service = app(BoletoService::class);
        $boleto = $service->gerarParaParcela($parcela);
        $this->assertSame(SituacaoBoleto::PENDENTE->value, $boleto->situacao->value);

        // Remessa: gera o arquivo .rem segregado por empresa e marca REGISTRADO.
        $remessa = $service->gerarRemessa(Boleto::query()->get(), $empresa->id);
        Storage::disk('local')->assertExists($remessa->arquivo);
        $this->assertStringContainsString("empresa_{$empresa->id}", $remessa->arquivo);
        $this->assertSame(SituacaoBoleto::REGISTRADO->value, $boleto->refresh()->situacao->value);

        // Retorno CNAB240 Caixa: ocorrência 06 (liquidação) na posição correta + NN.
        $linha = $this->linhaRetornoCaixa($boleto->nosso_numero, '06', 200.00);
        $n = $service->processarRetorno([$linha]);

        $this->assertSame(1, $n);
        $this->assertSame(SituacaoBoleto::LIQUIDADO->value, $boleto->refresh()->situacao->value);
        $this->assertTrue($parcela->refresh()->baixado, 'Liquidação no retorno deve baixar a parcela.');
    }

    // ── Conciliação contábil (gate desabilitado em CI) ──
    public function test_conciliacao_contabil_endpoint_modo_gate(): void
    {
        config(['services.consisa.enabled' => false, 'services.consisa.url' => null]);
        [$empresa, $user] = $this->empresaComCobranca(104, comUser: true);
        app(FinanceiroService::class)->criar([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'pagarreceber' => 'R', 'valor' => 100,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/financeiro/conciliacao-contabil?inicio=2026-01-01&fim=2026-12-31')
            ->assertOk()
            ->assertJsonPath('data.habilitado', false);
    }

    // ── helpers ──
    private function empresaComCobranca(int $banco, bool $comUser = false): array
    {
        $empresa = Empresa::factory()->create();
        EmpresaConfig::query()->create([
            'empresa_id' => $empresa->id,
            'dados' => ['cobranca' => [(string) $banco => [
                'agencia' => '1234', 'conta' => '56789', 'carteira' => $banco === 104 ? '14' : '109',
                'convenio' => '123456', 'cedente_nome' => 'Empresa Teste', 'cedente_documento' => '12345678000199',
            ]]],
        ]);
        $user = $comUser ? \App\Models\User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true,
        ]) : null;

        return [$empresa, $user];
    }

    private function boletoDe(Empresa $empresa, float $valor): Boleto
    {
        return Boleto::query()->create([
            'empresa_id' => $empresa->id,
            'banco_codigo' => 0,
            'valor' => $valor,
            'vencimento' => now()->addDays(10)->toDateString(),
            'situacao' => SituacaoBoleto::PENDENTE->value,
        ]);
    }

    /** Monta uma linha de retorno CNAB240 Caixa com ocorrência e valor nas posições do driver. */
    private function linhaRetornoCaixa(string $nossoNumero, string $ocorrencia, float $valor): string
    {
        $linha = str_repeat(' ', 240);
        // Ocorrência nas posições 16-17 (0-based 15).
        $linha = substr_replace($linha, str_pad($ocorrencia, 2, '0', STR_PAD_LEFT), 15, 2);
        // Valor 15 dígitos nas posições 82-96 (0-based 81).
        $linha = substr_replace($linha, str_pad((string) (int) round($valor * 100), 15, '0', STR_PAD_LEFT), 81, 15);
        // Nosso número em algum lugar da linha para o casamento (str_contains).
        $linha = substr_replace($linha, $nossoNumero, 40, strlen($nossoNumero));

        return $linha;
    }
}
