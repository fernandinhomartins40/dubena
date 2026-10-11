<?php

namespace Tests\Migration;

use App\Etl\Support\PreservaIdsDoLegado;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A recarga precisa funcionar DEPOIS que a titularidade foi aprovada — é o que
 * o cutover faz na última passada.
 *
 * Em homologação (2026-10-11) ela parou em `financeiros`: o ETL regravava a
 * linha sem `tenant_account_id`, e o gatilho de coerência do banco recusa
 * título sem tenant apontando para plano de contas que já tem. O ETL passa a
 * carimbar a chave quando — e só quando — o vínculo está APROVADO.
 */
class EtlCarimbaChaveDeTenantTest extends TestCase
{
    use RefreshDatabase;

    /** O trait, com o método de gravação exposto. */
    private function gravador(): object
    {
        return new class
        {
            use PreservaIdsDoLegado;

            public function gravar(string $tabela, array $linhas): int
            {
                return $this->gravarPreservandoId($tabela, $linhas);
            }
        };
    }

    /** A factory de empresa já nasce com vínculo; o cenário parte de "sem dono". */
    private function empresaSemDono(array $atributos = []): Empresa
    {
        $empresa = Empresa::factory()->create($atributos);
        DB::table('tenant_companies')->where('empresa_id', $empresa->id)->delete();
        DB::table('empresas')->where('id', $empresa->id)->update(['tenant_account_id' => null]);

        return $empresa;
    }

    private function tenantDono(Empresa $empresa): int
    {
        $tenant = DB::table('tenant_accounts')->insertGetId([
            'legal_name' => 'Tenant '.$empresa->id, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tenant_companies')->insert([
            'tenant_account_id' => $tenant, 'empresa_id' => $empresa->id, 'status' => 'APPROVED',
            'approved_at' => now(), 'ownership_evidence_ref' => 'teste', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $tenant;
    }

    private function cliente(int $id, Empresa $empresa): array
    {
        return ['id' => $id, 'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'nome' => "Cliente {$id}"];
    }

    public function test_linha_de_empresa_aprovada_ja_entra_com_a_chave_e_a_sem_dono_nao(): void
    {
        $aprovada = $this->empresaSemDono();
        $semDono = $this->empresaSemDono();
        $tenant = $this->tenantDono($aprovada);

        $this->gravador()->gravar('clientes', [$this->cliente(9001, $aprovada), $this->cliente(9002, $semDono)]);

        $this->assertSame($tenant, (int) DB::table('clientes')->where('id', 9001)->value('tenant_account_id'));
        $this->assertNull(DB::table('clientes')->where('id', 9002)->value('tenant_account_id'), 'titularidade não se inventa');
    }

    public function test_recarga_nao_apaga_a_chave_que_o_pos_carga_preencheu(): void
    {
        $empresa = $this->empresaSemDono();

        // Primeira carga: ainda não existe tenant, a linha entra sem chave.
        $this->gravador()->gravar('clientes', [$this->cliente(9001, $empresa)]);
        $this->assertNull(DB::table('clientes')->where('id', 9001)->value('tenant_account_id'));

        // Titularidade aprovada e pós-carga aplicado.
        $tenant = $this->tenantDono($empresa);
        DB::table('clientes')->where('id', 9001)->update(['tenant_account_id' => $tenant]);

        // Recarga (gravador novo: o mapa de titularidade é lido por execução).
        $this->gravador()->gravar('clientes', [$this->cliente(9001, $empresa)]);

        $this->assertSame($tenant, (int) DB::table('clientes')->where('id', 9001)->value('tenant_account_id'));
    }

    /**
     * Filho sem `empresa_id`/`grupo_id` herda a chave do pai. Foi aqui que a
     * recarga parou na segunda vez: `sorteio_numeros` regravado sem chave.
     */
    public function test_filho_sem_escopo_herda_a_chave_do_pai(): void
    {
        $empresa = $this->empresaSemDono();
        $tenant = $this->tenantDono($empresa);
        DB::table('tenant_legacy_group_scopes')->insert([
            'tenant_account_id' => $tenant, 'grupo_id' => $empresa->grupo_id, 'status' => 'APPROVED',
            'approved_at' => now(), 'evidence_ref' => 'teste', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $gravador = $this->gravador();
        $gravador->gravar('sorteios', [[
            'id' => 7001, 'grupo_id' => $empresa->grupo_id, 'descricao' => 'Sorteio', 'data_sorteio' => '2026-07-01',
        ]]);
        $this->assertSame($tenant, (int) DB::table('sorteios')->where('id', 7001)->value('tenant_account_id'));

        $gravador->gravar('sorteio_numeros', [['id' => 7101, 'sorteio_id' => 7001, 'numero' => 1, 'cliente_id' => null]]);

        $this->assertSame($tenant, (int) DB::table('sorteio_numeros')->where('id', 7101)->value('tenant_account_id'));
    }

    /** A chave da EMPRESA é a decisão de titularidade — o ETL nunca a deriva. */
    public function test_empresa_nao_ganha_chave_pelo_grupo(): void
    {
        $empresa = $this->empresaSemDono();
        $tenant = $this->tenantDono($empresa);
        DB::table('tenant_legacy_group_scopes')->insert([
            'tenant_account_id' => $tenant, 'grupo_id' => $empresa->grupo_id, 'status' => 'APPROVED',
            'approved_at' => now(), 'evidence_ref' => 'teste', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $outra = $this->empresaSemDono(['grupo_id' => $empresa->grupo_id]);

        $this->gravador()->gravar('empresas', [[
            'id' => $outra->id, 'grupo_id' => $outra->grupo_id, 'razao_social' => 'Regravada pelo ETL',
        ]]);

        $this->assertNull(DB::table('empresas')->where('id', $outra->id)->value('tenant_account_id'));
        $this->assertSame('Regravada pelo ETL', DB::table('empresas')->where('id', $outra->id)->value('razao_social'));
    }
}
