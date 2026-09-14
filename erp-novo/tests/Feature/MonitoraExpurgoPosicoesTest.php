<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\EmpresaConfig;
use App\Models\Monitora\Posicao;
use App\Models\Monitora\Veiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retenção do histórico de rastreamento (monitora:expurgar-posicoes).
 *
 * O que estes testes protegem não é "o comando roda": é que ele apague o que
 * deve e SÓ o que deve. Expurgo é irreversível — um filtro errado de empresa ou
 * de data apaga histórico alheio e ninguém descobre, porque o sintoma é
 * ausência de dado, não erro.
 */
class MonitoraExpurgoPosicoesTest extends TestCase
{
    use RefreshDatabase;

    private function veiculoDe(Empresa $empresa, string $imei): Veiculo
    {
        return Veiculo::create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'placa' => substr(strtoupper($imei), 0, 7),
            'descricao' => "Veículo {$imei}",
            'imei' => $imei,
            'ativo' => true,
        ]);
    }

    private function posicaoEm(Veiculo $veiculo, string $quando): Posicao
    {
        return Posicao::create([
            'veiculo_id' => $veiculo->id,
            'latitude' => -25.38,
            'longitude' => -51.45,
            'velocidade' => 10,
            'ignicao' => true,
            'registrado_em' => $quando,
        ]);
    }

    public function test_apaga_apenas_o_que_passou_da_janela_de_retencao(): void
    {
        $empresa = Empresa::factory()->create();
        $veiculo = $this->veiculoDe($empresa, 'IMEI-RET-1');

        $antiga = $this->posicaoEm($veiculo, now()->subDays(120)->toDateTimeString());
        $recente = $this->posicaoEm($veiculo, now()->subDays(10)->toDateTimeString());

        $this->artisan('monitora:expurgar-posicoes', ['--dias' => 90])->assertSuccessful();

        $this->assertDatabaseMissing('monitora_posicoes', ['id' => $antiga->id]);
        // A recente precisa sobreviver: um expurgo que leva tudo "funciona"
        // igual num teste que só conte o que sumiu.
        $this->assertDatabaseHas('monitora_posicoes', ['id' => $recente->id]);
    }

    public function test_nao_toca_no_historico_de_outra_empresa(): void
    {
        $minha = Empresa::factory()->create();
        $outra = Empresa::factory()->create();

        $meu = $this->posicaoEm($this->veiculoDe($minha, 'IMEI-A'), now()->subDays(200)->toDateTimeString());
        $alheio = $this->posicaoEm($this->veiculoDe($outra, 'IMEI-B'), now()->subDays(200)->toDateTimeString());

        $this->artisan('monitora:expurgar-posicoes', [
            '--dias' => 90,
            '--empresa' => $minha->id,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('monitora_posicoes', ['id' => $meu->id]);
        $this->assertDatabaseHas('monitora_posicoes', ['id' => $alheio->id]);
    }

    public function test_retencao_e_configuracao_da_empresa_e_nao_constante_do_codigo(): void
    {
        $empresa = Empresa::factory()->create();
        EmpresaConfig::create([
            'empresa_id' => $empresa->id,
            'dados' => ['monitora_retencao_dias' => 30],
        ]);

        $veiculo = $this->veiculoDe($empresa, 'IMEI-CFG');
        // 45 dias sobreviveria ao padrão de 90 e morre com os 30 configurados:
        // é exatamente essa diferença que prova que a config foi lida.
        $meio = $this->posicaoEm($veiculo, now()->subDays(45)->toDateTimeString());
        $nova = $this->posicaoEm($veiculo, now()->subDays(5)->toDateTimeString());

        $this->artisan('monitora:expurgar-posicoes', ['--empresa' => $empresa->id])->assertSuccessful();

        $this->assertDatabaseMissing('monitora_posicoes', ['id' => $meio->id]);
        $this->assertDatabaseHas('monitora_posicoes', ['id' => $nova->id]);
    }

    public function test_retencao_zero_guarda_para_sempre(): void
    {
        $empresa = Empresa::factory()->create();
        EmpresaConfig::create([
            'empresa_id' => $empresa->id,
            'dados' => ['monitora_retencao_dias' => 0],
        ]);

        $velha = $this->posicaoEm($this->veiculoDe($empresa, 'IMEI-ZERO'), now()->subYears(3)->toDateTimeString());

        $this->artisan('monitora:expurgar-posicoes', ['--empresa' => $empresa->id])->assertSuccessful();

        $this->assertDatabaseHas('monitora_posicoes', ['id' => $velha->id]);
    }

    public function test_dry_run_nao_apaga_nada(): void
    {
        $empresa = Empresa::factory()->create();
        $velha = $this->posicaoEm($this->veiculoDe($empresa, 'IMEI-DRY'), now()->subDays(300)->toDateTimeString());

        $this->artisan('monitora:expurgar-posicoes', ['--dias' => 90, '--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('monitora_posicoes', ['id' => $velha->id]);
    }
}
