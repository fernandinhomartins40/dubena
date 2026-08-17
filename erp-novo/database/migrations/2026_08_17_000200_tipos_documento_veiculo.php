<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de documento de VEÍCULO — CRLV, seguro, ANTT… (T4.5 do PLANO_PRODUCAO).
 *
 * O legado tem o cadastro (`TipodocumentoController` + model `Tipodocumento`),
 * consumido pelo pivô `Veiculodocumento` e pelo dropdown do cadastro de veículo.
 * No novo, o endpoint `veiculos/{id}/documentos` grava documentos **sem domínio
 * de valores por trás**: o campo fica livre e cada operador digita de um jeito.
 *
 * ⚠️ Não confundir com `DocumentotipoController` do legado (model
 * `Documentotipo`), que é o tipo da GESTÃO DOCUMENTAL — outro módulo, outro
 * controller. A auditoria destaca essa armadilha nominal explicitamente.
 *
 * Segue o padrão dos cadastros de apoio (grupo_id + descricao + ativo), o que
 * faz o CRUD sair de graça pelo `CadastroApoioRegistry`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tipos_documento_veiculo')) {
            Schema::create('tipos_documento_veiculo', function (Blueprint $t) {
                $t->id();
                $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();
                $t->string('descricao');
                $t->boolean('ativo')->default(true);
                // Documento que vence (CRLV, seguro) vs. permanente: permite ao
                // sistema cobrar data de validade só de quem precisa.
                $t->boolean('exige_validade')->default(false);
                $t->timestamps();
                $t->unique(['grupo_id', 'descricao']);
            });
        }

        // Liga o documento do veículo ao tipo. Nullable: os documentos que já
        // existem não têm tipo, e forçá-los quebraria a listagem.
        if (Schema::hasTable('veiculo_documentos') && ! Schema::hasColumn('veiculo_documentos', 'tipo_documento_id')) {
            Schema::table('veiculo_documentos', function (Blueprint $t) {
                $t->foreignId('tipo_documento_id')->nullable()
                    ->constrained('tipos_documento_veiculo')->nullOnDelete();
            });
        }

        $this->aplicarRls();
        $this->semearTiposPadrao();
    }

    /**
     * Semeia os tipos padrão para os grupos existentes.
     *
     * Um cadastro de apoio vazio é tão inútil quanto a ausência dele: o dropdown
     * abre sem opções e o operador volta a digitar texto livre. Estes são os
     * documentos obrigatórios de um veículo de carga no Brasil.
     *
     * Idempotente (`insertOrIgnore` + UNIQUE por grupo+descrição), então rodar
     * de novo não duplica nem sobrescreve o que o cliente tenha ajustado.
     */
    private function semearTiposPadrao(): void
    {
        if (! Schema::hasTable('grupos')) {
            return;
        }

        $padroes = [
            ['descricao' => 'CRLV', 'exige_validade' => true],
            ['descricao' => 'Seguro', 'exige_validade' => true],
            ['descricao' => 'ANTT/RNTRC', 'exige_validade' => true],
            ['descricao' => 'Inspeção veicular', 'exige_validade' => true],
            ['descricao' => 'Cronotacógrafo', 'exige_validade' => true],
            // Documento do transporte de GLP: sem ele o veículo não roda.
            ['descricao' => 'Certificado de inspeção do tanque', 'exige_validade' => true],
            ['descricao' => 'Nota fiscal de compra', 'exige_validade' => false],
        ];

        foreach (DB::table('grupos')->pluck('id') as $grupoId) {
            $linhas = array_map(fn (array $p) => [
                'grupo_id' => $grupoId,
                'descricao' => $p['descricao'],
                'exige_validade' => $p['exige_validade'],
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], $padroes);

            DB::table('tipos_documento_veiculo')->insertOrIgnore($linhas);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('veiculo_documentos') && Schema::hasColumn('veiculo_documentos', 'tipo_documento_id')) {
            Schema::table('veiculo_documentos', function (Blueprint $t) {
                $t->dropConstrainedForeignId('tipo_documento_id');
            });
        }

        Schema::dropIfExists('tipos_documento_veiculo');
    }

    /**
     * Policy de isolamento + GRANT para a role de runtime.
     *
     * A migration de auto-descoberta de RLS varre as colunas uma única vez, no
     * momento em que roda — tabelas criadas depois não são alcançadas.
     */
    private function aplicarRls(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tabela = 'tipos_documento_veiculo';

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
