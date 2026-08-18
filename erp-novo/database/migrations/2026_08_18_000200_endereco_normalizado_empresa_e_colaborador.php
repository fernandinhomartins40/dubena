<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Endereço normalizado (FK) em `empresas` e `colaboradores`.
 *
 * **A dívida.** A migration original de `empresas` guardava cidade/bairro como
 * texto livre, com o comentário *"normalizado virá em N1"* — o N1 normalizou
 * cliente e nunca voltou aqui. `colaboradores` nasceu sem endereço nenhum.
 *
 * Enquanto isso, os dois formulários da SPA já enviavam `cidade_id`/`bairro_id`
 * via `AsyncSelect`: o usuário preenchia (em Colaborador, campo até marcado como
 * obrigatório) e o backend descartava em silêncio, porque a coluna não existia.
 *
 * **O legado sempre teve FK nos dois** — e 100% preenchida: 81 colaboradores e 7
 * empresas com `cidade_id`/`bairro_id`/`rua_id`. Quem ficou para trás foi o
 * schema novo, e por isso as 7 empresas migraram com endereço VAZIO. Isso não é
 * cosmético: a DANFE imprime o endereço do emitente, e nota fiscal sem endereço
 * é problema fiscal.
 *
 * **Por que as colunas de texto continuam existindo.** `DanfePdfService`,
 * `ComodatoPdfService` e `ValeGasPdfService` imprimem `$empresa->cidade` como
 * string. Trocar por FK quebraria os três documentos. Aqui as FKs são a fonte da
 * verdade e o texto passa a ser DERIVADO delas (ver `EnderecoEmpresaSync`), o que
 * mantém os PDFs funcionando sem os consultar o cadastro geográfico.
 *
 * Todas as colunas são nullable: empresa e colaborador já existentes seguem
 * válidos sem endereço, e a carga do ETL preenche o que a origem tiver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $t) {
            // `restrictOnDelete` seria hostil aqui (impediria apagar uma cidade
            // do cadastro por causa de uma empresa); `nullOnDelete` mantém a
            // empresa viva e o texto derivado preserva o que foi impresso.
            $t->foreignId('cidade_id')->nullable()->after('uf')
                ->constrained('cidades')->nullOnDelete();
            $t->foreignId('bairro_id')->nullable()->after('cidade_id')
                ->constrained('bairros')->nullOnDelete();
            $t->foreignId('rua_id')->nullable()->after('bairro_id')
                ->constrained('ruas')->nullOnDelete();
            $t->foreignId('regiao_id')->nullable()->after('rua_id')
                ->constrained('regioes')->nullOnDelete();
        });

        Schema::table('colaboradores', function (Blueprint $t) {
            $t->string('cep', 8)->nullable()->after('telefone');
            $t->string('uf', 2)->nullable()->after('cep');
            $t->foreignId('cidade_id')->nullable()->after('uf')
                ->constrained('cidades')->nullOnDelete();
            $t->foreignId('bairro_id')->nullable()->after('cidade_id')
                ->constrained('bairros')->nullOnDelete();
            $t->foreignId('rua_id')->nullable()->after('bairro_id')
                ->constrained('ruas')->nullOnDelete();
            $t->string('numero', 20)->nullable()->after('rua_id');
            $t->string('complemento')->nullable()->after('numero');
        });

        // Retrofit: onde o texto já existe e casa com o cadastro, liga a FK.
        // Não inventa cidade — só conecta o que já bate por descrição.
        $this->ligarFksPeloTextoExistente();
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('cidade_id');
            $t->dropConstrainedForeignId('bairro_id');
            $t->dropConstrainedForeignId('rua_id');
            $t->dropConstrainedForeignId('regiao_id');
        });

        Schema::table('colaboradores', function (Blueprint $t) {
            $t->dropConstrainedForeignId('cidade_id');
            $t->dropConstrainedForeignId('bairro_id');
            $t->dropConstrainedForeignId('rua_id');
            $t->dropColumn(['cep', 'uf', 'numero', 'complemento']);
        });
    }

    /**
     * Liga `empresas.cidade_id`/`bairro_id` a partir do texto já gravado.
     *
     * A empresa demo (seed) tem cidade/bairro em texto; as migradas do legado
     * têm ambos nulos e serão preenchidas pela recarga do ETL. Casar por
     * descrição aqui evita que a empresa de desenvolvimento perca o vínculo.
     */
    private function ligarFksPeloTextoExistente(): void
    {
        $empresas = DB::table('empresas')
            ->whereNull('cidade_id')
            ->where(fn ($q) => $q->whereNotNull('cidade')->orWhereNotNull('bairro'))
            ->get(['id', 'cidade', 'bairro', 'grupo_id']);

        foreach ($empresas as $e) {
            $cidadeId = $e->cidade === null ? null : DB::table('cidades')
                ->where('grupo_id', $e->grupo_id)
                ->whereRaw('LOWER(descricao) = ?', [mb_strtolower(trim($e->cidade))])
                ->value('id');

            $bairroId = ($e->bairro === null || $cidadeId === null) ? null : DB::table('bairros')
                ->where('cidade_id', $cidadeId)
                ->whereRaw('LOWER(descricao) = ?', [mb_strtolower(trim($e->bairro))])
                ->value('id');

            if ($cidadeId === null && $bairroId === null) {
                continue;
            }

            DB::table('empresas')->where('id', $e->id)->update(array_filter([
                'cidade_id' => $cidadeId,
                'bairro_id' => $bairroId,
            ], fn ($v) => $v !== null));
        }
    }
};
