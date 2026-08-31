<?php

namespace Tests\Feature;

use App\Domain\Cobranca\Cnab\CnabHelper;
use App\Domain\Identidade\NormalizadorTexto;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteTelefone;
use App\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F6-06A — normalização canônica e reparo dos telefones escritos fora do
 * observer.
 *
 * ## As duas metades da tarefa
 *
 * **`iconv('ASCII//TRANSLIT')` depende do locale.** No Windows devolve `?` para
 * acentuado — armadilha já registrada no `CLAUDE.md`. Sobravam duas ocorrências:
 *
 *  - `CnabHelper::semAcento` — o nome do sacado no **boleto impresso**. Sairia
 *    "JOAO" na VPS e "JO?O" em dev, e a divergência apareceria no papel
 *    entregue ao cliente;
 *  - `InconsistenciaService::normalizar` — pior que cosmético, porque este
 *    serviço **compara** textos para achar cadastros duplicados. Com "?" no
 *    lugar do acento, a mesma base produz listas diferentes conforme onde roda.
 *
 * **A ponte NFWEB gravava telefone com `DB::table()->insert()`**, pulando os
 * model events e com eles o `ClienteIdentidadeObserver`. O telefone entrava na
 * tabela sem virar traço de identidade — e o cliente ficava invisível ao motor
 * de deduplicação. O próximo cadastro com o mesmo número viraria duplicata
 * **sem sequer ser comparado**, que é exatamente o que o motor existe para
 * evitar.
 */
class NormalizacaoLegadaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * O CNAB precisa de ASCII **preservando** pontuação e caixa: o layout aceita
     * `.`, `,`, `-` e `/` no nome, e o boleto tem de sair igual ao cadastro.
     */
    public function test_sem_acento_preserva_pontuacao_e_caixa(): void
    {
        $this->assertSame('Endereco', NormalizadorTexto::semAcento('Endereço'));
        $this->assertSame('JOAO DA SILVA', NormalizadorTexto::semAcento('JOÃO DA SILVA'));
        $this->assertSame('Jose Angelo', NormalizadorTexto::semAcento('José Ângelo'));
        $this->assertSame('IND. E COM. LTDA', NormalizadorTexto::semAcento('IND. E COM. LTDA'));
    }

    /** Texto já ASCII passa intacto — nada de normalização a mais. */
    public function test_texto_sem_acento_nao_e_alterado(): void
    {
        $this->assertSame('ABC 123 - X/Y', NormalizadorTexto::semAcento('ABC 123 - X/Y'));
        $this->assertSame('', NormalizadorTexto::semAcento(null));
    }

    /**
     * O campo do CNAB sai sem acento e sem `?`.
     *
     * O `?` é o resultado do `iconv` no Windows, e é o que este teste impede de
     * voltar — um boleto com "JO?O SILVA" no nome do sacado.
     */
    public function test_campo_cnab_sai_sem_acento_e_sem_interrogacao(): void
    {
        $campo = CnabHelper::texto('João Conceição & Cia', 30);

        $this->assertStringNotContainsString('?', $campo, 'o TRANSLIT do Windows deixaria "?" aqui');
        $this->assertStringContainsString('JOAO CONCEICAO', $campo);
        $this->assertSame(30, strlen($campo), 'largura fixa do layout');
    }

    /** A pontuação que o layout aceita sobrevive. */
    public function test_campo_cnab_preserva_a_pontuacao_do_layout(): void
    {
        $campo = CnabHelper::texto('IND. E COM. LTDA', 20);

        $this->assertStringContainsString('IND. E COM. LTDA', $campo);
    }

    /**
     * A ponte NFWEB grava telefone pelo model — e o traço de identidade nasce.
     *
     * Este é o teste da correção: com `DB::table()->insert()` o telefone entrava
     * e o traço não.
     */
    public function test_telefone_gravado_pelo_model_vira_traco_de_identidade(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);

        ClienteTelefone::create([
            'cliente_id' => $cliente->id,
            'telefone' => '42999887766',
        ]);

        $this->assertSame(
            1,
            DB::table('cliente_identidades')
                ->where('cliente_id', $cliente->id)
                ->where('tipo', 'telefone')
                ->count(),
            'o observer precisa ter rodado',
        );
    }

    /**
     * O reparo reconstrói o traço de quem foi escrito fora do observer.
     *
     * O cenário é o real: a linha existe na tabela, e o traço não — porque a
     * escrita crua pulou os model events.
     */
    public function test_o_reparo_reconstroi_tracos_de_quem_ficou_sem(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'nome' => 'Maria Aparecida',
        ]);

        // Escrita crua, como a ponte NFWEB fazia: pula o observer.
        DB::table('clientetelefones')->insert([
            'cliente_id' => $cliente->id, 'empresa_id' => $empresa->id,
            'telefone' => '42988776655', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('cliente_identidades')->where('cliente_id', $cliente->id)->delete();

        $this->assertSame(0, DB::table('cliente_identidades')->where('cliente_id', $cliente->id)->count());

        $this->artisan('identidade:reparar')->assertSuccessful();

        $this->assertGreaterThan(
            0,
            DB::table('cliente_identidades')->where('cliente_id', $cliente->id)->count(),
            'o cliente volta a ser visível ao motor de identidade',
        );
    }

    /** `--dry-run` conta e não escreve. */
    public function test_dry_run_nao_escreve(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        DB::table('cliente_identidades')->where('cliente_id', $cliente->id)->delete();

        $this->artisan('identidade:reparar --dry-run')
            ->expectsOutputToContain('sem traço')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('cliente_identidades')->where('cliente_id', $cliente->id)->count());
    }

    /** Reparar duas vezes não duplica traço — a operação é idempotente. */
    public function test_reparar_duas_vezes_nao_duplica(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = Cliente::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
        ]);
        DB::table('cliente_identidades')->where('cliente_id', $cliente->id)->delete();

        $this->artisan('identidade:reparar')->assertSuccessful();
        $primeira = DB::table('cliente_identidades')->where('cliente_id', $cliente->id)->count();

        $this->artisan('identidade:reparar')->assertSuccessful();

        $this->assertSame(
            $primeira,
            DB::table('cliente_identidades')->where('cliente_id', $cliente->id)->count(),
        );
    }

    /**
     * Guardião: nenhum `iconv(...TRANSLIT)` volta ao código.
     *
     * A tarefa nomeia a substituição, e o defeito é do tipo que reaparece —
     * `iconv` é a forma óbvia de tirar acento, e o resultado errado só acontece
     * em Windows, então passa despercebido em revisão feita no Linux.
     */
    public function test_nenhum_iconv_translit_sobrou_no_codigo(): void
    {
        $achados = [];
        $varridos = 0;

        $arquivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($arquivos as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }

            $varridos++;
            $conteudo = (string) file_get_contents($arquivo->getPathname());

            foreach (explode("\n", $conteudo) as $n => $linha) {
                $semEspaco = ltrim($linha);

                // Comentário não é chamada — e as cinco ocorrências que restam
                // no código são justamente as notas explicando POR QUE não se
                // usa `iconv`. Essas são o registro da decisão: acusá-las
                // levaria alguém a apagar a explicação para calar o teste, que é
                // o pior desfecho possível.
                if (str_starts_with($semEspaco, '*')
                    || str_starts_with($semEspaco, '//')
                    || str_starts_with($semEspaco, '/*')) {
                    continue;
                }

                if (preg_match('/iconv\s*\(/', $linha)) {
                    $achados[] = basename($arquivo->getPathname()).':'.($n + 1);
                }
            }
        }

        $this->assertGreaterThan(200, $varridos, 'a varredura precisa ter varrido algo');
        $this->assertSame([], $achados, 'use NormalizadorTexto: iconv//TRANSLIT depende do locale');
    }
}
