<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N2 — Cliente (também fornecedor/transportador) + sub-relações.
 *
 * Schema LIMPO (princípio do plano): valores numéricos nativos (lat/long decimal,
 * limites/saldo decimal), flags boolean (não string '0'/'1' nem array posicional).
 * Escopo por EMPRESA (cliente é da empresa, não só do grupo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('grupo_id')->constrained('grupos')->cascadeOnDelete();

            // Dados gerais
            $t->string('nome', 200);
            $t->string('fantasia', 200)->nullable();
            $t->foreignId('tipopessoa_id')->nullable()->constrained('tipopessoas')->nullOnDelete();
            $t->foreignId('segmento_id')->nullable()->constrained('segmentos')->nullOnDelete();
            $t->char('sexo', 1)->nullable();
            $t->date('datanascimento')->nullable();
            $t->text('observacoes')->nullable();

            // Documentos / fiscal
            $t->string('cpf', 20)->nullable();
            $t->string('rg', 20)->nullable();
            $t->string('cnpj', 20)->nullable();
            $t->string('inscricao_estadual', 30)->nullable();
            $t->unsignedSmallInteger('indicador_ie')->nullable(); // 1=contribuinte,2=isento,9=não-contrib.
            $t->string('suframa', 20)->nullable();

            // Flags (papéis e fiscais) — boolean nativo
            $t->boolean('cliente')->default(true);
            $t->boolean('fornecedor')->default(false);
            $t->boolean('transportador')->default(false);
            $t->boolean('simples')->default(false);
            $t->boolean('nfemite')->default(false);
            $t->boolean('gasdopovo')->default(false);
            $t->boolean('ativo')->default(true);

            // Endereço
            $t->string('endereco', 500)->nullable();
            $t->string('numero', 10)->nullable();
            $t->string('complemento', 100)->nullable();
            $t->string('ponto_referencia', 255)->nullable();
            $t->string('cep', 10)->nullable();
            $t->char('uf', 2)->nullable();
            $t->foreignId('cidade_id')->nullable()->constrained('cidades')->nullOnDelete();
            $t->foreignId('bairro_id')->nullable()->constrained('bairros')->nullOnDelete();
            $t->foreignId('rua_id')->nullable()->constrained('ruas')->nullOnDelete();
            $t->string('email', 150)->nullable();

            // Geolocalização (preenchida por Job assíncrono)
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('location_type', 30)->nullable();

            // Convênio / crédito (valores decimais)
            $t->boolean('convenio')->default(false);
            $t->boolean('convenio_ativo')->default(false);
            $t->foreignId('convenio_id')->nullable()->constrained('clientes')->nullOnDelete();
            $t->decimal('convenio_limite', 12, 2)->nullable();
            $t->decimal('credito_limite', 12, 2)->nullable();
            $t->decimal('credito_saldo', 12, 2)->nullable();
            $t->date('data_ultima_compra')->nullable();

            $t->timestamps();
            $t->index(['empresa_id', 'nome']);
            $t->index(['empresa_id', 'cpf']);
            $t->index(['empresa_id', 'cnpj']);
        });

        // Telefones (1:N) — substitui arrays posicionais do legado.
        Schema::create('clientetelefones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->foreignId('telefonetipo_id')->nullable()->constrained('telefonetipos')->nullOnDelete();
            $t->string('telefone', 30);
            $t->boolean('whatsapp')->default(false);
            $t->timestamps();
        });

        // Interações / contatos (1:N).
        Schema::create('clienteinteracoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('tipo_id')->nullable()->constrained('clientecontatotipos')->nullOnDelete();
            $t->foreignId('situacao_id')->nullable()->constrained('clientecontatosituacoes')->nullOnDelete();
            $t->text('descricao');
            $t->string('acao', 255)->nullable();
            $t->timestamps();
        });

        // Dependentes do convênio (1:N).
        Schema::create('clientedependentes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->string('nome', 200);
            $t->foreignId('parentesco_id')->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        // Preço/desconto por produto do cliente (1:N) — valores decimais.
        Schema::create('clienteprecos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $t->unsignedBigInteger('produto_id'); // FK formal quando Produto chegar (N3)
            $t->decimal('preco', 12, 2)->nullable();
            $t->decimal('desconto', 5, 2)->nullable(); // percentual
            $t->timestamps();
            $t->unique(['cliente_id', 'produto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clienteprecos');
        Schema::dropIfExists('clientedependentes');
        Schema::dropIfExists('clienteinteracoes');
        Schema::dropIfExists('clientetelefones');
        Schema::dropIfExists('clientes');
    }
};
