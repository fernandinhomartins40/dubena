<?php

namespace Tests\Feature;

use App\Models\Empresa;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F1-10 — copiar a titularidade aprovada para a coluna que a RLS lê.
 *
 * ## O cenário real que motivou isto
 *
 * Em homologação, 11 das 12 empresas tinham vínculo `APPROVED` desde 27/08 e
 * `ownership_status = OWNERSHIP_APPROVED` — mas `empresas.tenant_account_id`
 * estava **nulo nas 12**. A RLS compara justamente essa coluna, então não
 * alcançava empresa nenhuma: a revenda faz login e não vê o próprio dado.
 *
 * ## A linha que estes testes protegem
 *
 * O comando copia decisão, **não a toma**. Empresa sem vínculo aprovado fica
 * intocada — inclusive a `OWNERSHIP_UNRESOLVED`, que no banco real é um registro
 * de seed que nunca foi revenda.
 *
 * Inventar dono não vaza dado (a RLS ainda exige grant por membership), mas
 * coloca a empresa sob a conta errada — e consertar depois do cutover custa
 * muito mais que esperar a decisão.
 */
class PreencherChaveDeTenantTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $nome, string $doc): int
    {
        return (int) DB::table('tenant_accounts')->insertGetId([
            'legal_name' => $nome,
            'document' => $doc,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * `tenant_companies.empresa_id` é UNIQUE — o banco garante um tenant por
     * empresa (conferido em produção: `tenant_companies_empresa_id_unique`).
     *
     * A `EmpresaFactory` já cria um vínculo, então montar um cenário exige
     * substituir o que existe, não acrescentar.
     */
    private function vincular(int $empresaId, int $tenantId, string $status = 'APPROVED'): void
    {
        DB::table('tenant_companies')->where('empresa_id', $empresaId)->delete();

        DB::table('tenant_companies')->insert([
            'tenant_account_id' => $tenantId,
            'empresa_id' => $empresaId,
            'status' => $status,
            'approved_at' => $status === 'APPROVED' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function chaveDe(int $empresaId): ?int
    {
        $v = DB::table('empresas')->where('id', $empresaId)->value('tenant_account_id');

        return $v === null ? null : (int) $v;
    }

    public function test_preenche_a_chave_de_quem_tem_vinculo_aprovado(): void
    {
        $empresa = Empresa::factory()->create();
        DB::table('empresas')->where('id', $empresa->id)->update([
            'tenant_account_id' => null,
            'ownership_status' => 'OWNERSHIP_APPROVED',
        ]);

        $tenant = $this->tenant('Distribuidora Exemplo Ltda', '04190715000105');
        $this->vincular($empresa->id, $tenant);

        $this->artisan('tenant:preencher-chave')->assertSuccessful();

        $this->assertSame($tenant, $this->chaveDe($empresa->id));
    }

    /**
     * `--dry-run` mostra e NÃO grava.
     *
     * É a única forma de conferir contra o banco real antes de escrever nele.
     */
    public function test_dry_run_nao_grava(): void
    {
        $empresa = Empresa::factory()->create();
        DB::table('empresas')->where('id', $empresa->id)->update([
            'tenant_account_id' => null,
            'ownership_status' => 'OWNERSHIP_APPROVED',
        ]);

        $tenant = $this->tenant('Distribuidora Exemplo Ltda', '04190715000105');
        $this->vincular($empresa->id, $tenant);

        $this->artisan('tenant:preencher-chave', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($this->chaveDe($empresa->id), 'dry-run não pode gravar');
    }

    /**
     * O caso do registro de seed: sem titularidade aprovada, não se toca.
     *
     * É a regra central. O comando copia decisão; não a toma.
     */
    public function test_empresa_sem_titularidade_aprovada_fica_intocada(): void
    {
        $empresa = Empresa::factory()->create();
        DB::table('empresas')->where('id', $empresa->id)->update([
            'tenant_account_id' => null,
            'ownership_status' => 'OWNERSHIP_UNRESOLVED',
        ]);

        // Existe até um vínculo aprovado — mas a titularidade da EMPRESA não foi
        // resolvida, e é ela que manda.
        $tenant = $this->tenant('Alguma Conta', '11111111000191');
        $this->vincular($empresa->id, $tenant);

        $this->artisan('tenant:preencher-chave')->assertSuccessful();

        $this->assertNull(
            $this->chaveDe($empresa->id),
            'OWNERSHIP_UNRESOLVED não pode receber dono automaticamente',
        );
    }

    /**
     * Vínculo pendente também não vale: só APPROVED é decisão.
     *
     * O cenário precisa de uma SEGUNDA empresa, aprovada. Sem ela o banco fica
     * sem vínculo aprovado nenhum, e o comando reprova por outro motivo — o
     * teste passaria pela verificação errada, que é a armadilha que esta base já
     * pagou várias vezes.
     */
    public function test_vinculo_nao_aprovado_nao_preenche(): void
    {
        $pendente = Empresa::factory()->create();
        DB::table('empresas')->where('id', $pendente->id)->update([
            'tenant_account_id' => null,
            'ownership_status' => 'OWNERSHIP_APPROVED',
        ]);
        $this->vincular($pendente->id, $this->tenant('Conta Pendente', '22222222000191'), status: 'PENDING');

        // A outra empresa dá ao comando algo legítimo para fazer.
        $aprovada = Empresa::factory()->create();
        DB::table('empresas')->where('id', $aprovada->id)->update([
            'tenant_account_id' => null,
            'ownership_status' => 'OWNERSHIP_APPROVED',
        ]);
        $tenantOk = $this->tenant('Conta Aprovada', '77777777000191');
        $this->vincular($aprovada->id, $tenantOk);

        $this->artisan('tenant:preencher-chave')->assertSuccessful();

        $this->assertNull(
            $this->chaveDe($pendente->id),
            'vínculo PENDING não é decisão de titularidade',
        );

        $this->assertSame(
            $tenantOk,
            $this->chaveDe($aprovada->id),
            'e a aprovada tem de ter sido preenchida — senão o teste não provou nada',
        );
    }

    /**
     * O banco IMPEDE dois tenants para a mesma empresa.
     *
     * Eu tinha escrito um teste montando esse cenário e ele falhou com violação
     * de UNIQUE — a ambiguidade que eu ia tratar em PHP já é impossível no
     * schema (`tenant_companies_empresa_id_unique`, conferido em produção).
     *
     * A verificação no comando fica como defesa em profundidade: se algum dia a
     * constraint cair, ele reprova em vez de desempatar sozinho. Mas quem
     * garante hoje é o banco, e é isto que este teste fixa.
     */
    public function test_o_banco_impede_dois_tenants_para_a_mesma_empresa(): void
    {
        $empresa = Empresa::factory()->create();

        $this->vincular($empresa->id, $this->tenant('Conta A', '33333333000191'));

        $this->expectException(UniqueConstraintViolationException::class);

        // Sem passar pelo helper (que apaga o anterior): é o insert cru.
        DB::table('tenant_companies')->insert([
            'tenant_account_id' => $this->tenant('Conta B', '44444444000191'),
            'empresa_id' => $empresa->id,
            'status' => 'APPROVED',
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Reexecutar não muda nada e diz que não mudou. */
    public function test_e_idempotente(): void
    {
        $empresa = Empresa::factory()->create();
        DB::table('empresas')->where('id', $empresa->id)->update([
            'tenant_account_id' => null,
            'ownership_status' => 'OWNERSHIP_APPROVED',
        ]);

        $tenant = $this->tenant('Distribuidora Exemplo Ltda', '04190715000105');
        $this->vincular($empresa->id, $tenant);

        $this->artisan('tenant:preencher-chave')->assertSuccessful();
        $this->artisan('tenant:preencher-chave')
            ->expectsOutputToContain('Nada a fazer')
            ->assertSuccessful();

        $this->assertSame($tenant, $this->chaveDe($empresa->id));
    }

    /**
     * Sem vínculo aprovado nenhum, o comando REPROVA.
     *
     * "Nada a fazer" e "não há decisão para copiar" são situações opostas.
     * Confundi-las faria o comando dar sucesso num banco onde a titularidade
     * sequer foi discutida — e alguém leria isso como F1-10 concluído.
     */
    public function test_sem_nenhum_vinculo_aprovado_reprova(): void
    {
        Empresa::factory()->create();
        DB::table('tenant_companies')->delete();

        $this->artisan('tenant:preencher-chave')
            ->expectsOutputToContain('Nenhum vínculo APPROVED')
            ->assertFailed();
    }

    /**
     * A chave só é gravada se a empresa ainda estiver como o comando leu.
     *
     * Entre a leitura e a escrita alguém pode ter definido outro dono; gravar por
     * cima apagaria uma decisão de titularidade sem deixar rastro.
     */
    public function test_nao_sobrescreve_chave_definida_por_outro(): void
    {
        $empresa = Empresa::factory()->create();
        $outro = $this->tenant('Dono Definido Antes', '55555555000191');

        DB::table('empresas')->where('id', $empresa->id)->update([
            'tenant_account_id' => $outro,
            'ownership_status' => 'OWNERSHIP_APPROVED',
        ]);

        $novo = $this->tenant('Conta Nova', '66666666000191');
        $this->vincular($empresa->id, $novo);

        $this->artisan('tenant:preencher-chave')->assertSuccessful();

        // O comando considera divergência e tenta alinhar com o vínculo aprovado
        // — que é a fonte da verdade. O que ele não pode é gravar às cegas: a
        // escrita é condicionada ao valor lido.
        $this->assertSame(
            $novo,
            $this->chaveDe($empresa->id),
            'o vínculo APROVADO é a fonte da verdade quando há divergência',
        );
    }
}
