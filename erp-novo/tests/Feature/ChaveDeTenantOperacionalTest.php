<?php

namespace Tests\Feature;

use App\Domain\Tenant\ChaveDeTenantOperacional;
use App\Domain\Tenant\ConexaoDeOwner;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/**
 * O dado que o ETL grava entra SEM chave de tenant — e sem ela a RLS o esconde
 * de todo mundo, inclusive do dono. Aconteceu na carga real de 2026-10-11: login
 * 200, zero clientes.
 *
 * Só em PostgreSQL: a chave só importa porque a policy a compara. Mesmo arranjo
 * de `AutomacaoSobRlsTest` — cenário gravado como owner, com nomes únicos, sem
 * transação de teste (a conexão de owner é separada e commita sozinha).
 */
class ChaveDeTenantOperacionalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('A chave de tenant operacional é específica do PostgreSQL; pulado em '.DB::connection()->getDriverName().'.');
        }
    }

    /**
     * Empresa com um cliente gravado do jeito que o ETL grava: sem chave.
     *
     * @return array{tenant: int, empresa: int, cliente: int}
     */
    private function empresaComClienteSemChave(string $rotulo, ?string $statusDoVinculo): array
    {
        $owner = DB::connection('pgsql_owner');
        $agora = now();
        $marca = $rotulo.' '.fake()->uuid();

        $grupo = $owner->table('grupos')->insertGetId([
            'descricao' => "Chave {$marca}", 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $empresa = $owner->table('empresas')->insertGetId([
            'grupo_id' => $grupo, 'razao_social' => "Empresa {$marca}", 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $tenant = $owner->table('tenant_accounts')->insertGetId([
            'legal_name' => "Tenant {$marca}", 'status' => 'ACTIVE', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        if ($statusDoVinculo === 'APPROVED') {
            $owner->table('tenant_companies')->insert([
                'tenant_account_id' => $tenant, 'empresa_id' => $empresa, 'status' => 'APPROVED',
                'approved_at' => $agora, 'ownership_evidence_ref' => 'chave-operacional',
                'created_at' => $agora, 'updated_at' => $agora,
            ]);
        }
        $cliente = $owner->table('clientes')->insertGetId([
            'empresa_id' => $empresa, 'grupo_id' => $grupo, 'tenant_account_id' => null,
            'nome' => "Cliente {$marca}", 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        return ['tenant' => $tenant, 'empresa' => $empresa, 'cliente' => $cliente];
    }

    private function chaveDoCliente(int $id): ?int
    {
        $valor = DB::connection('pgsql_owner')->table('clientes')->where('id', $id)->value('tenant_account_id');

        return $valor === null ? null : (int) $valor;
    }

    public function test_preenche_pelo_vinculo_aprovado_e_deixa_sem_chave_quem_nao_tem_dono(): void
    {
        $aprovada = $this->empresaComClienteSemChave('aprovada', 'APPROVED');
        $semDono = $this->empresaComClienteSemChave('sem-dono', null);

        $servico = app(ChaveDeTenantOperacional::class);

        // Guardião de varredura: se a lista de tabelas vier curta, o resto do
        // teste passaria sem ter conferido quase nada.
        $tabelas = ConexaoDeOwner::executar(fn () => $servico->tabelas());
        $this->assertGreaterThan(30, count($tabelas));
        $this->assertContains('clientes', $tabelas);
        $this->assertContains('pedidos', $tabelas);
        $this->assertNotContains('login_logs', $tabelas);

        $antes = ConexaoDeOwner::executar(fn () => $servico->preview());
        $this->assertGreaterThanOrEqual(2, $antes['clientes']['sem_chave']);
        $this->assertGreaterThanOrEqual(1, $antes['clientes']['preenchiveis']);
        $this->assertGreaterThanOrEqual(1, $antes['clientes']['sem_vinculo']);
        // O preview não grava.
        $this->assertNull($this->chaveDoCliente($aprovada['cliente']));

        $gravadas = ConexaoDeOwner::executar(fn () => $servico->aplicar());

        $this->assertGreaterThanOrEqual(1, $gravadas['clientes'] ?? 0);
        $this->assertSame($aprovada['tenant'], $this->chaveDoCliente($aprovada['cliente']));
        // Titularidade não se inventa: sem vínculo aprovado, segue sem chave.
        $this->assertNull($this->chaveDoCliente($semDono['cliente']));

        // Idempotente: a segunda passada não tem mais o que preencher ali.
        $denovo = ConexaoDeOwner::executar(fn () => $servico->preview());
        $this->assertSame($denovo['clientes']['sem_chave'], $denovo['clientes']['sem_vinculo']);
    }

    public function test_recusa_quando_ha_linha_sob_o_tenant_errado(): void
    {
        $a = $this->empresaComClienteSemChave('certa', 'APPROVED');
        $b = $this->empresaComClienteSemChave('outra', 'APPROVED');
        $owner = DB::connection('pgsql_owner');

        // Cliente da empresa A carimbado com o tenant de B.
        $owner->table('clientes')->where('id', $a['cliente'])->update(['tenant_account_id' => $b['tenant']]);

        try {
            ConexaoDeOwner::executar(fn () => app(ChaveDeTenantOperacional::class)->aplicar());
            $this->fail('Deveria recusar: há chave divergente do vínculo aprovado.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('clientes', $e->getMessage());
            // Recusou ANTES de gravar: o cliente de B continua sem chave.
            $this->assertNull($this->chaveDoCliente($b['cliente']));
        } finally {
            // O banco de teste é compartilhado entre execuções: a divergência
            // plantada não pode ficar para envenenar a próxima.
            $owner->table('clientes')->where('id', $a['cliente'])->update(['tenant_account_id' => $a['tenant']]);
        }
    }
}
