<?php

namespace Tests\Feature;

use App\Domain\Cliente\ClienteExportacaoService;
use App\Domain\Shared\PermissaoCatalogo;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exportação personalizada da base de clientes (CSV / XLSX / PDF).
 *
 * O que se garante aqui, na ordem do que mais dói se quebrar:
 *  1. quem não é dono da rede NÃO extrai a base — nem com `cliente.export`;
 *  2. o endereço sai DECOMPOSTO e vindo da FK (a coluna `endereco` é NULL na
 *     base real — ver o accessor em Cliente);
 *  3. o filtro por rua/bairro realmente restringe;
 *  4. a extração fica registrada na trilha de segurança (exigência de LGPD).
 */
class ClienteExportacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Estático não é reiniciado entre testes: sem isto, o lote reduzido de
        // um teste vazaria para os seguintes.
        ClienteExportacaoService::$tamanhoDoLote = 2000;
        parent::tearDown();
    }

    /**
     * Usuário com papel real (sem break-glass) e apenas as chaves informadas.
     *
     * `semPapel()` é essencial: a factory padrão marca `support`, que dá bypass
     * total — um teste de negação escrito sobre ela passaria verde sem provar
     * nada.
     *
     * @param  list<string>  $chaves
     * @return array{0: User, 1: Empresa}
     */
    private function ator(array $chaves): array
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->semPapel()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        $role = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Papel de teste']);
        $role->permissions()->sync(
            collect($chaves)->map(fn (string $c) => Permission::firstOrCreate(['chave' => $c])->id)->all(),
        );
        $user->roles()->attach($role->id, ['empresa_id' => $empresa->id]);

        return [$user->fresh(), $empresa];
    }

    /** Cliente com endereço vindo das FKs — como a base real de fato guarda. */
    private function cliente(Empresa $empresa, string $nome, string $rua, string $bairro): Cliente
    {
        // `cidades.uf` tem FK para `estados` — sem o estado, o insert falha.
        Estado::firstOrCreate(['uf' => 'PR'], ['descricao' => 'Paraná', 'cod_ibge' => 41]);
        $cidade = Cidade::firstOrCreate(
            ['grupo_id' => $empresa->grupo_id, 'descricao' => 'Guarapuava', 'uf' => 'PR'],
        );
        // firstOrCreate: `bairros` e `ruas` têm unique (cidade_id, descricao),
        // e vários clientes do mesmo bairro é justamente o caso a testar.
        $b = Bairro::firstOrCreate(
            ['cidade_id' => $cidade->id, 'descricao' => $bairro],
            ['grupo_id' => $empresa->grupo_id],
        );
        $r = Rua::firstOrCreate(
            ['cidade_id' => $cidade->id, 'descricao' => $rua],
            ['grupo_id' => $empresa->grupo_id],
        );

        return Cliente::create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'nome' => $nome, 'cpf' => '01234567890', 'ativo' => true,
            // `endereco` fica NULL de propósito: é o estado real da base.
            'numero' => '587', 'complemento' => 'Fundos', 'cep' => '85070000', 'uf' => 'PR',
            'cidade_id' => $cidade->id, 'bairro_id' => $b->id, 'rua_id' => $r->id,
        ]);
    }

    // ───────────────────────── Autorização (LGPD) ─────────────────────────

    public function test_quem_tem_apenas_cliente_export_nao_extrai_a_base_completa(): void
    {
        // O ponto do requisito: a exportação resumida do dia a dia NÃO abre a
        // porta da base cadastral inteira. Se este teste ficar verde por
        // engano, o gate novo não existe na prática.
        [$user] = $this->ator(['cliente.view', 'cliente.export']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/clientes/exportacao/opcoes')->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/clientes/exportacao', ['formato' => 'csv', 'campos' => ['nome']])
            ->assertStatus(403);
    }

    public function test_dono_da_rede_com_a_permissao_extrai(): void
    {
        [$user, $empresa] = $this->ator(['cliente.view', 'cliente.export.completo']);
        $this->cliente($empresa, 'Jeann Ricardo de Goes', 'Rua XV de Novembro', 'Centro');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/clientes/exportacao/opcoes')
            ->assertOk()
            ->assertJsonPath('data.padrao.0', 'id');
    }

    public function test_a_permissao_nova_esta_no_catalogo_e_fora_do_papel_gerente(): void
    {
        // Guardião da decisão: a chave existe (senão o Gate nunca autoriza) e
        // o Gerente NÃO a herda — é o que a separa de `cliente.export`.
        $this->assertArrayHasKey('cliente.export.completo', PermissaoCatalogo::GRANULARES);

        $seeder = file_get_contents(database_path('seeders/RbacSeeder.php'));
        $this->assertStringContainsString("\$chave === 'cliente.export.completo'", $seeder);
    }

    // ─────────────────────── Endereço decomposto ───────────────────────

    public function test_endereco_sai_em_colunas_separadas_e_o_logradouro_vem_da_fk(): void
    {
        [$user, $empresa] = $this->ator(['cliente.view', 'cliente.export.completo']);
        $this->cliente($empresa, 'Jeann Ricardo de Goes', 'Rua XV de Novembro', 'Centro');

        $resp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes/exportacao', [
            'formato' => 'csv',
            'campos' => ['nome', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep'],
        ])->assertOk();

        $csv = $resp->getContent();

        // A regressão que este teste existe para pegar: ler `clientes.endereco`
        // direto (NULL na base real) exportaria o logradouro vazio e o número
        // solto — o defeito que já cegou roteirização e relatórios.
        $this->assertStringContainsString('Rua XV de Novembro', $csv);
        $this->assertStringContainsString('Centro', $csv);
        $this->assertStringContainsString('Guarapuava', $csv);

        // Colunas SEPARADAS, não uma linha só: é o que permite ordenar por
        // bairro e filtrar por rua na planilha.
        $cabecalho = strtok($csv, "\n");
        $this->assertStringContainsString('Logradouro', $cabecalho);
        $this->assertStringContainsString('Número', $cabecalho);
        $this->assertStringContainsString('Bairro', $cabecalho);
        $this->assertStringNotContainsString('Endereço completo', $cabecalho);
    }

    public function test_filtro_por_bairro_restringe_de_verdade(): void
    {
        [$user, $empresa] = $this->ator(['cliente.view', 'cliente.export.completo']);
        $centro = $this->cliente($empresa, 'Cliente do Centro', 'Rua XV de Novembro', 'Centro');
        $this->cliente($empresa, 'Cliente do Trianon', 'Rua Amazonas', 'Trianon');

        $resp = $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes/exportacao', [
            'formato' => 'csv',
            'campos' => ['nome', 'bairro'],
            'bairro_ids' => [$centro->bairro_id],
        ])->assertOk();

        $csv = $resp->getContent();
        $this->assertStringContainsString('Cliente do Centro', $csv);
        // Sem esta linha o teste passaria com o filtro completamente ignorado.
        $this->assertStringNotContainsString('Cliente do Trianon', $csv);
    }

    public function test_filtro_vazio_nao_restringe_e_traz_todos(): void
    {
        // Guardião do `limpar()` do front e do `filled()` do serviço: um filtro
        // em branco chegando como restrição real devolveria arquivo vazio — e
        // arquivo vazio parece "não há clientes assim", não "o filtro quebrou".
        [$user, $empresa] = $this->ator(['cliente.view', 'cliente.export.completo']);
        $this->cliente($empresa, 'Cliente do Centro', 'Rua XV de Novembro', 'Centro');
        $this->cliente($empresa, 'Cliente do Trianon', 'Rua Amazonas', 'Trianon');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/clientes/exportacao/previa')
            ->assertOk()
            ->assertJsonPath('data.total', 2);
    }

    // ───────────────────────── Formatos ─────────────────────────

    public function test_os_tres_formatos_geram_arquivo_reconhecivel(): void
    {
        [$user, $empresa] = $this->ator(['cliente.view', 'cliente.export.completo']);
        $this->cliente($empresa, 'Jeann Ricardo de Goes', 'Rua XV de Novembro', 'Centro');

        $campos = ['nome', 'logradouro', 'numero', 'bairro'];

        // XLSX: assinatura de ZIP (todo OOXML é um zip) — prova que saiu um
        // arquivo de planilha de verdade, não um CSV renomeado.
        $xlsx = $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes/exportacao', [
            'formato' => 'xlsx', 'campos' => $campos,
        ])->assertOk()->getContent();
        $this->assertStringStartsWith('PK', $xlsx);
        $this->assertGreaterThan(1000, strlen($xlsx));

        $pdf = $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes/exportacao', [
            'formato' => 'pdf', 'campos' => $campos,
        ])->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);

        // CSV com BOM: sem ele o Excel abre em ANSI e "João" vira "JoÃ£o".
        $csv = $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes/exportacao', [
            'formato' => 'csv', 'campos' => $campos,
        ])->assertOk()->getContent();
        $this->assertStringStartsWith("\u{FEFF}", $csv);
    }

    public function test_complemento_que_parece_formula_nao_derruba_a_exportacao(): void
    {
        // Regressão REAL de produção: 6 cadastros têm complemento '=', '+',
        // '=======' ou '=-'. O PhpSpreadsheet lia como fórmula e a exportação
        // inteira morria com 500 ("Formula Error: Unexpected operator '='") —
        // um cadastro digitado errado derrubava o relatório de 51 mil clientes.
        // É também o vetor de formula injection: '=HYPERLINK(...)' viraria
        // fórmula ativa na máquina de quem abrisse a planilha.
        [$user, $empresa] = $this->ator(['cliente.view', 'cliente.export.completo']);
        $cliente = $this->cliente($empresa, 'Cliente com complemento estranho', 'Rua XV de Novembro', 'Centro');

        // '==-' é o valor REAL que derrubava a exportação em produção. Ele
        // importa: dos 16 complementos que começam com operador, só ESTE faz o
        // PhpSpreadsheet lançar — '=', '+', '=======' e '=-' passam. Um teste
        // escrito com qualquer um dos outros ficaria verde sem provar nada.
        $cliente->update(['complemento' => '==-']);

        $campos = ['nome', 'complemento'];

        $xlsx = $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes/exportacao', [
            'formato' => 'xlsx', 'campos' => $campos,
        ])->assertOk()->getContent();
        $this->assertStringStartsWith('PK', $xlsx);

        // No CSV não há tipo de célula: a defesa é o apóstrofo, que o Excel
        // consome como "isto é texto" e não mostra na planilha.
        $csv = $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes/exportacao', [
            'formato' => 'csv', 'campos' => $campos,
        ])->assertOk()->getContent();
        $this->assertStringContainsString("'==-", $csv);
    }

    public function test_csv_e_xlsx_nao_recusam_por_volume(): void
    {
        // Guardião contra o defeito que ISTO já foi: um teto de 50.000
        // inventado no código recusava "exportar todos" numa base de 51.793
        // clientes. Nenhum limite real o justificava — o XLSX comporta
        // 1.048.576 linhas e o CSV não tem limite. Se alguém reintroduzir um
        // teto de volume em CSV/XLSX, este teste falha.
        $this->assertSame(
            PHP_INT_MAX,
            ClienteExportacaoService::SEM_LIMITE,
            'CSV e XLSX não podem ter teto de linhas.',
        );

        $controller = file_get_contents(app_path('Http/Controllers/Api/Admin/ClienteExportacaoController.php'));
        $this->assertStringNotContainsString(
            'acima do limite de',
            $controller,
            'Voltou a existir recusa por volume fora do PDF.',
        );
    }

    public function test_exportacao_em_lotes_nao_perde_nem_duplica_homonimos(): void
    {
        // O `chunk` (que evita hidratar 51 mil models de uma vez) pagina por
        // ordenação: se a ordem não for determinística, um lote repete ou pula
        // registros na fronteira. A base é cheia de homônimos — "MARIA
        // APARECIDA" aparece dezenas de vezes —, então é exatamente o cenário
        // que quebraria. Por isso o `orderBy('id')` de desempate.
        [$user, $empresa] = $this->ator(['cliente.view', 'cliente.export.completo']);

        // Lote pequeno para o teste ATRAVESSAR a fronteira entre lotes: com os
        // 2000 de produção, 25 clientes caberiam num lote só e este teste
        // passaria sem exercitar paginação nenhuma.
        ClienteExportacaoService::$tamanhoDoLote = 5;

        $quantos = 25;
        for ($i = 0; $i < $quantos; $i++) {
            $this->cliente($empresa, 'MARIA APARECIDA DA SILVA', 'Rua Um', 'Centro');
        }

        $csv = $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes/exportacao', [
            'formato' => 'csv', 'campos' => ['id', 'nome'],
        ])->assertOk()->getContent();

        // -1 do cabeçalho; o arquivo termina com quebra de linha.
        $linhas = array_filter(explode("\n", trim($csv)));
        $this->assertCount($quantos + 1, $linhas, 'O chunk perdeu ou duplicou linhas.');

        // Nenhum id repetido: duplicata é o sintoma clássico de paginação sem
        // ordem estável, e passaria despercebida só contando linhas.
        $ids = array_map(fn ($l) => (int) strtok($l, ';'), array_slice($linhas, 1));
        $this->assertCount($quantos, array_unique($ids), 'Há id repetido na exportação.');
    }

    public function test_coluna_fora_do_catalogo_e_ignorada(): void
    {
        // A requisição não pode virar `select` livre: pedir uma coluna que o
        // catálogo não declara não pode vazar dado nenhum.
        [$user, $empresa] = $this->ator(['cliente.view', 'cliente.export.completo']);
        $this->cliente($empresa, 'Jeann Ricardo de Goes', 'Rua XV de Novembro', 'Centro');

        $csv = $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes/exportacao', [
            'formato' => 'csv', 'campos' => ['nome', 'password', 'remember_token'],
        ])->assertOk()->getContent();

        $cabecalho = strtok($csv, "\n");
        $this->assertStringContainsString('Nome', $cabecalho);
        $this->assertStringNotContainsString('password', $cabecalho);
        $this->assertStringNotContainsString('remember_token', $cabecalho);
    }

    // ───────────────────────── Trilha (LGPD) ─────────────────────────

    public function test_a_exportacao_fica_registrada_com_o_que_foi_levado(): void
    {
        [$user, $empresa] = $this->ator(['cliente.view', 'cliente.export.completo']);
        $this->cliente($empresa, 'Jeann Ricardo de Goes', 'Rua XV de Novembro', 'Centro');

        $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes/exportacao', [
            'formato' => 'xlsx', 'campos' => ['nome', 'cpf'], 'situacao' => 'ativos',
        ])->assertOk();

        $evento = SecurityEvent::where('tipo', 'cliente.exportacao')->latest('id')->first();

        $this->assertNotNull($evento, 'Sem trilha não há como responder a um pedido de titular.');
        $this->assertSame((int) $user->id, (int) $evento->user_id);
        $this->assertSame('xlsx', $evento->detalhes['formato']);
        $this->assertSame(1, $evento->detalhes['linhas']);
        // O que a fiscalização pergunta primeiro: saiu CPF nesse arquivo?
        $this->assertContains('cpf', $evento->detalhes['campos_sensiveis']);
    }

    // ───────────────────────── Limites ─────────────────────────

    public function test_pdf_acima_do_teto_e_recusado_com_o_numero_na_mensagem(): void
    {
        [$user, $empresa] = $this->ator(['cliente.view', 'cliente.export.completo']);

        // Não criamos 5.001 clientes (custaria minutos): medimos a decisão
        // contra o teto declarado, que é a regra que se quer provar.
        // O PDF é o ÚNICO com teto, e por limitação do formato: o dompdf monta
        // a tabela inteira em memória, e 5 mil linhas já passam de 100 páginas.
        $this->assertSame(5000, ClienteExportacaoService::LIMITE_PDF);

        $this->cliente($empresa, 'Jeann Ricardo de Goes', 'Rua XV de Novembro', 'Centro');
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/clientes/exportacao/previa')
            ->assertOk()
            ->assertJsonPath('data.excede_pdf', false);
    }

    public function test_exportacao_nao_cruza_empresa(): void
    {
        // A fronteira dura do multi-tenant: o dono da rede A não leva a base
        // da rede B, por mais permissão que tenha.
        [$user, $empresaA] = $this->ator(['cliente.view', 'cliente.export.completo']);
        $this->cliente($empresaA, 'Cliente da Empresa A', 'Rua XV de Novembro', 'Centro');

        $empresaB = Empresa::factory()->create();
        $this->cliente($empresaB, 'Cliente da Empresa B', 'Rua Amazonas', 'Trianon');

        $csv = $this->actingAs($user, 'sanctum')->postJson('/api/admin/clientes/exportacao', [
            'formato' => 'csv', 'campos' => ['nome'],
        ])->assertOk()->getContent();

        $this->assertStringContainsString('Cliente da Empresa A', $csv);
        $this->assertStringNotContainsString('Cliente da Empresa B', $csv);
    }
}
