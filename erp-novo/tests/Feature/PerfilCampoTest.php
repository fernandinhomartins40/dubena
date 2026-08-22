<?php

namespace Tests\Feature;

use App\Domain\Rh\VinculoColaborador;
use App\Models\Empresa;
use App\Models\Rh\Colaborador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * F1/F6 — um app, três perfis.
 *
 * O papel do token sai do VÍNCULO do colaborador (antes era fixo em
 * `role:entregador`, AppAuthController:119). É o que permite unificar os dois
 * apps legados num só com níveis de acesso, em vez de manter três aplicativos.
 */
class PerfilCampoTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->empresa = Empresa::factory()->create();
    }

    private function usuarioCom(?VinculoColaborador $vinculo, string $email): User
    {
        $user = User::factory()->create([
            'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
            'email' => $email, 'password' => Hash::make('segredo123'),
        ]);

        if ($vinculo !== null) {
            Colaborador::factory()->create([
                'empresa_id' => $this->empresa->id, 'grupo_id' => $this->empresa->grupo_id,
                'user_id' => $user->id, 'vinculo' => $vinculo->value,
            ]);
        }

        return $user;
    }

    private function login(string $email): array
    {
        // device_id derivado do e-mail: `app_devices` tem chave por device_id, e
        // reusar o mesmo entre usuários faz um login sobrescrever o registro do
        // outro dentro do mesmo teste.
        return $this->postJson('/api/app/v1/login', [
            'email' => $email, 'password' => 'segredo123',
            'device_id' => 'dev-'.md5($email),
        ])->assertOk()->json();
    }

    public function test_funcionario_recebe_apenas_papel_de_entregador(): void
    {
        $this->usuarioCom(VinculoColaborador::FUNCIONARIO, 'clt@teste.com');
        $r = $this->login('clt@teste.com');

        $this->assertSame('funcionario', $r['user']['vinculo']);
        $this->assertSame('entregador', $r['user']['papel']);
    }

    public function test_franqueado_recebe_papel_proprio(): void
    {
        $this->usuarioCom(VinculoColaborador::FRANQUEADO, 'franq@teste.com');
        $r = $this->login('franq@teste.com');

        $this->assertSame('franqueado', $r['user']['papel']);
    }

    /**
     * FAIL-CLOSED: sem cadastro de colaborador, o app de campo RECUSA o login.
     *
     * Antes caia em FUNCIONARIO por default — quem tinha so conta de cliente
     * recebia papel de entregador ao logar aqui. Presumir papel por ausencia
     * num ponto de identidade e o oposto do resto do sistema ("sem credencial,
     * nao autentica").
     */
    public function test_sem_colaborador_o_app_de_campo_recusa_o_login(): void
    {
        $this->usuarioCom(null, 'semvinculo@teste.com');

        $this->postJson('/api/app/v1/login', [
            'email' => 'semvinculo@teste.com', 'password' => 'segredo123',
            'device_id' => 'dev-sem-vinculo',
        ])->assertStatus(403);
    }

    /** Colaborador DESATIVADO tambem perde o acesso ao app de campo. */
    public function test_colaborador_desativado_nao_entra_no_app_de_campo(): void
    {
        $user = $this->usuarioCom(VinculoColaborador::FUNCIONARIO, 'desativado@teste.com');
        Colaborador::query()->where('user_id', $user->id)->update(['ativo' => false]);

        $this->postJson('/api/app/v1/login', [
            'email' => 'desativado@teste.com', 'password' => 'segredo123',
            'device_id' => 'dev-desativado',
        ])->assertStatus(403);
    }

    public function test_industrial_alcanca_a_rota_fiscal(): void
    {
        $this->usuarioCom(VinculoColaborador::INDUSTRIAL, 'ind@teste.com');

        // 422 = passou pelo papel e caiu na validação (payload vazio de propósito).
        $token = $this->login('ind@teste.com')['token'];
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/app/v1/entregador/fiscal/emitir', [])
            ->assertStatus(422);
    }

    public function test_franqueado_nao_emite_nota(): void
    {
        $this->usuarioCom(VinculoColaborador::FRANQUEADO, 'fr2@teste.com');

        // Payload inválido de propósito: interessa o STATUS de autorização, não a
        // emissão em si — 403 (barrado pelo papel) x 422 (passou, dados ruins).
        //
        // O franqueado vem PRIMEIRO: o usuário autenticado persiste entre
        // requests do mesmo teste, e um login bem-sucedido antes deixaria o
        // resolvedor devolvendo o usuário anterior em vez do token do header.
        $tokenFranq = $this->login('fr2@teste.com')['token'];
        $this->withHeader('Authorization', 'Bearer '.$tokenFranq)
            ->postJson('/api/app/v1/entregador/fiscal/emitir', [])
            ->assertStatus(403);
    }

    public function test_regras_do_vinculo(): void
    {
        // O enum concentra o que cada vínculo pode — em vez de espalhar `if` pelo
        // código e pelo app.
        $this->assertTrue(VinculoColaborador::FUNCIONARIO->entraEmFolha());
        $this->assertFalse(VinculoColaborador::FRANQUEADO->entraEmFolha());

        $this->assertFalse(VinculoColaborador::FUNCIONARIO->podeSolicitarDesconto());
        $this->assertTrue(VinculoColaborador::FRANQUEADO->podeSolicitarDesconto());

        $this->assertTrue(VinculoColaborador::INDUSTRIAL->emiteNotaEmCampo());
        $this->assertFalse(VinculoColaborador::FRANQUEADO->emiteNotaEmCampo());
    }
}
