<?php

namespace Tests\Feature;

use App\Domain\Tenant\AutomacaoPorEmpresa;
use App\Domain\Tenant\ConexaoDeOwner;
use App\Domain\Tenant\IdentidadeDeAutomacao;
use App\Models\Saas\TenantAccount;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A prova que o sqlite não dá: sob RLS de verdade, com a role do runtime, a
 * rotina agendada ENXERGA as linhas da empresa da vez — e só as do tenant dela.
 *
 * `AutomacaoPorEmpresaTest` prova o contrato (quem é processado e com que
 * envelope). Este prova que o banco concorda: que o envelope da identidade de
 * automação é o que as policies canônicas pedem.
 *
 * Sem `RefreshDatabase` nem transação: o runtime (`erp_app`) não é dono das
 * tabelas, e o cenário é gravado pela conexão de owner — que é separada e
 * commita por conta própria. As linhas ficam no banco de teste, com nomes
 * únicos; é o mesmo arranjo de `RlsCoberturaTest`.
 */
class AutomacaoSobRlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é específico do PostgreSQL; pulado em '.DB::connection()->getDriverName().'.');
        }
    }

    /**
     * Um tenant com uma empresa aprovada e um cliente, gravados como owner.
     *
     * @return array{tenant: int, empresa: int, cliente: int}
     */
    private function tenantComCliente(string $rotulo): array
    {
        $owner = DB::connection('pgsql_owner');
        $agora = now();
        $marca = $rotulo.' '.fake()->uuid();

        $grupo = $owner->table('grupos')->insertGetId([
            'descricao' => "Automacao {$marca}", 'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $empresa = $owner->table('empresas')->insertGetId([
            'grupo_id' => $grupo, 'razao_social' => "Empresa {$marca}", 'ativo' => true,
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $tenant = $owner->table('tenant_accounts')->insertGetId([
            'legal_name' => "Tenant {$marca}", 'status' => 'ACTIVE', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $owner->table('tenant_companies')->insert([
            'tenant_account_id' => $tenant, 'empresa_id' => $empresa, 'status' => 'APPROVED',
            'approved_at' => $agora, 'ownership_evidence_ref' => 'automacao-rls',
            'created_at' => $agora, 'updated_at' => $agora,
        ]);
        $cliente = $owner->table('clientes')->insertGetId([
            'empresa_id' => $empresa, 'grupo_id' => $grupo, 'tenant_account_id' => $tenant,
            'nome' => "Cliente {$marca}", 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        return ['tenant' => $tenant, 'empresa' => $empresa, 'cliente' => $cliente];
    }

    private function provisionar(int $tenantId): void
    {
        ConexaoDeOwner::executar(fn () => app(IdentidadeDeAutomacao::class)->provisionar(TenantAccount::findOrFail($tenantId)));
    }

    /**
     * O defeito, medido: pelo runtime e sem contexto, a tabela de empresas é
     * vazia. Era assim que os comandos agendados descobriam "por onde passar".
     */
    public function test_sem_contexto_o_runtime_nao_enxerga_empresa_nenhuma(): void
    {
        $a = $this->tenantComCliente('cego');

        $this->assertSame(0, DB::table('empresas')->where('id', $a['empresa'])->count());
        $this->assertSame(0, DB::table('clientes')->where('id', $a['cliente'])->count());
        // E existem: o owner as vê.
        $this->assertSame(1, DB::connection('pgsql_owner')->table('empresas')->where('id', $a['empresa'])->count());
    }

    public function test_dentro_da_automacao_o_runtime_le_e_grava_so_no_proprio_tenant(): void
    {
        $a = $this->tenantComCliente('A');
        $b = $this->tenantComCliente('B');
        $this->provisionar($a['tenant']);
        $this->provisionar($b['tenant']);

        $visto = [];
        $resultado = app(AutomacaoPorEmpresa::class)->paraCada(function (int $empresaId) use (&$visto, $a, $b) {
            if (! in_array($empresaId, [$a['empresa'], $b['empresa']], true)) {
                return null; // sobras de outros testes no mesmo banco
            }

            $alheio = $empresaId === $a['empresa'] ? $b : $a;

            $visto[$empresaId] = [
                'role' => DB::selectOne('select current_user as u')->u,
                'clientes' => DB::table('clientes')->whereIn('id', [$a['cliente'], $b['cliente']])->pluck('id')->all(),
                'empresas' => DB::table('empresas')->whereIn('id', [$a['empresa'], $b['empresa']])->pluck('id')->all(),
                // Gravar no próprio tenant funciona…
                'update_proprio' => DB::table('clientes')
                    ->where('id', $empresaId === $a['empresa'] ? $a['cliente'] : $b['cliente'])
                    ->update(['observacoes' => 'tocado pela automacao']),
                // …e no alheio o banco não entrega a linha.
                'update_alheio' => DB::table('clientes')->where('id', $alheio['cliente'])->update(['nome' => 'INVASAO']),
            ];

            return 1;
        });

        $this->assertArrayNotHasKey($a['empresa'], $resultado->falhas, json_encode($resultado->falhas));
        $this->assertArrayNotHasKey($b['empresa'], $resultado->falhas, json_encode($resultado->falhas));
        $this->assertEqualsCanonicalizing([$a['empresa'], $b['empresa']], array_keys($visto));

        foreach ([[$a, $b], [$b, $a]] as [$meu, $alheio]) {
            $v = $visto[$meu['empresa']];
            // É o runtime, não o owner: a RLS está valendo dentro do trabalho.
            $this->assertSame('erp_app', $v['role']);
            $this->assertSame([$meu['cliente']], $v['clientes']);
            $this->assertSame([$meu['empresa']], $v['empresas']);
            $this->assertSame(1, $v['update_proprio']);
            $this->assertSame(0, $v['update_alheio']);
        }

        // Conferido pelo owner: a escrita própria pegou, a alheia não.
        $owner = DB::connection('pgsql_owner');
        foreach ([$a, $b] as $t) {
            $linha = $owner->table('clientes')->where('id', $t['cliente'])->first(['nome', 'observacoes']);
            $this->assertSame('tocado pela automacao', $linha->observacoes);
            $this->assertNotSame('INVASAO', $linha->nome);
        }

        // Depois da passada o contexto some: o runtime volta a não ver nada.
        $this->assertSame(0, DB::table('clientes')->whereIn('id', [$a['cliente'], $b['cliente']])->count());
    }

    /** Sem identidade provisionada a empresa é pulada — e nada dela é lido. */
    public function test_empresa_sem_identidade_e_pulada_no_banco_real(): void
    {
        $sem = $this->tenantComCliente('sem identidade');

        $processadas = [];
        $resultado = app(AutomacaoPorEmpresa::class)->paraCada(function (int $empresaId) use (&$processadas) {
            $processadas[] = $empresaId;
        }, $sem['empresa']);

        $this->assertSame([], $processadas);
        $this->assertSame([$sem['empresa']], $resultado->semIdentidade);
    }

    /**
     * Um comando agendado de ponta a ponta: o PIX vencido de um tenant expira,
     * e o comando grava como runtime. Antes, ele achava zero cobranças.
     */
    public function test_pix_expirar_alcanca_a_cobranca_vencida_sob_rls(): void
    {
        $a = $this->tenantComCliente('pix');
        $this->provisionar($a['tenant']);
        $owner = DB::connection('pgsql_owner');
        $id = $owner->table('pix_cobrancas')->insertGetId([
            'empresa_id' => $a['empresa'], 'tenant_account_id' => $a['tenant'],
            'txid' => substr(str_replace('-', '', fake()->uuid()), 0, 32), 'valor' => 50,
            'situacao' => 'ATIVA', 'expira_em' => now()->subHour(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('pix:expirar')->assertSuccessful();

        $this->assertSame('EXPIRADA', $owner->table('pix_cobrancas')->where('id', $id)->value('situacao'));
    }
}
