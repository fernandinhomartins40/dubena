<?php

namespace Tests\Migration;

use App\Etl\Migrators\MonitoraLegadoMigrator;
use App\Etl\Support\MigrationContext;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cercas: o polígono é a cerca; o círculo é só aproximação.
 *
 * A primeira versão do `MonitoraLegadoMigrator` convertia o polígono do legado
 * (`cercapoligonos`) no círculo circunscrito e **descartava os vértices**. Duas
 * consequências:
 *
 *  - a tela de Cercas mostrava "0 pts" em todas — não havia polígono a desenhar;
 *  - a área ficava DEFORMADA: um setor em L vira um círculo que cobre bairros
 *    vizinhos, e o geofencing passa a acusar entrada onde o entregador não está.
 *
 * O dump real tem 18 cercas com 11 a 138 vértices cada, e cor por cerca.
 */
class CercaPoligonoTest extends TestCase
{
    use RefreshDatabase;

    /** O migrator usa `monitora_legado` fixo: é essa conexão que o teste substitui. */
    private string $conn = 'monitora_legado';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set("database.connections.{$this->conn}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge($this->conn);
        DB::connection($this->conn)->getPdo();
    }

    /** Monta o schema mínimo do Monitora que o migrator lê. */
    private function schemaMonitora(Empresa $empresa): void
    {
        $leg = DB::connection($this->conn);

        $leg->statement('create table empresas (id integer, razao_social text)');
        $leg->table('empresas')->insert([
            'id' => 1, 'razao_social' => $empresa->razao_social,
        ]);

        $leg->statement('create table grupos (id integer, descricao text)');
        $leg->statement('create table veiculos (id integer, empresa_id integer, placa text, descricao text, ativo integer)');
        $leg->statement('create table ultimaposicaos (id integer, veiculo_id integer, latitude real, longitude real, datahora text)');

        $leg->statement('create table cercas (id integer, grupo_id integer, empresa_id integer, setor_id integer, cor text, descricao text, ativo integer)');
        $leg->statement('create table cercapoligonos (id integer, grupo_id integer, empresa_id integer, cerca_id integer, latitude real, longitude real)');
    }

    public function test_poligono_do_legado_vira_vertices_e_nao_so_circulo(): void
    {
        $empresa = Empresa::factory()->create();
        $this->schemaMonitora($empresa);
        $leg = DB::connection($this->conn);

        $leg->table('cercas')->insert([
            'id' => 7, 'grupo_id' => 1, 'empresa_id' => 1, 'setor_id' => 1,
            'cor' => '#f700ff', 'descricao' => 'Setor 07 - MAICON', 'ativo' => 1,
        ]);

        // Um quadrado: 4 vértices, em ordem.
        $vertices = [
            [-25.35, -51.50], [-25.35, -51.41], [-25.43, -51.41], [-25.43, -51.50],
        ];
        foreach ($vertices as $i => [$lat, $lng]) {
            $leg->table('cercapoligonos')->insert([
                'id' => $i + 1, 'grupo_id' => 1, 'empresa_id' => 1,
                'cerca_id' => 7, 'latitude' => $lat, 'longitude' => $lng,
            ]);
        }

        (new MonitoraLegadoMigrator)->migrar(new MigrationContext);

        $cerca = DB::table('monitora_cercas')->where('id', 7)->first();
        $this->assertNotNull($cerca, 'a cerca não migrou');
        $this->assertSame('#f700ff', $cerca->cor, 'a cor identifica o setor no mapa');

        $pontos = DB::table('monitora_cerca_pontos')->where('cerca_id', 7)->orderBy('ordem')->get();
        $this->assertCount(4, $pontos, 'a tela mostraria "0 pts" — o polígono não foi gravado');
        // A ORDEM é o desenho: trocar dois vértices vira um polígono cruzado.
        $this->assertSame(-51.5, (float) $pontos[0]->longitude);
        $this->assertSame(-51.41, (float) $pontos[1]->longitude);
        $this->assertSame(-51.41, (float) $pontos[2]->longitude);
        $this->assertSame(-51.5, (float) $pontos[3]->longitude);
    }

    /** O círculo continua sendo gravado: enquadra o mapa e pré-filtra distância. */
    public function test_circulo_circunscrito_continua_disponivel(): void
    {
        $empresa = Empresa::factory()->create();
        $this->schemaMonitora($empresa);
        $leg = DB::connection($this->conn);

        $leg->table('cercas')->insert([
            'id' => 8, 'grupo_id' => 1, 'empresa_id' => 1, 'setor_id' => null,
            'cor' => '#00ccff', 'descricao' => 'Setor 08', 'ativo' => 1,
        ]);
        foreach ([[-25.35, -51.50], [-25.35, -51.41], [-25.43, -51.41]] as $i => [$lat, $lng]) {
            $leg->table('cercapoligonos')->insert([
                'id' => $i + 1, 'grupo_id' => 1, 'empresa_id' => 1,
                'cerca_id' => 8, 'latitude' => $lat, 'longitude' => $lng,
            ]);
        }

        (new MonitoraLegadoMigrator)->migrar(new MigrationContext);

        $cerca = DB::table('monitora_cercas')->where('id', 8)->first();
        $this->assertGreaterThan(0, (float) $cerca->raio_metros, 'o raio enquadra o mapa');
        $this->assertNotSame(0.0, (float) $cerca->centro_lat);
    }

    /** Recarga substitui o polígono por inteiro — nunca mescla vértices velhos com novos. */
    public function test_recarga_substitui_o_poligono_em_vez_de_mesclar(): void
    {
        $empresa = Empresa::factory()->create();
        $this->schemaMonitora($empresa);
        $leg = DB::connection($this->conn);

        $leg->table('cercas')->insert([
            'id' => 9, 'grupo_id' => 1, 'empresa_id' => 1, 'setor_id' => null,
            'cor' => '#ff0000', 'descricao' => 'Setor 09', 'ativo' => 1,
        ]);
        foreach ([[-25.35, -51.50], [-25.35, -51.41], [-25.43, -51.41]] as $i => [$lat, $lng]) {
            $leg->table('cercapoligonos')->insert([
                'id' => $i + 1, 'grupo_id' => 1, 'empresa_id' => 1,
                'cerca_id' => 9, 'latitude' => $lat, 'longitude' => $lng,
            ]);
        }

        $ctx = new MigrationContext;
        (new MonitoraLegadoMigrator)->migrar($ctx);
        (new MonitoraLegadoMigrator)->migrar($ctx);

        $this->assertSame(
            3,
            DB::table('monitora_cerca_pontos')->where('cerca_id', 9)->count(),
            'rodar duas vezes duplicou os vértices — o polígono ficaria cruzado',
        );
    }
}
