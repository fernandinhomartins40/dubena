<?php

namespace Tests\Migration;

use App\Etl\Support\RegistraFalhaDeLeitura;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * GATE da T2.6 — falha de leitura da origem deixa de ser silenciosa.
 *
 * Os migrators tinham 72 `catch (\Throwable)` que devolviam vazio sem log e sem
 * contabilizar erro, apagando a diferença entre "a tabela não está no espelho"
 * (esperado em dev/CI) e "a leitura quebrou" (carga incompleta reportada como
 * sucesso). O `AppGasEmCasaMigrator` mostrou a forma destrutiva disso: um catch
 * zerava o mapa de correlação e o migrator recriava a base inteira de clientes.
 */
class FalhaDeLeituraTest extends TestCase
{
    private function sujeito(): object
    {
        return new class
        {
            use RegistraFalhaDeLeitura {
                lerOuAvisar as public;
                avisosDeLeitura as public avisosPublicos;
            }

            public function nome(): string
            {
                return 'migrator-de-teste';
            }
        };
    }

    private function queryException(string $mensagem): QueryException
    {
        return new QueryException('legado', 'select 1', [], new \RuntimeException($mensagem));
    }

    public function test_tabela_ausente_devolve_vazio_sem_aviso(): void
    {
        $m = $this->sujeito();

        $r = $m->lerOuAvisar('tabela x', function () {
            throw $this->queryException('SQLSTATE[42P01]: relation "x" does not exist');
        });

        // Esperado fora do ambiente com dump: quem decide se isso é falha são
        // as invariantes (CountInvariant::hasTable), não o migrator.
        $this->assertSame([], $r);
        $this->assertSame([], $m->avisosPublicos());
    }

    public function test_falha_real_de_query_gera_aviso_e_log(): void
    {
        Log::spy();
        $m = $this->sujeito();

        $r = $m->lerOuAvisar('tabela x', function () {
            throw $this->queryException('SQLSTATE[42703]: column "inexistente" does not exist in WHERE');
        });

        $this->assertSame([], $r, 'o valor vazio ainda é devolvido — o que muda é não ser silencioso');
        $this->assertCount(1, $m->avisosPublicos());
        $this->assertStringContainsString('leitura falhou', $m->avisosPublicos()[0]);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_erro_que_nao_e_de_query_tambem_avisa(): void
    {
        Log::spy();
        $m = $this->sujeito();

        // Conexão caída, timeout, OOM: nada disso é "tabela não existe" e
        // devolver vazio calado seria mentir sobre a carga.
        $r = $m->lerOuAvisar('tabela x', function () {
            throw new \RuntimeException('server closed the connection unexpectedly');
        }, vazio: 0);

        $this->assertSame(0, $r);
        $this->assertCount(1, $m->avisosPublicos());
        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * Conexão de legado ausente é a situação NORMAL em dev/CI — o `phpunit.xml`
     * aponta a conexão para um destino inexistente de propósito, para exercitar
     * o caminho "sem dump". Se isso virasse aviso, o `etl:run` passaria a falhar
     * em todo ambiente sem os bancos legados.
     */
    public function test_conexao_indisponivel_nao_gera_aviso(): void
    {
        $m = $this->sujeito();

        foreach ([
            'could not find driver',
            'SQLSTATE[08006] connection to server at "legado" failed: Connection refused',
            'SQLSTATE[HY000] [2002] No such host is known',
        ] as $mensagem) {
            $r = $m->lerOuAvisar('tabela x', function () use ($mensagem) {
                throw $this->queryException($mensagem);
            });

            $this->assertSame([], $r);
        }

        $this->assertSame([], $m->avisosPublicos(), 'ambiente sem dump não é falha de carga');
    }

    public function test_leitura_bem_sucedida_nao_avisa(): void
    {
        $m = $this->sujeito();

        $r = $m->lerOuAvisar('tabela x', fn () => ['a', 'b']);

        $this->assertSame(['a', 'b'], $r);
        $this->assertSame([], $m->avisosPublicos());
    }
}
