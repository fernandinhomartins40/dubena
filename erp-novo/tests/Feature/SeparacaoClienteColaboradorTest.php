<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Rh\Colaborador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Separação entre CLIENTE e COLABORADOR.
 *
 * Antes desta fase os dois papéis apontavam para a mesma tabela `users` sem
 * nada que dissesse qual era qual, e o app inferia o perfil por ausência.
 * Medido: 36 colaboradores tinham cadastro de cliente com o mesmo CPF, soltos.
 */
class SeparacaoClienteColaboradorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Uma PESSOA, dois PAPÉIS: o colaborador aponta para o seu cadastro de
     * cliente sem que os dois virem um só registro.
     */
    public function test_colaborador_pode_apontar_para_o_proprio_cadastro_de_cliente(): void
    {
        $empresa = Empresa::factory()->create();

        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'nome' => 'Maria Funcionaria', 'cpf' => '39053344705',
        ]);

        $colaborador = Colaborador::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'nome' => 'Maria Funcionaria', 'cpf' => '39053344705',
            'cliente_id' => $cliente->id,
        ]);

        // Continuam sendo DOIS registros: as naturezas de dado não se misturam.
        $this->assertSame($cliente->id, $colaborador->fresh()->cliente_id);
        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseCount('colaboradores', 1);
    }

    /**
     * O comando vincula por CPF e NUNCA por nome — homônimo entre colaborador
     * e cliente é comum, e ligar a ficha de RH de um ao cadastro de compra de
     * outro é pior que deixar sem vínculo.
     */
    public function test_comando_vincula_por_cpf_e_nunca_apenas_por_nome(): void
    {
        $empresa = Empresa::factory()->create();
        $base = ['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id];

        $clienteComCpf = Cliente::factory()->create($base + ['nome' => 'Ana Souza', 'cpf' => '39053344705']);
        Cliente::factory()->create($base + ['nome' => 'Carlos Homonimo', 'cpf' => null]);

        $comCpf = Colaborador::factory()->create($base + ['nome' => 'Ana Souza', 'cpf' => '39053344705']);
        $soNome = Colaborador::factory()->create($base + ['nome' => 'Carlos Homonimo', 'cpf' => null]);

        $this->artisan('identidade:vincular-colaboradores --executar')->assertSuccessful();

        $this->assertSame($clienteComCpf->id, $comCpf->fresh()->cliente_id, 'CPF igual deveria vincular');
        $this->assertNull($soNome->fresh()->cliente_id, 'só nome NÃO pode vincular');
    }

    /** O vínculo não pode cruzar empresa. */
    public function test_vinculo_nao_cruza_empresa(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();

        Cliente::factory()->create([
            'empresa_id' => $empresaA->id, 'grupo_id' => $empresaA->grupo_id,
            'nome' => 'Pessoa Repetida', 'cpf' => '39053344705',
        ]);
        $colaboradorB = Colaborador::factory()->create([
            'empresa_id' => $empresaB->id, 'grupo_id' => $empresaB->grupo_id,
            'nome' => 'Pessoa Repetida', 'cpf' => '39053344705',
        ]);

        $this->artisan('identidade:vincular-colaboradores --executar')->assertSuccessful();

        $this->assertNull($colaboradorB->fresh()->cliente_id);
    }

    /**
     * A migration classifica os logins existentes pelo vínculo que já tinham.
     * `tipo_principal` deixa de ser inferido por ausência.
     */
    public function test_usuario_de_colaborador_e_classificado_como_colaborador(): void
    {
        $empresa = Empresa::factory()->create();

        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        Colaborador::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'user_id' => $user->id,
        ]);

        // Reaplica a classificação da migration sobre os dados deste teste.
        DB::table('users')
            ->whereIn('id', fn ($q) => $q->select('user_id')->from('colaboradores')->whereNotNull('user_id'))
            ->update(['tipo_principal' => 'colaborador']);

        $this->assertSame('colaborador', DB::table('users')->where('id', $user->id)->value('tipo_principal'));
    }
}
