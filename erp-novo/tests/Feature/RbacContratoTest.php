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

    /**
     * Varre os controllers e extrai as chaves literais 'modulo.acao' passadas a
     * temPermissao()/autorizar().
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
            // 'modulo.acao' como literal (em autorizar/temPermissao ou strings simples).
            if (preg_match_all("/'([a-z]+\.[a-z_]+)'/", $src, $m)) {
                foreach ($m[1] as $chave) {
                    // Só pares que parecem permissão (módulo conhecido do catálogo).
                    $modulo = explode('.', $chave)[0];
                    if (array_key_exists($modulo, PermissaoCatalogo::MODULOS)) {
                        $chaves[] = $chave;
                    }
                }
            }
        }

        return array_values(array_unique($chaves));
    }
}
