<?php

namespace Tests\Feature;

use App\Domain\Seguranca\LoginSeguranca;
use App\Models\Empresa;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2-07 — o lockout não pode cruzar tenants por NAT.
 *
 * O lockout conta falhas por e-mail E por IP. Por e-mail está certo: a conta é
 * a mesma pessoa em qualquer lugar. Por IP, num SaaS, é uma arma apontada para
 * o próprio cliente.
 *
 * Dois cenários reais em que isso derruba quem não fez nada:
 *
 *  - duas revendas atrás do mesmo CGNAT de operadora — comum em cidade pequena,
 *    que é exatamente o público de um ERP de distribuidora de GLP;
 *  - uma revenda com escritório atrás de NAT: cinco funcionários erram a senha
 *    de manhã e o sexto não entra, mesmo com a senha certa.
 *
 * No segundo caso o bloqueio é discutível mas defensável — é o mesmo tenant.
 * No primeiro, uma revenda tira a outra do ar sem saber que ela existe.
 *
 * A correção precisa preservar a defesa que o contador por IP dá (varredura de
 * muitos e-mails a partir de um IP) sem deixar o dano atravessar a fronteira.
 */
class LockoutEntreTenantsTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioEm(Empresa $empresa, string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'password' => bcrypt('senha-correta-123'),
        ]);
    }

    /** Falha registrada com o e-mail de alguém, a partir de um IP. */
    private function falharLogin(string $email, string $ip): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/login', ['email' => $email, 'password' => 'errada'])
            ->assertStatus(401);
    }

    /**
     * O vazamento: revenda A esgota o contador do IP compartilhado e revenda B
     * — que não errou nada — fica de fora.
     */
    public function test_falhas_de_um_tenant_nao_bloqueiam_outro_no_mesmo_ip(): void
    {
        $nat = '200.150.10.1';

        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $vitima = $this->usuarioEm($empresaA, "func{$i}@revenda-a.test");
            $this->falharLogin($vitima->email, $nat);
        }

        $this->usuarioEm($empresaB, 'gerente@revenda-b.test');

        $this->withServerVariables(['REMOTE_ADDR' => $nat])
            ->postJson('/api/login', [
                'email' => 'gerente@revenda-b.test',
                'password' => 'senha-correta-123',
            ])
            ->assertOk();
    }

    /**
     * A defesa que NÃO pode ser perdida ao corrigir o acima: varredura de muitos
     * e-mails do mesmo tenant, a partir de um IP, continua sendo barrada.
     */
    public function test_varredura_dentro_do_mesmo_tenant_continua_bloqueada(): void
    {
        $ip = '200.150.10.2';
        $empresa = Empresa::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $alvo = $this->usuarioEm($empresa, "alvo{$i}@revenda.test");
            $this->falharLogin($alvo->email, $ip);
        }

        $ultimo = $this->usuarioEm($empresa, 'ultimo@revenda.test');

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/login', ['email' => $ultimo->email, 'password' => 'senha-correta-123'])
            ->assertStatus(429);
    }

    /** Brute-force contra UMA conta, de vários IPs, continua barrado. */
    public function test_ataque_a_uma_conta_de_varios_ips_continua_bloqueado(): void
    {
        $empresa = Empresa::factory()->create();
        $vitima = $this->usuarioEm($empresa, 'vitima@revenda.test');

        for ($i = 1; $i <= 5; $i++) {
            $this->falharLogin($vitima->email, "200.150.20.{$i}");
        }

        $this->withServerVariables(['REMOTE_ADDR' => '200.150.20.99'])
            ->postJson('/api/login', ['email' => $vitima->email, 'password' => 'senha-correta-123'])
            ->assertStatus(429);
    }

    /**
     * E-mail que não existe em tenant nenhum não pode servir de alavanca para
     * bloquear um IP inteiro — senão o atacante derruba o IP de propósito.
     */
    public function test_email_inexistente_nao_derruba_o_ip_de_terceiros(): void
    {
        $ip = '200.150.30.1';
        $empresa = Empresa::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->falharLogin("ninguem{$i}@lugar-nenhum.test", $ip);
        }

        $legitimo = $this->usuarioEm($empresa, 'legitimo@revenda.test');

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/login', ['email' => $legitimo->email, 'password' => 'senha-correta-123'])
            ->assertOk();
    }

    /** A trilha continua registrando tudo: o escopo muda a decisão, não o log. */
    public function test_toda_tentativa_continua_registrada(): void
    {
        $empresa = Empresa::factory()->create();
        $user = $this->usuarioEm($empresa, 'auditado@revenda.test');

        $this->falharLogin($user->email, '200.150.40.1');

        $this->assertSame(
            1,
            LoginLog::query()->where('email', $user->email)->where('sucesso', false)->count(),
        );
    }

    /** O serviço decide o mesmo que o endpoint — sem isto, corrigir um deixa o outro. */
    public function test_servico_e_endpoint_concordam(): void
    {
        $nat = '200.150.50.1';
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            $this->falharLogin($this->usuarioEm($empresaA, "f{$i}@a.test")->email, $nat);
        }
        $b = $this->usuarioEm($empresaB, 'b@b.test');

        $this->assertFalse(app(LoginSeguranca::class)->bloqueado($b->email, $nat));
    }
}
