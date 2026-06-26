<?php

namespace Tests\Feature;

use App\Domain\Shared\PermissaoCatalogo;
use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GATE da FASE C1 — RBAC fim-a-fim.
 *
 * A auditoria forense apontou: /me só devolvia `support`, então a SPA nunca
 * recebia roles/permissions e o RBAC só funcionava por bypass. Estes testes
 * fixam o contrato corrigido:
 *   1. /me devolve roles + permissions reais do usuário na empresa ativa;
 *   2. usuário não-support recebe APENAS as permissões do seu papel;
 *   3. support recebe o universo de permissões (bypass);
 *   4. toda chave usada nos controllers existe no catálogo (sem permissão órfã).
 */
class RbacContratoTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_devolve_roles_e_permissions_do_papel(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => false,
        ]);

        // Papel "Caixa" com 2 permissões, vinculado ao usuário NA empresa.
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Caixa']);
        $pView = Permission::create(['chave' => 'caixa.view']);
        $pEdit = Permission::create(['chave' => 'caixa.edit']);
        $role->permissions()->sync([$pView->id, $pEdit->id]);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        $resp = $this->actingAs($user, 'sanctum')->getJson('/api/me')->assertOk();

        $resp->assertJsonPath('user.support', false);
        $this->assertEqualsCanonicalizing(['Caixa'], $resp->json('user.roles'));
        $this->assertEqualsCanonicalizing(['caixa.view', 'caixa.edit'], $resp->json('user.permissions'));
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => false,
        ]);
        // Sem papel algum → sem permissões.

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/produtos')
            ->assertStatus(403);
    }

    public function test_support_recebe_universo_de_permissoes(): void
    {
        // Popula o catálogo (como o RbacSeeder faria).
        foreach (PermissaoCatalogo::chaves() as $chave) {
            Permission::firstOrCreate(['chave' => $chave]);
        }

        $empresa = Empresa::factory()->create();
        $support = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'support' => true,
        ]);

        $resp = $this->actingAs($support, 'sanctum')->getJson('/api/me')->assertOk();

        $this->assertEqualsCanonicalizing(
            PermissaoCatalogo::chaves(),
            $resp->json('user.permissions'),
            'Support deve receber TODAS as permissões do catálogo.',
        );
    }

    public function test_catalogo_cobre_toda_chave_usada_nos_controllers(): void
    {
        $catalogo = PermissaoCatalogo::chaves();
        $usadas = $this->chavesUsadasNosControllers();

        $orfas = array_values(array_diff($usadas, $catalogo));

        $this->assertSame(
            [],
            $orfas,
            "Permissões usadas nos controllers mas ausentes do catálogo:\n - ".implode("\n - ", $orfas),
        );
    }

    public function test_catalogo_cobre_toda_permissao_do_menu_da_spa(): void
    {
        $arquivo = base_path('frontend/src/layouts/AppShell.tsx');
        if (! is_file($arquivo)) {
            $this->markTestSkipped('AppShell.tsx não encontrado (frontend ausente neste contexto).');
        }

        $catalogo = PermissaoCatalogo::chaves();
        $src = (string) file_get_contents($arquivo);

        // Extrai `permission: 'modulo.acao'` dos itens de navegação.
        preg_match_all("/permission:\s*'([a-z]+\.[a-z_]+)'/", $src, $m);
        $doMenu = array_values(array_unique($m[1]));

        $orfas = array_values(array_diff($doMenu, $catalogo));

        $this->assertSame(
            [],
            $orfas,
            "Permissões no menu da SPA (AppShell.tsx) ausentes do catálogo:\n - ".implode("\n - ", $orfas),
        );
    }

    /**
     * Verbos de permissão conhecidos do sistema (parte ".acao" da chave).
     * Usado para distinguir uma PERMISSÃO ('cidade.view') de uma string de config
     * ('mail.mailers', 'services.x'): só é permissão se a ação for um verbo destes.
     * Mantém a detecção de órfãs robusta mesmo para MÓDULOS fora do catálogo.
     *
     * @var list<string>
     */
    private const VERBOS = [
        'view', 'create', 'edit', 'delete', 'config', 'preco', 'emitir',
        'cancelar', 'aprovar', 'reprovar', 'estornar', 'export', 'import',
        'imprimir', 'enviar', 'assinar', 'baixar', 'conciliar', 'fechar', 'reabrir',
    ];

    /**
     * Varre os controllers e extrai as chaves literais 'modulo.acao' que são
     * permissões — TODAS, inclusive de MÓDULO desconhecido (uma chave de módulo
     * fora do catálogo é justamente o tipo de órfã que queremos detectar; o filtro
     * antigo por "módulo conhecido" mascarava exatamente esse caso).
     *
     * Heurística anti-falso-positivo: só conta literais cuja AÇÃO é um verbo de
     * permissão conhecido — assim 'cidade.view' entra e 'mail.mailers' fica de fora.
     *
     * @return list<string>
     */
    private function chavesUsadasNosControllers(): array
    {
        $dir = app_path('Http/Controllers');
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        $chaves = [];
        foreach ($it as $f) {
            if (! $f->isFile() || $f->getExtension() !== 'php') {
                continue;
            }
            $src = (string) file_get_contents($f->getPathname());
            if (preg_match_all("/'([a-z]+)\.([a-z_]+)'/", $src, $m, PREG_SET_ORDER)) {
                foreach ($m as $par) {
                    [$modulo, $acao] = [$par[1], $par[2]];
                    if (in_array($acao, self::VERBOS, true)) {
                        $chaves[] = "{$modulo}.{$acao}";
                    }
                }
            }
        }

        return array_values(array_unique($chaves));
    }
}
