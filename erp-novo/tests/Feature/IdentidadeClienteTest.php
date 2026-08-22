<?php

namespace Tests\Feature;

use App\Domain\Identidade\ConsolidarClientes;
use App\Domain\Identidade\IdentificarOuCriarCliente;
use App\Domain\Identidade\NormalizadorTexto;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoSituacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Identidade de cliente sem CPF.
 *
 * Medido na base real antes desta fase: 90,5% dos clientes não têm documento, e
 * 3.486 cadastros duplicados carregavam 20.702 pedidos. Os casos abaixo são os
 * padrões reais encontrados nessa medição.
 */
class IdentidadeClienteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Empresa} */
    private function suporte(): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'support' => true,
        ]);

        return [$user, $empresa];
    }

    /** Cidade real: `clientes.cidade_id` e FK, e um id inventado nao insere. */
    private function cidade(Empresa $empresa): int
    {
        return $this->cidadeId ??= (int) \App\Models\Geografico\Cidade::factory()->create([
            'grupo_id' => $empresa->grupo_id,
        ])->id;
    }

    private ?int $cidadeId = null;

    /** @param array<string,mixed> $dados */
    private function cadastrar(Empresa $empresa, array $dados, string $origem = 'admin')
    {
        return app(IdentificarOuCriarCliente::class)->executar(
            (int) $empresa->id, (int) $empresa->grupo_id, $dados, $origem,
        );
    }

    // ── Normalização e fonética ─────────────────────────────────────────────

    /**
     * Os pares abaixo são clientes REAIS da base que estavam duplicados: o
     * mesmo nome digitado em canais diferentes.
     */
    public function test_fonetica_reconhece_variacoes_reais_de_grafia(): void
    {
        $mesmos = [
            ['SANDRA MARA DE FATIMA CARNEIRO', 'Sandra Mara de Fátima Carneiro'], // caixa + acento
            ['VICENTE BARONI', 'Vicente Barone'],                                  // erro de digitação
            ['GABRIEL NICZAI DE ARAUJO', 'Gabriel Niczay'],                         // parcial + fonética
            ['MIGUEL MARCELITO GADENS', 'Miguel MARCELIt gadens'],                  // truncado
            ['JOSE DE SOUZA', 'Jose Sousa'],                                        // z/s
        ];

        foreach ($mesmos as [$a, $b]) {
            $this->assertGreaterThanOrEqual(
                0.8,
                NormalizadorTexto::similaridadeNome($a, $b),
                "'{$a}' e '{$b}' deveriam ser reconhecidos como a mesma pessoa",
            );
        }
    }

    /** Pessoas diferentes no mesmo telefone (família) NÃO podem casar. */
    public function test_fonetica_nao_confunde_pessoas_diferentes(): void
    {
        $this->assertLessThan(
            0.6,
            NormalizadorTexto::similaridadeNome('JEANN RICARDO DE GOES', 'Karem Francieli Calixto'),
        );
    }

    /**
     * Primeiro nome sozinho é evidência fraca: "Paulo" bate com todo Paulo da
     * base. Sem este teto, um único token consolidaria pessoas diferentes.
     */
    public function test_um_unico_token_nao_basta_para_identificar(): void
    {
        $this->assertLessThanOrEqual(
            0.5,
            NormalizadorTexto::similaridadeNome('PAULO CESAR DOMINICO', 'Paulo'),
        );
    }

    public function test_telefone_normaliza_para_os_oito_digitos_finais(): void
    {
        foreach (['42999852622', '(42) 99985-2622', '+5542999852622'] as $formato) {
            $this->assertSame('99852622', NormalizadorTexto::telefone($formato));
        }
    }

    // ── Decisão de identidade ───────────────────────────────────────────────

    /** Telefone + nome = mesma pessoa: reconhece em vez de duplicar. */
    public function test_telefone_e_nome_iguais_reconhecem_o_cliente(): void
    {
        [, $empresa] = $this->suporte();

        $primeiro = $this->cadastrar($empresa, [
            'nome' => 'Sandra Mara de Fátima Carneiro', 'telefone' => '42999852622',
        ]);

        $segundo = $this->cadastrar($empresa, [
            'nome' => 'SANDRA MARA DE FATIMA CARNEIRO', 'telefone' => '(42) 99985-2622',
        ], 'entregador');

        $this->assertTrue($primeiro->criado);
        $this->assertFalse($segundo->criado, 'não deveria criar um segundo cadastro');
        $this->assertTrue($segundo->identificado);
        $this->assertSame($primeiro->cliente->id, $segundo->cliente->id);
        $this->assertSame(1, Cliente::query()->count());
    }

    /**
     * Mesmo telefone, nomes DIFERENTES: família/república compartilha número.
     * A venda acontece e o par vai para revisão — nunca funde sozinho.
     */
    public function test_telefone_igual_com_nome_diferente_cria_e_enfileira(): void
    {
        [, $empresa] = $this->suporte();

        $this->cadastrar($empresa, ['nome' => 'Jeann Ricardo de Goes', 'telefone' => '42991045566']);
        $segundo = $this->cadastrar($empresa, ['nome' => 'Karem Francieli Calixto', 'telefone' => '42991045566']);

        $this->assertTrue($segundo->criado, 'a venda não pode travar');
        $this->assertTrue($segundo->emRevisao);
        $this->assertSame(2, Cliente::query()->count());
        $this->assertDatabaseHas('cliente_revisoes', ['situacao' => 'pendente', 'escore' => 60]);
    }

    /** CPF é determinístico: bate, é a mesma pessoa, ainda que o nome mude. */
    public function test_cpf_identico_identifica_mesmo_com_nome_diferente(): void
    {
        [, $empresa] = $this->suporte();

        $primeiro = $this->cadastrar($empresa, [
            'nome' => 'Maria Aparecida', 'cpf' => '39053344705', 'telefone' => '42988001122',
        ]);

        $segundo = $this->cadastrar($empresa, [
            'nome' => 'Maria Aparecida da Silva Souza', 'cpf' => '390.533.447-05',
            'telefone' => '42977006655',
        ]);

        $this->assertSame($primeiro->cliente->id, $segundo->cliente->id);
        $this->assertFalse($segundo->criado);
    }

    /**
     * Documento DIFERENTE veta a fusão automática, por mais que o resto bata.
     * Pai e filho homônimos, mesma casa, mesmo telefone: são duas pessoas.
     */
    public function test_documentos_diferentes_vetam_a_fusao_automatica(): void
    {
        [, $empresa] = $this->suporte();

        $this->cadastrar($empresa, [
            'nome' => 'Joao Carlos dos Santos', 'cpf' => '39053344705', 'telefone' => '42988112233',
        ]);

        $segundo = $this->cadastrar($empresa, [
            'nome' => 'Joao Carlos dos Santos', 'cpf' => '11144477735', 'telefone' => '42988112233',
        ]);

        $this->assertTrue($segundo->criado, 'CPF distinto = pessoa distinta: não pode fundir');
        $this->assertSame(2, Cliente::query()->count());
    }

    /**
     * Nome sozinho NÃO identifica ninguém.
     *
     * Medido ao calibrar: com nome idêntico pontuando sem corroboração, a
     * varredura devolveu 73.893 pares — todos os homônimos da base.
     */
    public function test_nome_igual_sem_outro_traco_nao_gera_suspeita(): void
    {
        [, $empresa] = $this->suporte();

        $this->cadastrar($empresa, ['nome' => 'Maria Silva', 'telefone' => '42988110000']);
        $segundo = $this->cadastrar($empresa, ['nome' => 'Maria Silva', 'telefone' => '42999220000']);

        $this->assertTrue($segundo->criado);
        $this->assertFalse($segundo->emRevisao, 'homônimo não é suspeita de duplicata');
        $this->assertDatabaseCount('cliente_revisoes', 0);
    }

    // ── Cadastro sem barreira ───────────────────────────────────────────────

    /**
     * CPF NÃO é obrigatório: 90,5% da base não tem, e exigi-lo trava a venda
     * quando o cliente se recusa a informar — que é a razão desta fase existir.
     */
    public function test_cadastra_sem_cpf_com_nome_e_telefone(): void
    {
        [, $empresa] = $this->suporte();

        $r = $this->cadastrar($empresa, ['nome' => 'Cliente Sem Documento', 'telefone' => '42999887766']);

        $this->assertTrue($r->criado);
        $this->assertNull($r->cliente->cpf);
    }

    /** Nome + endereço também basta: nem todo cliente informa telefone. */
    public function test_cadastra_sem_telefone_com_endereco(): void
    {
        [, $empresa] = $this->suporte();

        $r = $this->cadastrar($empresa, [
            'nome' => 'Cliente Sem Telefone', 'endereco' => 'Rua das Flores',
            'numero' => '100', 'cidade_id' => $this->cidade($empresa),
        ]);

        $this->assertTrue($r->criado);
    }

    /** Sem nenhuma forma de contato não há cadastro utilizável. */
    public function test_recusa_cadastro_sem_contato_nem_endereco(): void
    {
        [, $empresa] = $this->suporte();

        $this->expectException(ValidationException::class);
        $this->cadastrar($empresa, ['nome' => 'Fulano Sem Nada']);
    }

    /**
     * Endereço repetido NÃO trava: prédio, vila e condomínio têm dezenas de
     * clientes no mesmo logradouro e número. A trava antiga bloqueava a venda.
     */
    public function test_mesmo_endereco_nao_bloqueia_o_cadastro(): void
    {
        [, $empresa] = $this->suporte();

        $this->cadastrar($empresa, [
            'nome' => 'Vizinho de Cima', 'endereco' => 'Rua das Flores', 'numero' => '100',
            'cidade_id' => $this->cidade($empresa), 'telefone' => '42988110001',
        ]);

        $segundo = $this->cadastrar($empresa, [
            'nome' => 'Vizinho de Baixo', 'endereco' => 'Rua das Flores', 'numero' => '100',
            'cidade_id' => $this->cidade($empresa), 'telefone' => '42988110002',
        ]);

        $this->assertTrue($segundo->criado);
        $this->assertSame(2, Cliente::query()->count());
    }

    /** Reconhecer um cliente COMPLETA o cadastro dele com o que veio novo. */
    public function test_identificar_enriquece_o_cadastro_existente(): void
    {
        [, $empresa] = $this->suporte();

        $primeiro = $this->cadastrar($empresa, [
            'nome' => 'Joilson de Souza', 'telefone' => '42988525911',
        ]);
        $this->assertNull($primeiro->cliente->email);

        $this->cadastrar($empresa, [
            'nome' => 'JOILSON DE SOUZA', 'telefone' => '42988525911',
            'email' => 'joilson@exemplo.com', 'cpf' => '39053344705',
        ], 'app');

        $atualizado = $primeiro->cliente->fresh();
        $this->assertSame('joilson@exemplo.com', $atualizado->email);
        $this->assertSame('39053344705', $atualizado->cpf);
    }

    /** Identidade não cruza empresa: mesmo telefone em tenants distintos. */
    public function test_identidade_nao_cruza_empresa(): void
    {
        [, $empresaA] = $this->suporte();
        [, $empresaB] = $this->suporte();

        $a = $this->cadastrar($empresaA, ['nome' => 'Cliente Repetido', 'telefone' => '42999112233']);
        $b = $this->cadastrar($empresaB, ['nome' => 'Cliente Repetido', 'telefone' => '42999112233']);

        $this->assertNotSame($a->cliente->id, $b->cliente->id);
        $this->assertTrue($b->criado);
    }

    // ── Consolidação ────────────────────────────────────────────────────────

    /**
     * Consolidar REMAPEIA o histórico e não apaga nada — era o que impedia a
     * limpeza da base: pedidos apontando para a cópia.
     */
    public function test_consolidar_remapeia_pedidos_e_preserva_o_absorvido(): void
    {
        [$user, $empresa] = $this->suporte();
        $this->actingAs($user, 'sanctum');

        $principal = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id, 'nome' => 'Vicente Baroni',
        ]);
        $copia = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'nome' => 'Vicente Barone', 'email' => 'vicente@exemplo.com',
        ]);

        $situacao = PedidoSituacao::query()->create([
            'grupo_id' => $empresa->grupo_id, 'descricao' => 'Concluído', 'efeito' => 'CONCLUIDO',
        ]);
        $pedido = Pedido::query()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $copia->id, 'pedidosituacao_id' => $situacao->id,
        ]);

        app(ConsolidarClientes::class)->executar($principal, $copia, 100, 'humano', ['Nome muito parecido']);

        // O pedido migrou para o sobrevivente.
        $this->assertSame($principal->id, $pedido->fresh()->cliente_id);
        // A cópia continua existindo, desativada e apontando para o vencedor.
        $this->assertDatabaseHas('clientes', ['id' => $copia->id, 'ativo' => false]);
        $this->assertDatabaseHas('cliente_vinculos', [
            'cliente_id' => $copia->id, 'principal_id' => $principal->id,
        ]);
        // E o dado que só a cópia tinha foi agregado, não perdido.
        $this->assertSame('vicente@exemplo.com', $principal->fresh()->email);
    }

    /** Consolidação encadeada esconderia o cadastro real. */
    public function test_nao_consolida_um_cadastro_ja_consolidado(): void
    {
        [$user, $empresa] = $this->suporte();
        $this->actingAs($user, 'sanctum');

        $base = ['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id];
        $a = Cliente::factory()->create($base + ['nome' => 'Primeiro']);
        $b = Cliente::factory()->create($base + ['nome' => 'Segundo']);
        $c = Cliente::factory()->create($base + ['nome' => 'Terceiro']);

        app(ConsolidarClientes::class)->executar($a, $b, 100);

        $this->expectException(ValidationException::class);
        app(ConsolidarClientes::class)->executar($c, $b, 100);
    }

    /** Um cadastro absorvido não pode voltar como candidato. */
    public function test_cadastro_absorvido_nao_aparece_como_candidato(): void
    {
        [$user, $empresa] = $this->suporte();
        $this->actingAs($user, 'sanctum');

        $principal = $this->cadastrar($empresa, ['nome' => 'Ana Paula Costa', 'telefone' => '42988220011'])->cliente;
        $copia = $this->cadastrar($empresa, ['nome' => 'Ana Paula Costa Silva', 'telefone' => '42988330022'])->cliente;

        app(ConsolidarClientes::class)->executar($principal, $copia, 100);

        $sugestoes = app(IdentificarOuCriarCliente::class)
            ->sugerir((int) $empresa->id, ['nome' => 'Ana Paula Costa Silva', 'telefone' => '42988330022']);

        $this->assertNotContains($copia->id, $sugestoes->pluck('cliente.id')->all());
    }
}
