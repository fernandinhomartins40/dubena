<?php

namespace Tests\Feature;

use App\Domain\Shared\PeriodoPorDia;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * O filtro por dia que troca o `whereDate` em coluna de data e hora.
 *
 * `whereDate` anulava o índice (o "pedidos de hoje" do painel levava 3,1 s
 * varrendo 400 mil linhas); a troca não pode reabrir a armadilha que levou ao
 * `whereDate` — perder o último dia do período (SemanticaTemporalTest).
 */
class PeriodoPorDiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('periodo_teste', function (Blueprint $t) {
            $t->id();
            $t->dateTime('datahora');
        });
        DB::table('periodo_teste')->insert([
            ['id' => 1, 'datahora' => '2026-09-30 23:59:59'], // véspera
            ['id' => 2, 'datahora' => '2026-10-01 00:00:00'], // primeiro instante
            ['id' => 3, 'datahora' => '2026-10-15 12:00:00'],
            ['id' => 4, 'datahora' => '2026-10-31 23:59:59'], // último instante do último dia
            ['id' => 5, 'datahora' => '2026-11-01 00:00:00'], // dia seguinte
        ]);
    }

    private function ids(?string $inicio, ?string $fim): array
    {
        return PeriodoPorDia::aplicar(DB::table('periodo_teste'), 'datahora', $inicio, $fim)
            ->orderBy('id')->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    public function test_o_ultimo_dia_entra_inteiro_e_o_seguinte_fica_de_fora(): void
    {
        $this->assertSame([2, 3, 4], $this->ids('2026-10-01', '2026-10-31'));
    }

    public function test_limite_nulo_deixa_o_lado_aberto(): void
    {
        $this->assertSame([2, 3, 4, 5], $this->ids('2026-10-01', null));
        $this->assertSame([1, 2, 3, 4], $this->ids(null, '2026-10-31'));
        $this->assertSame([1, 2, 3, 4, 5], $this->ids(null, ''));
    }

    public function test_no_dia_pega_so_aquele_dia(): void
    {
        $ids = PeriodoPorDia::noDia(DB::table('periodo_teste'), 'datahora', '2026-10-31')->pluck('id')->map(fn ($i) => (int) $i)->all();
        $this->assertSame([4], $ids);
    }

    /** O limite pode chegar com hora (Carbon, now()): vale o DIA, não o instante. */
    public function test_limite_com_hora_vale_pelo_dia(): void
    {
        $this->assertSame([2, 3, 4], $this->ids('2026-10-01 15:30:00', '2026-10-31 08:00:00'));
    }

    /**
     * Guardião: `whereDate` não volta em coluna de data e hora das tabelas
     * grandes. Reprovava antes da troca em 10 arquivos.
     */
    public function test_nenhum_where_date_em_coluna_de_data_e_hora(): void
    {
        // Colunas `timestamp` (conferido no pg_attribute da homologação). As
        // `date` — vencimento, data_emissao — usam índice com whereDate e ficam.
        $colunas = ['datahora', 'datahora_baixa', 'emitida_em', 'created_at', 'criado_em', 'updated_at'];
        $padrao = "/whereDate\\(\\s*'(?:[a-z_]+\\.)?(".implode('|', $colunas).")'/";
        $achados = [];
        $varridos = 0;

        $arquivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS));
        foreach ($arquivos as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }
            $varridos++;
            foreach (explode("\n", (string) file_get_contents($arquivo->getPathname())) as $n => $linha) {
                if (preg_match($padrao, $linha)) {
                    $achados[] = basename($arquivo->getPathname()).':'.($n + 1);
                }
            }
        }

        $this->assertGreaterThan(200, $varridos, 'a varredura precisa ter varrido algo');
        $this->assertSame([], $achados, 'whereDate em data e hora anula o índice: use PeriodoPorDia::aplicar()');
    }
}
