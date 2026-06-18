<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * M1.2 (RBAC) — papéis/permissões spatie (tabela acl_roles) e a ponte
 * User::podeRecurso (RBAC + legado menuusers) usada pelas Policies do Filament.
 */
class RbacTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_seeder_cria_papeis_e_permissoes()
    {
        $this->assertGreaterThanOrEqual(6, Role::where('guard_name', 'web')->count());
        $this->assertNotNull(Role::findByName('Admin', 'web'));
        $this->assertTrue(Permission::where('name', 'cliente.view')->where('guard_name', 'web')->exists());
        // Admin tem todas as permissões.
        $this->assertTrue(Role::findByName('Admin', 'web')->permissions()->count() >= 1);
    }

    public function test_usuario_com_papel_admin_pode_tudo_via_can()
    {
        $u = User::find(1); // admin support=1 → papel Admin no seeder
        $this->assertTrue($u->hasRole('Admin') || (string) $u->support === '1');
        $this->assertTrue($u->podeRecurso('cliente', 'view'));
        $this->assertTrue($u->podeRecurso('pedido', 'create'));
    }

    public function test_podeRecurso_via_permissao_direta_rbac()
    {
        $u = new User();
        $u->name = 'RBAC Teste';
        $u->email = 'rbac.' . uniqid() . '@local.test';
        $u->password = bcrypt('secret123');
        $u->support = 0;
        $u->ativo = 1;
        $u->save();

        $this->assertFalse($u->podeRecurso('cliente', 'view'), 'sem permissão não deveria poder');

        $u->givePermissionTo('cliente.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($u->fresh()->podeRecurso('cliente', 'view'), 'com cliente.view deveria poder ver');
        $this->assertFalse($u->fresh()->podeRecurso('cliente', 'delete'), 'sem cliente.delete não deveria deletar');
    }

    public function test_papel_agrupa_permissoes()
    {
        $u = new User();
        $u->name = 'Papel Teste';
        $u->email = 'papel.' . uniqid() . '@local.test';
        $u->password = bcrypt('secret123');
        $u->support = 0;
        $u->ativo = 1;
        $u->save();

        $vendedor = Role::findByName('Vendedor', 'web');
        $vendedor->givePermissionTo('cliente.view');
        $u->assignRole('Vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($u->fresh()->podeRecurso('cliente', 'view'), 'papel Vendedor com cliente.view → pode ver');
    }
}
