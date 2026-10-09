<?php

namespace Tests\Migration;

use App\Etl\Migrators\UsersMigrator;
use App\Etl\Support\MigrationContext;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Quem NÃO entrava no legado não pode passar a entrar no sistema novo.
 *
 * O login do legado (`AuthController::handleLogin`) envia `ativo=1` num campo
 * escondido e o `Auth::attempt` o exige: só `ativo = 1` autentica. No dump real
 * (12/08/2026), 20 dos 74 usuários têm `ativo` NULO — gente desligada, que não
 * entrava. A migração lia `ativo ?? true` e os REATIVAVA: acesso de volta para
 * quem tinha sido bloqueado. Achado ao reimplantar a homologação em 2026-10-09.
 */
class UsersAtivoLegadoTest extends TestCase
{
    use RefreshDatabase;

    private string $legadoConn = 'legado_teste';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set("database.connections.{$this->legadoConn}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::connection($this->legadoConn)->getPdo();
    }

    public function test_so_ativo_igual_a_1_no_legado_vira_usuario_ativo(): void
    {
        $empresa = Empresa::factory()->create();
        $leg = DB::connection($this->legadoConn);
        // Colunas do schema real de CTRL2QTI.USERS que o migrador lê.
        $leg->statement('create table users (id integer, email text, password text, name text, empresa_id integer, ativo text, support text, tipo_id integer, created_at text)');
        $hash = bcrypt('qualquer');
        $leg->table('users')->insert([
            ['id' => 9001, 'email' => 'ativo1', 'password' => $hash, 'name' => 'Ativo', 'empresa_id' => $empresa->id, 'ativo' => '1', 'support' => '0', 'tipo_id' => 1],
            ['id' => 9002, 'email' => 'nulo', 'password' => $hash, 'name' => 'Desligado', 'empresa_id' => $empresa->id, 'ativo' => null, 'support' => null, 'tipo_id' => 1],
            ['id' => 9003, 'email' => 'zero', 'password' => $hash, 'name' => 'Inativo', 'empresa_id' => $empresa->id, 'ativo' => '0', 'support' => '0', 'tipo_id' => 1],
        ]);

        (new UsersMigrator)->migrar(new MigrationContext(conexaoLegado: $this->legadoConn));

        $this->assertTrue((bool) User::query()->find(9001)?->ativo, 'ativo=1 no legado deve seguir ativo');
        $this->assertFalse((bool) User::query()->find(9002)?->ativo, 'ativo NULO não entrava no legado — não pode ser reativado');
        $this->assertFalse((bool) User::query()->find(9003)?->ativo);
    }
}
