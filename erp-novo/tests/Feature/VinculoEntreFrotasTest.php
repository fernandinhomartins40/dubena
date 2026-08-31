<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Frota\Veiculo as VeiculoFrota;
use App\Models\Monitora\Veiculo as VeiculoRastreado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3-09 — as duas frotas passam a se conhecer.
 *
 * O mesmo caminhão existe duas vezes: em `veiculos` (km, troca de óleo,
 * documentos) e em `monitora_veiculos` (rastreador, posições, cercas). Nada as
 * ligava — cada uma tem o seu `veiculo_id` apontando para si mesma, e a placa
 * era a única coisa em comum, sem ninguém conferir se batia.
 *
 * Duas consequências, e as duas doem:
 *
 *  - "onde está o caminhão que precisa trocar o óleo?" não tinha resposta: uma
 *    frota sabe o km, a outra sabe a posição;
 *  - a placa podia divergir por erro de digitação e nada acusava — o veículo
 *    simplesmente sumia de um dos lados.
 *
 * Este lote entrega o VÍNCULO, não a fusão. Fundir as tabelas alcançaria
 * `Veiculo` em 23 arquivos e as posições, que têm milhões de linhas.
 */
class VinculoEntreFrotasTest extends TestCase
{
    use RefreshDatabase;

    private function frota(Empresa $empresa, string $placa): VeiculoFrota
    {
        return VeiculoFrota::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'placa' => $placa,
        ]);
    }

    private function rastreado(Empresa $empresa, string $placa, ?int $frotaId = null): VeiculoRastreado
    {
        return VeiculoRastreado::query()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'placa' => $placa,
            'descricao' => 'Rastreado '.$placa,
            'veiculo_frota_id' => $frotaId,
            'ativo' => true,
        ]);
    }

    /** A pergunta que o vínculo torna respondível, dos dois lados. */
    public function test_o_vinculo_liga_os_dois_cadastros(): void
    {
        $empresa = Empresa::factory()->create();
        $frota = $this->frota($empresa, 'ABC1D23');
        $rastreado = $this->rastreado($empresa, 'ABC1D23', $frota->id);

        $this->assertSame($frota->id, $rastreado->frota->id);
        $this->assertSame($rastreado->id, $frota->fresh()->rastreamento->id);
    }

    /** Sem par, o vínculo fica nulo — e isso é uma informação, não um defeito. */
    public function test_rastreado_sem_par_na_frota_fica_sem_vinculo(): void
    {
        $empresa = Empresa::factory()->create();
        $rastreado = $this->rastreado($empresa, 'XYZ9K88');

        $this->assertNull($rastreado->veiculo_frota_id);
        $this->assertNull($rastreado->frota);
    }

    /**
     * Apagar o cadastro de frota NÃO apaga o histórico de posições.
     *
     * As posições são o registro de onde o veículo esteve — dado que se usa
     * para conferir entrega e jornada. Um `cascadeOnDelete` aqui destruiria
     * prova operacional por causa de uma limpeza de cadastro.
     */
    public function test_excluir_o_cadastro_de_frota_nao_apaga_o_rastreado(): void
    {
        $empresa = Empresa::factory()->create();
        $frota = $this->frota($empresa, 'ABC1D23');
        $rastreado = $this->rastreado($empresa, 'ABC1D23', $frota->id);

        $frota->delete();

        $sobrevivente = VeiculoRastreado::query()->find($rastreado->id);

        $this->assertNotNull($sobrevivente, 'o histórico de rastreamento não pode sumir com o cadastro');
        $this->assertNull($sobrevivente->veiculo_frota_id);
    }

    /** O vínculo não atravessa empresa: seria pior que não existir. */
    public function test_vinculo_e_por_empresa(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();

        $daA = $this->frota($empresaA, 'ABC1D23');
        $rastreadoB = $this->rastreado($empresaB, 'ABC1D23');

        // Mesma placa em empresas diferentes é possível e legítimo: são
        // veículos distintos. A conversão por placa não pode uni-los.
        $this->assertNull($rastreadoB->veiculo_frota_id);
        $this->assertNotSame($daA->empresa_id, $rastreadoB->empresa_id);
    }

    /**
     * O rastreador é um vínculo, não a identidade: trocar de aparelho não cria
     * um caminhão novo nem desfaz a ligação com a frota.
     */
    public function test_trocar_de_rastreador_preserva_o_vinculo(): void
    {
        $empresa = Empresa::factory()->create();
        $frota = $this->frota($empresa, 'ABC1D23');
        $rastreado = $this->rastreado($empresa, 'ABC1D23', $frota->id);

        $rastreado->update(['imei' => '111111111111111']);
        $rastreado->update(['imei' => '222222222222222']);

        $this->assertSame($frota->id, $rastreado->fresh()->veiculo_frota_id);
    }
}
