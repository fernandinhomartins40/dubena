<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PIE R3-G-04 — cadastro de cliente em campo com número de endereço longo.
 *
 * `clientes.numero` é varchar(10), mas os dois cadastros do app do entregador
 * validavam `max:20`. No Postgres, "1234 fundos B" virava 500 (value too long);
 * pela fila offline o 5xx é transitório e o cadastro retentava para sempre. O
 * sqlite não valida tamanho de varchar, por isso nenhum teste pegava: aqui o
 * contrato é medido na VALIDAÇÃO (422), que vale nos dois bancos, e um guardião
 * compara a regra com a coluna declarada na migration.
 *
 * Payload literal de `cliente-novo.tsx` (VendaService.CadastrarCliente) e de
 * `missao.service.ts` (CadastrarCliente).
 */
class PieClienteEmCampoNumeroTest extends TestCase
{
    use RefreshDatabase;

    private User $entregador;

    protected function setUp(): void
    {
        parent::setUp();
        $empresa = Empresa::factory()->create();
        $this->entregador = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
    }

    public function test_cliente_novo_com_numero_acima_da_coluna_recebe_422_e_nao_500(): void
    {
        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/clientes', [
                'nome' => 'Cliente Numero Longo',
                'telefone' => '42999990000',
                'endereco' => 'Rua das Flores',
                'numero' => '1234 fundos B',
                'ponto_referencia' => 'portão azul',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('numero');
    }

    public function test_cliente_novo_com_numero_de_ate_10_continua_entrando(): void
    {
        // Guarda: o limite novo não pode barrar o caso comum.
        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/clientes', [
                'nome' => 'Cliente Numero Curto',
                'telefone' => '42999990001',
                'endereco' => 'Rua das Flores',
                'numero' => '1234-B fds',
            ])
            ->assertJsonMissingValidationErrors('numero');
    }

    public function test_cliente_da_missao_com_numero_acima_da_coluna_recebe_422(): void
    {
        $this->actingAs($this->entregador, 'sanctum')
            ->postJson('/api/app/v1/entregador/missao/clientes', [
                'nome' => 'Cliente Missao',
                'endereco' => 'Rua das Flores',
                'numero' => '1234 fundos B',
                'telefone' => '42999990002',
                'latitude' => -25.39,
                'longitude' => -51.46,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('numero');
    }

    /**
     * Guardião: o `max:` de `numero` nos cadastros do app não passa do tamanho
     * da coluna na migration que cria `clientes`. Falha se alguém voltar a 20.
     */
    public function test_regra_de_numero_do_app_cabe_na_coluna(): void
    {
        $migs = glob(database_path('migrations/*create_clientes*.php')) ?: [];
        $this->assertGreaterThan(0, count($migs), 'migration de clientes não encontrada');
        $this->assertMatchesRegularExpression("/string\\('numero',\\s*(\\d+)\\)/", (string) file_get_contents($migs[0]));
        preg_match("/string\\('numero',\\s*(\\d+)\\)/", (string) file_get_contents($migs[0]), $m);
        $coluna = (int) $m[1];

        $varridos = 0;
        foreach (['AppSolicitacaoController', 'AppMissaoController'] as $classe) {
            $fonte = (string) file_get_contents(app_path("Http/Controllers/Api/Mobile/{$classe}.php"));
            preg_match_all("/'numero'\\s*=>\\s*'[^']*max:(\\d+)/", $fonte, $regras);
            foreach ($regras[1] as $max) {
                $varridos++;
                $this->assertLessThanOrEqual($coluna, (int) $max, "{$classe}: numero max:{$max} > coluna {$coluna}");
            }
        }
        $this->assertGreaterThanOrEqual(2, $varridos);
    }
}
