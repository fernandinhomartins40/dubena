<?php

namespace Tests\Feature;

use App\Domain\Shared\PermissaoCatalogo;
use App\Models\Empresa;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * GATE da FASE A1 — Enforcement central de autorização.
 *
 * Antes da A1 a autorização vivia só em `temPermissao()` chamado à mão em cada
 * controller (frágil, sem ponto único). A A1 introduz o PONTO ÚNICO: um Gate por
 * chave do catálogo (AuthServiceProvider), consumido pela trait
 * AutorizaPorPermissao (controllers) e pelo middleware `permissao:` (rotas).
 *
 * Estes testes fixam o contrato:
 *   1. Todo Gate delega à MESMA regra (`temPermissao`) — nega sem papel, libera
 *      com papel; sem mudança funcional.
 *   2. `support` faz bypass de QUALQUER ability (Gate::before).
 *   3. Toda chave do catálogo tem um Gate definido (cobertura total).
 *   4. Os controllers NÃO chamam mais `temPermissao()` direto — a autorização
 *      passa pelo ponto único (guarda anti-regressão estrutural).
 */
class EnforcementCentralTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_nega_sem_papel_e_libera_com_papel(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        // Sem papel → o Gate (que delega a temPermissao) nega.
        $this->assertTrue(Gate::forUser($user)->denies('produto.view'));

        // Concede a permissão via papel na empresa ativa.
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Operador']);
        $perm = Permission::firstOrCreate(['chave' => 'produto.view']);
        $role->permissions()->sync([$perm->id]);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        // Recarrega para limpar o cache de relações do usuário.
        $user = $user->fresh();

        $this->assertTrue(Gate::forUser($user)->allows('produto.view'));
        // Outra ability continua negada (não vaza permissão).
        $this->assertTrue(Gate::forUser($user)->denies('produto.delete'));
    }

    public function test_support_faz_bypass_de_qualquer_ability(): void
    {
        // Comportamento do modo LEGADO: com o enforcement ligado, `support`
        // sozinho não autoriza mais — quem autoriza é o break-glass (F2-05).
        config()->set('saas_transformation.enforcement.tenant_envelope', false);

        $empresa = Empresa::factory()->create();
        $support = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);
        // `support` não é fillable (T1.8).
        $support->forceFill(['support' => true])->save();
        $support = $support->fresh();

        // Sem nenhum papel, o support passa em qualquer Gate (Gate::before).
        $this->assertTrue(Gate::forUser($support)->allows('produto.view'));
        $this->assertTrue(Gate::forUser($support)->allows('financeiro.delete'));
        $this->assertTrue(Gate::forUser($support)->allows('grupo.view'));
    }

    public function test_todo_a_catalogo_tem_gate_definido(): void
    {
        $semGate = array_values(array_filter(
            PermissaoCatalogo::chaves(),
            fn (string $chave) => ! Gate::has($chave),
        ));

        $this->assertSame(
            [],
            $semGate,
            "Chaves do catálogo sem Gate definido (AuthServiceProvider):\n - ".implode("\n - ", $semGate),
        );
    }

    public function test_endpoint_real_aplica_enforcement_central(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        // Sem papel → 403 (a trait nos controllers usa o Gate central).
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/produtos')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Sem permissão.');

        // Com a permissão → passa (200).
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Operador']);
        $perm = Permission::firstOrCreate(['chave' => 'produto.view']);
        $role->permissions()->sync([$perm->id]);
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/admin/produtos')
            ->assertOk();
    }

    public function test_controllers_nao_chamam_tem_permissao_direto(): void
    {
        $dir = app_path('Http/Controllers');
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        $infratores = [];
        foreach ($it as $f) {
            if (! $f->isFile() || $f->getExtension() !== 'php') {
                continue;
            }
            // A trait é o ÚNICO ponto autorizado a falar com temPermissao().
            if ($f->getFilename() === 'AutorizaPorPermissao.php') {
                continue;
            }
            $src = (string) file_get_contents($f->getPathname());
            if (str_contains($src, '->temPermissao(')) {
                $infratores[] = $f->getFilename();
            }
        }

        $this->assertSame(
            [],
            $infratores,
            "Controllers chamando temPermissao() direto (deveriam usar a trait/Gate):\n - ".implode("\n - ", $infratores),
        );
    }
}
