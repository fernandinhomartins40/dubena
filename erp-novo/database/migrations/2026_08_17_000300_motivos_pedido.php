<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Motivos de ATRASO e de NÃO-VENDA do pedido (T4.8 do PLANO_PRODUCAO).
 *
 * As colunas `pedidos.pedidomotivoatraso_id` e `pedidos.motivonaovenda_id` já
 * existiam no schema novo — mas apontando para o vazio: as tabelas de domínio
 * nunca foram criadas, e `grep motivonaovenda` no erp-novo retornava zero.
 * O operador do disk-gás não conseguia justificar um atraso nem registrar por
 * que a venda não aconteceu.
 *
 * No legado são `PEDIDOMOTIVOATRASOS` (2 linhas) e `MOTIVONAOVENDAS` (5) —
 * cadastros pequenos, mas de uso diário no atendimento e na venda em campo.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tabelas = ['pedido_motivos_atraso', 'motivos_nao_venda'];

    public function up(): void
    {
        foreach ($this->tabelas as $tabela) {
            if (Schema::hasTable($tabela)) {
                continue;
            }

            Schema::create($tabela, function (Blueprint $t) {
                $t->id();
                $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
                $t->string('descricao');
                $t->boolean('ativo')->default(true);
                $t->timestamps();
                $t->unique(['grupo_id', 'descricao']);
            });

            $this->aplicarRls($tabela);
        }

        $this->semear();
        $this->ligarFks();
    }

    /**
     * Semeia os motivos que o legado de fato usa.
     *
     * Valores lidos do Oracle (`PEDIDOMOTIVOATRASOS` e `MOTIVONAOVENDAS`), não
     * inventados: são os que o atendimento escolhe hoje. Um cadastro vazio
     * deixaria o campo obrigatório impossível de preencher no primeiro dia.
     *
     * Idempotente (insertOrIgnore + UNIQUE por grupo+descrição). Quando o
     * espelho do Oracle for reexecutado com as duas tabelas novas no MAPA, o
     * ETL pode reconciliar preservando o que o cliente já tiver ajustado.
     */
    private function semear(): void
    {
        if (! Schema::hasTable('grupos')) {
            return;
        }

        $motivos = [
            'pedido_motivos_atraso' => [
                'Acidente na pista',
                'Interdição do caminho',
                // Os dois acima vêm do legado; os demais são situações que o
                // atendimento relata e não tinha como registrar.
                'Trânsito intenso',
                'Falta de produto',
                'Problema no veículo',
            ],
            'motivos_nao_venda' => [
                'Cliente pesquisando preço',
                'Demora na entrega',
                'Solicitação de vale-gás',
                'Pedir assistência',
                'Ligou por engano',
            ],
        ];

        foreach (DB::table('grupos')->pluck('id') as $grupoId) {
            foreach ($motivos as $tabela => $descricoes) {
                DB::table($tabela)->insertOrIgnore(array_map(fn (string $d) => [
                    'grupo_id' => $grupoId,
                    'descricao' => $d,
                    'ativo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $descricoes));
            }
        }
    }

    public function down(): void
    {
        foreach (['pedidomotivoatraso_id' => 'pedido_motivos_atraso', 'motivonaovenda_id' => 'motivos_nao_venda'] as $coluna => $_) {
            if (Schema::hasTable('pedidos') && Schema::hasColumn('pedidos', $coluna)) {
                // Só derruba a FK; a coluna é preexistente e não é nossa para remover.
                $this->soltarFk('pedidos', $coluna);
            }
        }

        foreach ($this->tabelas as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }

    /**
     * Garante as colunas em `pedidos` e as liga às tabelas novas.
     *
     * **As colunas existiam no Postgres mas NÃO no schema do repositório**: elas
     * chegaram pelo ETL (vieram do Oracle) e nenhuma migration as declarava —
     * então o banco de produção e o dos testes (sqlite, construído só a partir
     * das migrations) estavam divergentes. Criá-las aqui quando ausentes fecha
     * essa divergência; onde já existem, o `hasColumn` deixa como está.
     *
     * Sem a FK, um id inválido entraria calado e o relatório de motivos mostraria
     * linhas órfãs. Com `nullOnDelete`, apagar um motivo não apaga o pedido.
     */
    private function ligarFks(): void
    {
        if (! Schema::hasTable('pedidos')) {
            return;
        }

        $mapa = [
            'pedidomotivoatraso_id' => 'pedido_motivos_atraso',
            'motivonaovenda_id' => 'motivos_nao_venda',
        ];

        foreach ($mapa as $coluna => $tabela) {
            if (! Schema::hasColumn('pedidos', $coluna)) {
                Schema::table('pedidos', function (Blueprint $t) use ($coluna) {
                    $t->unsignedBigInteger($coluna)->nullable();
                });
            }

            if ($this->temFk('pedidos', $coluna)) {
                continue;
            }

            // Zera referências que não existem no destino novo: os ids vieram do
            // Oracle e a FK falharia na criação.
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement(
                    "UPDATE pedidos SET {$coluna} = NULL
                      WHERE {$coluna} IS NOT NULL
                        AND {$coluna} NOT IN (SELECT id FROM {$tabela})"
                );
            }

            Schema::table('pedidos', function (Blueprint $t) use ($coluna, $tabela) {
                $t->foreign($coluna)->references('id')->on($tabela)->nullOnDelete();
            });
        }
    }

    private function temFk(string $tabela, string $coluna): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        return DB::selectOne(
            "SELECT 1 AS ok FROM pg_constraint c
               JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
              WHERE c.contype = 'f' AND c.conrelid = ?::regclass AND a.attname = ?",
            [$tabela, $coluna]
        ) !== null;
    }

    private function soltarFk(string $tabela, string $coluna): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $fk = DB::selectOne(
            "SELECT c.conname FROM pg_constraint c
               JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
              WHERE c.contype = 'f' AND c.conrelid = ?::regclass AND a.attname = ?",
            [$tabela, $coluna]
        );

        if ($fk !== null) {
            DB::statement("ALTER TABLE {$tabela} DROP CONSTRAINT {$fk->conname}");
        }
    }

    private function aplicarRls(string $tabela): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE {$tabela} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$tabela} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$tabela}");
        DB::statement(
            "CREATE POLICY tenant_isolation ON {$tabela}
             USING (
                 nullif(current_setting('app.grupo_id', true), '') IS NULL
                 OR grupo_id = nullif(current_setting('app.grupo_id', true), '')::int
             )
             WITH CHECK (
                 nullif(current_setting('app.grupo_id', true), '') IS NULL
                 OR grupo_id = nullif(current_setting('app.grupo_id', true), '')::int
             )"
        );

        $role = 'erp_app';
        if (DB::selectOne('SELECT 1 AS ok FROM pg_roles WHERE rolname = ?', [$role]) === null) {
            return;
        }

        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$tabela} TO {$role}");
        DB::statement("GRANT USAGE, SELECT, UPDATE ON SEQUENCE {$tabela}_id_seq TO {$role}");
    }
};
