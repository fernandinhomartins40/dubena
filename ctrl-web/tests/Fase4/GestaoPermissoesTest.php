<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\User;
use App\Menu;
use App\Menuuser;
use App\Filament\Resources\UserResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * FASE 4 Bloco A — gestão de permissões (menuusers) na UI Filament.
 *
 * A UI grava/edita linhas em `menuusers` (RelationManager). Estes testes provam
 * que essa MESMA tabela é a fonte de verdade lida por User::podeNoMenu (e, por
 * tabela, pelo AuthorizeCustom): conceder/remover reflete na autorização.
 */
class GestaoPermissoesTest extends TestCase
{
    use DatabaseTransactions;

    private function adminLogado(): User
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->post('/handleLogin', [
            'email'    => env('ADMIN_SEED_EMAIL', 'admin'),
            'password' => env('ADMIN_SEED_PASSWORD', 'admin1234'),
            'ativo'    => 1,
        ]);
        return \Auth::user();
    }

    /** O RelationManager de permissões está registrado no UserResource. */
    public function test_relation_manager_registrado()
    {
        $this->assertContains(
            \App\Filament\Resources\UserResource\RelationManagers\MenuuserRelationManager::class,
            UserResource::getRelations()
        );
    }

    /**
     * Conceder uma permissão (linha em menuusers, como a UI faz) torna o
     * podeNoMenu verdadeiro para aquela rota/ação; removê-la, falso.
     */
    public function test_conceder_e_remover_permissao_reflete_em_podenomenu()
    {
        $admin = $this->adminLogado();

        // Usuário de teste SEM nenhuma permissão e SEM suporte.
        $u = new User();
        $u->name = 'Perm Teste';
        $u->email = 'perm.' . uniqid() . '@local.test';
        $u->password = bcrypt('secret123');
        $u->empresa_id = $admin->empresa_id;
        $u->support = 0;
        $u->ativo = 1;
        $u->save();

        $menu = Menu::whereNotNull('descricao')->where('descricao', 'cidade.index')->first();
        $this->assertNotNull($menu, 'menu cidade.index deveria existir (seed)');

        $this->assertFalse(
            $u->podeNoMenu('cidade.index', 'visualizar'),
            'sem menuuser, não deveria poder'
        );

        // Concede (o que o RelationManager grava).
        $mu = new Menuuser();
        $mu->user_id = $u->id;
        $mu->empresa_id = $u->empresa_id;
        $mu->menu_id = $menu->id;
        $mu->visualizar = 1;
        $mu->criar = 0;
        $mu->editar = 0;
        $mu->deletar = 0;
        $mu->baixar = 0;
        $mu->alerta = 0;
        $mu->save();

        $this->assertTrue(
            $u->fresh()->podeNoMenu('cidade.index', 'visualizar'),
            'com menuuser visualizar=1, deveria poder ver'
        );
        $this->assertFalse(
            $u->fresh()->podeNoMenu('cidade.index', 'editar'),
            'editar=0 não deveria permitir editar'
        );

        // Remove (o que o DeleteAction do RelationManager faz).
        $mu->delete();
        $this->assertFalse(
            $u->fresh()->podeNoMenu('cidade.index', 'visualizar'),
            'após remover, não deveria mais poder'
        );
    }
}
