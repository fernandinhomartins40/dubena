<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rede com filiais: uma empresa (dono de revenda) com vários estabelecimentos.
 *
 * O modelo é `grupo` = REDE e `empresa` = estabelecimento/tenant operacional.
 * Um usuário tem uma empresa padrão e pode receber acesso a outras (pivot
 * `empresa_user`); a troca é feita pelo header `X-Empresa-Id`.
 *
 * O que estes testes fixam — porque é o que separa "rede" de "vazamento":
 *  1. o dono da rede alterna entre as filiais e vê os dados de cada uma;
 *  2. quem é de uma filial só NÃO alcança as irmãs;
 *  3. e NÃO alcança empresa de OUTRA rede, mesmo tendo o vínculo — cada dono
 *     de revenda é um cliente distinto do SaaS.
 */
class RedeFiliaisTest extends TestCase
{
    use RefreshDatabase;

    private Grupo $rede;

    private Empresa $matriz;

    private Empresa $filial;

    private Grupo $outraRede;

    private Empresa $concorrente;

    protected function setUp(): void
    {
        parent::setUp();

        // Rede A: uma revenda com duas unidades.
        $this->rede = Grupo::factory()->create(['descricao' => 'Rede Dubena']);
        $this->matriz = Empresa::factory()->create([
            'grupo_id' => $this->rede->id, 'razao_social' => 'Dubena Matriz', 'matriz' => true,
        ]);
        $this->filial = Empresa::factory()->create([
            'grupo_id' => $this->rede->id, 'razao_social' => 'Dubena Filial Centro',
        ]);

        // Rede B: outro dono de revenda, sem relação com a A.
        $this->outraRede = Grupo::factory()->create(['descricao' => 'Rede Central Gás']);
        $this->concorrente = Empresa::factory()->create([
            'grupo_id' => $this->outraRede->id, 'razao_social' => 'Central Gás',
        ]);

        Cliente::withoutTenant()->create($this->cliente('Cliente da Matriz', $this->matriz));
        Cliente::withoutTenant()->create($this->cliente('Cliente da Filial', $this->filial));
        Cliente::withoutTenant()->create($this->cliente('Cliente do Concorrente', $this->concorrente));
    }

    private function cliente(string $nome, Empresa $empresa): array
    {
        return [
            'nome' => $nome,
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente' => true,
            'ativo' => true,
        ];
    }

    /**
     * Usuário da empresa informada, com papel de leitura de cliente.
     *
     * O papel é atribuído POR EMPRESA (pivot `role_user.empresa_id`): ter acesso
     * à filial não basta, é preciso ter permissão nela também. Por isso o papel
     * é vinculado à empresa padrão e a cada uma das adicionais.
     *
     * @return array{0: User, 1: string}
     */
    private function usuarioDe(Empresa $empresa, array $tambemEm = []): array
    {
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => false,
        ]);

        $verCliente = Permission::firstOrCreate(['chave' => 'cliente.view']);

        foreach ([$empresa, ...$tambemEm] as $alvo) {
            if ($alvo->id !== $empresa->id) {
                $user->empresas()->attach($alvo->id);
            }
            // Um papel POR EMPRESA: filiais da mesma rede compartilhariam o
            // mesmo papel do grupo, e o pivot (user_id, role_id) é único.
            $papel = Role::firstOrCreate(
                ['grupo_id' => $alvo->grupo_id, 'nome' => 'Leitura '.$alvo->id],
            );
            $papel->permissions()->syncWithoutDetaching([$verCliente->id]);
            $user->roles()->syncWithoutDetaching([$papel->id => ['empresa_id' => $alvo->id]]);
        }

        return [$user, $user->createToken('teste')->plainTextToken];
    }

    public function test_dono_da_rede_alterna_entre_as_filiais(): void
    {
        // Dono: empresa padrão = matriz, com acesso também à filial.
        [$dono, $token] = $this->usuarioDe($this->matriz, [$this->filial]);

        $this->assertTrue($dono->podeAcessarEmpresa($this->matriz->id));
        $this->assertTrue($dono->podeAcessarEmpresa($this->filial->id));

        // Sem header: vê a matriz.
        $this->withToken($token)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Cliente da Matriz')
            ->assertJsonCount(1, 'data');

        // Trocando para a filial: vê a filial, e SÓ ela.
        $this->withToken($token)
            ->withHeader('X-Empresa-Id', (string) $this->filial->id)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Cliente da Filial')
            ->assertJsonCount(1, 'data');
    }

    public function test_usuario_de_uma_filial_nao_alcanca_a_irma(): void
    {
        // Gerente da filial: sem vínculo com a matriz.
        [$gerente, $token] = $this->usuarioDe($this->filial);

        $this->assertFalse($gerente->podeAcessarEmpresa($this->matriz->id));

        // Pedir a matriz pelo header não deve trocar o tenant: continua na filial.
        $this->withToken($token)
            ->withHeader('X-Empresa-Id', (string) $this->matriz->id)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonPath('data.0.nome', 'Cliente da Filial')
            ->assertJsonCount(1, 'data');
    }

    public function test_rede_diferente_e_outro_cliente_do_saas(): void
    {
        [$dono, $token] = $this->usuarioDe($this->matriz, [$this->filial]);

        // O dono da rede A não alcança a revenda de outro dono.
        $this->assertFalse($dono->podeAcessarEmpresa($this->concorrente->id));

        $this->withToken($token)
            ->withHeader('X-Empresa-Id', (string) $this->concorrente->id)
            ->getJson('/api/admin/clientes')
            ->assertOk()
            ->assertJsonMissing(['nome' => 'Cliente do Concorrente']);
    }

    public function test_vinculo_cruzando_redes_nao_mistura_os_dados(): void
    {
        // Mesmo que alguém vincule (por engano) um usuário da rede A a uma
        // empresa da rede B, cada consulta continua vendo UMA empresa só —
        // o escopo é por empresa ativa, não pela soma dos vínculos.
        [, $token] = $this->usuarioDe($this->matriz, [$this->concorrente]);

        $resposta = $this->withToken($token)->getJson('/api/admin/clientes')->assertOk();
        $resposta->assertJsonCount(1, 'data');
        $resposta->assertJsonPath('data.0.nome', 'Cliente da Matriz');
    }
}
