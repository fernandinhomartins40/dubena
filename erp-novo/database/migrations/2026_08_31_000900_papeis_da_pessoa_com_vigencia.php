<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F3-01 (primeira peça) — os papéis da pessoa ganham vigência.
 *
 * `clientes` tem três booleanos paralelos: `cliente`, `fornecedor` e
 * `transportador`. Eles respondem "é?" e não conseguem responder "era, quando?".
 *
 * O que isso custa hoje:
 *
 *  - **um fornecedor que deixou de fornecer** não tem como sair da lista sem
 *    apagar o histórico: desmarcar o booleano faz parecer que ele nunca
 *    forneceu, e as notas de entrada antigas passam a apontar para alguém que
 *    "não é fornecedor";
 *  - **não há filtro por papel em consulta nenhuma.** O lookup
 *    `clientes-fornecedores` devolve a tabela inteira, então ao lançar uma nota
 *    de entrada o operador escolhe entre todos os cadastros — inclusive quem
 *    nunca forneceu nada.
 *
 * ## Tabela, e não mais colunas
 *
 * `cliente_papeis` guarda um papel por linha, com `inicio` e `fim`. É a mesma
 * troca que F3-05 fez com o canal: dimensão em vez de booleanos paralelos.
 * Acrescentar um papel novo (representante, prestador) passa a ser uma linha, e
 * não uma migration.
 *
 * ## Os booleanos NÃO são removidos
 *
 * Eles continuam sendo escritos e lidos. Removê-los no mesmo lote em que a
 * tabela nasce deixaria a leitura sem fonte enquanto o consumo não migra — e
 * `ClienteResource`, `ClienteRequest` e o ETL ainda dependem deles.
 *
 * O caminho é: primeiro a tabela existe e é preenchida; depois o consumo migra;
 * só então as colunas saem, em migration própria (migration destrutiva não viaja
 * junto com feature — regra do repositório).
 */
return new class extends Migration
{
    /** Coluna booleana => papel, na conversão. */
    private const LEGADOS = [
        'cliente' => 'CLIENTE',
        'fornecedor' => 'FORNECEDOR',
        'transportador' => 'TRANSPORTADOR',
    ];

    public function up(): void
    {
        Schema::create('cliente_papeis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
            // A policy canonica decide por (tenant, empresa); sem esta coluna a
            // tabela nova ficaria de fora da fronteira SaaS.
            $t->unsignedBigInteger('tenant_account_id')->nullable()->index();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->string('papel', 20);
            // `inicio` nullable: na conversão não se sabe desde quando o papel
            // vale, e inventar uma data seria pior que admitir que não se sabe.
            $t->date('inicio')->nullable();
            $t->date('fim')->nullable();
            $t->timestamps();

            // Um papel ATIVO por pessoa. O histórico admite repetição (saiu e
            // voltou), e é por isso que a unicidade inclui `fim`: linhas
            // encerradas não disputam com a vigente.
            $t->unique(['cliente_id', 'papel', 'fim']);
            $t->index(['empresa_id', 'papel']);
        });

        $this->aplicarRls();
        $this->converterDosBooleanos();
    }

    /**
     * RLS para a tabela nova.
     *
     * Migration que cria tabela com dado de empresa precisa incluir a policy: a
     * descoberta automática varreu o banco uma vez e não alcança o que nasce
     * depois (armadilha registrada no CLAUDE.md).
     */
    private function aplicarRls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE cliente_papeis ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE cliente_papeis FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON cliente_papeis');
        // Funções canônicas, e não `current_setting` à mão: são elas que
        // decidem por (tenant, empresa) em todo o resto do banco, e uma policy
        // escrita à parte sairia de sincronia com as demais na primeira mudança.
        DB::statement(
            'CREATE POLICY tenant_isolation ON cliente_papeis
             USING (app_tenant_can_read(tenant_account_id, empresa_id))
             WITH CHECK (app_tenant_can_operate(tenant_account_id, empresa_id))'
        );
        DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON cliente_papeis TO erp_app');
        DB::statement('GRANT USAGE, SELECT ON SEQUENCE cliente_papeis_id_seq TO erp_app');
    }

    /**
     * Converte os booleanos existentes em papéis vigentes.
     *
     * `inicio` fica nulo: o booleano não guarda desde quando. Preencher com
     * `created_at` do cliente seria plausível e errado — o papel pode ter sido
     * marcado anos depois do cadastro, e um histórico inventado é pior que um
     * histórico incompleto, porque parece confiável.
     */
    private function converterDosBooleanos(): void
    {
        foreach (self::LEGADOS as $coluna => $papel) {
            if (! Schema::hasColumn('clientes', $coluna)) {
                continue;
            }

            DB::statement(
                'INSERT INTO cliente_papeis (empresa_id, grupo_id, tenant_account_id, cliente_id, papel, created_at, updated_at) '
                ."SELECT empresa_id, grupo_id, tenant_account_id, id, '{$papel}', ".$this->agora().', '.$this->agora().' '
                ."FROM clientes WHERE {$coluna} = ".$this->verdadeiro()
            );
        }
    }

    private function agora(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'now()' : "datetime('now')";
    }

    private function verdadeiro(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'true' : '1';
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_papeis');
    }
};
