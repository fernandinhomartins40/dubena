<?php

namespace Tests\Migration;

use App\Console\Commands\DedupClientesApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A descoberta de FKs da dedup (T2.2) não pode depender de POSSE das tabelas.
 *
 * **O quase-acidente que originou este teste.** A primeira versão lia
 * `information_schema.constraint_column_usage`, que só expõe constraints de
 * objetos que o usuário POSSUI. Em produção a aplicação roda como `erp_app` —
 * a role restrita `NOSUPERUSER NOBYPASSRLS` do multi-tenant, que não é dona das
 * tabelas. O dry-run lá reportou **"0 tabelas referenciam clientes.id"**
 * enquanto havia 20.328 telefones e 19.678 endereços apontando para as cópias:
 * executar teria apagado os clientes sem remapear nada, criando ~40 mil órfãos
 * numa base de produção.
 *
 * A correção lê `pg_constraint` (catálogo real, legível sem posse) e aborta se
 * a lista vier vazia — porque `clientes` tem dezenas de filhas e "nenhuma FK" é
 * sinal de catálogo inacessível, não de ausência de FK.
 */
class DedupClientesFksTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{tabela:string,coluna:string}> */
    private function referencias(): array
    {
        $m = new \ReflectionMethod(DedupClientesApp::class, 'referenciasAClientes');
        $m->setAccessible(true);

        return $m->invoke(new DedupClientesApp);
    }

    public function test_descobre_as_fks_de_clientes_no_catalogo(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('a descoberta usa pg_constraint (Postgres).');
        }

        $refs = $this->referencias();

        $this->assertNotEmpty($refs, 'nenhuma FK encontrada — catálogo inacessível?');

        $chaves = array_map(fn ($r) => $r['tabela'].'.'.$r['coluna'], $refs);

        // As de maior impacto na dedup: se qualquer uma sumir da descoberta, as
        // cópias seriam removidas deixando estas linhas órfãs.
        foreach ([
            'pedidos.cliente_id',
            'clientetelefones.cliente_id',
            'cliente_enderecos.cliente_id',
            'financeiros.cliente_id',
        ] as $esperada) {
            $this->assertContains($esperada, $chaves, "FK {$esperada} não foi descoberta");
        }
    }

    public function test_nomes_de_tabela_vem_sem_o_prefixo_do_schema(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('a descoberta usa pg_constraint (Postgres).');
        }

        foreach ($this->referencias() as $ref) {
            // `conrelid::regclass` devolve "public.x" quando o schema não está no
            // search_path; o UPDATE do remapeamento monta "public.{$tabela}" e
            // viraria "public.public.x".
            $this->assertStringNotContainsString('.', $ref['tabela']);
        }
    }
}
