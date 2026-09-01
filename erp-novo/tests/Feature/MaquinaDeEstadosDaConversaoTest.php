<?php

namespace Tests\Feature;

use App\Etl\Support\RegistroDaConversao;
use App\Etl\Support\SituacaoDaConversao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F7-02 — a máquina de estados da conversão.
 *
 * O plano pede: *"estados felizes e bloqueantes; transição exige pré-condições e
 * CAS/lock. `COMPLETED` não pode ser setado diretamente pelo job"*.
 *
 * ## O que existia
 *
 * As pré-condições viviam no `EtlRun` — invariante reprovada vira `FALHOU`,
 * origem indisponível vira `FALHOU`, o resto vira `CONCLUIDA`. Isso está certo, e
 * continua onde estava: é o comando que sabe o que aconteceu na carga.
 *
 * ## O que faltava
 *
 * A máquina em si. `encerrar()` aceitava **qualquer string** e fazia um `update`
 * **incondicional**:
 *
 *  - `'CONCLUÍDA'` com acento gravaria um estado que nenhuma consulta encontra —
 *    a execução some do gate de cutover sem sumir do banco;
 *  - o último a escrever vencia. Uma execução marcada `INTERROMPIDA` por um
 *    supervisor voltava a `CONCLUIDA` se a thread agonizante ainda escrevesse.
 */
class MaquinaDeEstadosDaConversaoTest extends TestCase
{
    use RefreshDatabase;

    private function registro(): RegistroDaConversao
    {
        return app(RegistroDaConversao::class);
    }

    private function situacao(int $id): string
    {
        return (string) DB::table('conversao_execucoes')->where('id', $id)->value('situacao');
    }

    public function test_encerra_uma_execucao_em_andamento(): void
    {
        $registro = $this->registro();
        $id = (int) $registro->iniciar('clientes', dryRun: false, comInvariantes: true);

        $this->assertSame('EM_ANDAMENTO', $this->situacao($id));
        $this->assertTrue($registro->encerrar('CONCLUIDA', 'ok'));
        $this->assertSame('CONCLUIDA', $this->situacao($id));
    }

    /**
     * O caso que o CAS existe para impedir.
     *
     * Um supervisor marca `INTERROMPIDA` (o processo morreu por OOM); a thread
     * agonizante ainda consegue escrever e chamaria `encerrar('CONCLUIDA')`.
     * Sem o `where` da situação, o último a escrever venceria — e uma carga
     * incompleta apareceria como concluída para o gate de cutover.
     */
    public function test_nao_sobrescreve_desfecho_ja_registrado(): void
    {
        $registro = $this->registro();
        $id = (int) $registro->iniciar(null, false, false);

        // Outro processo chega primeiro.
        DB::table('conversao_execucoes')->where('id', $id)->update([
            'situacao' => 'INTERROMPIDA',
            'encerrada_em' => now(),
        ]);

        $this->assertFalse(
            $registro->encerrar('CONCLUIDA', 'a thread agonizante tentando fechar'),
            'encerrar() precisa devolver false quando outro processo chegou antes',
        );

        $this->assertSame(
            'INTERROMPIDA',
            $this->situacao($id),
            'quem já encerrou, encerrou — o desfecho registrado é a evidência',
        );
    }

    /** Encerrar duas vezes: a segunda não muda nada e diz que não mudou. */
    public function test_encerrar_duas_vezes_e_idempotente_e_honesto(): void
    {
        $registro = $this->registro();
        $id = (int) $registro->iniciar(null, false, false);

        $this->assertTrue($registro->encerrar('FALHOU', 'invariante reprovada'));
        $this->assertFalse($registro->encerrar('CONCLUIDA', 'segunda tentativa'));

        $this->assertSame('FALHOU', $this->situacao($id));
    }

    /**
     * Estado desconhecido LANÇA — não é engolido pelo `catch`.
     *
     * A regra "registro não derruba carga" vale para falha de infraestrutura.
     * `'CONCLUÍDA'` com acento é bug, e gravá-lo em silêncio deixaria a execução
     * invisível para o gate (que procura `CONCLUIDA` exato): some do relatório
     * sem sumir do banco, que é o pior desfecho possível.
     */
    public function test_situacao_desconhecida_lanca(): void
    {
        $registro = $this->registro();
        $registro->iniciar(null, false, false);

        $this->expectException(\InvalidArgumentException::class);
        $registro->encerrar('CONCLUÍDA');   // com acento: o erro de digitação real
    }

    /** `EM_ANDAMENTO` não encerra nada — encerrar exige estado final. */
    public function test_nao_da_para_encerrar_com_estado_nao_final(): void
    {
        $registro = $this->registro();
        $registro->iniciar(null, false, false);

        $this->expectException(\InvalidArgumentException::class);
        $registro->encerrar('EM_ANDAMENTO');
    }

    /** Sem execução aberta, encerrar não inventa uma. */
    public function test_sem_execucao_aberta_devolve_false(): void
    {
        $this->assertFalse($this->registro()->encerrar('CONCLUIDA'));
        $this->assertSame(0, DB::table('conversao_execucoes')->count());
    }

    /**
     * Só `CONCLUIDA` é estado feliz.
     *
     * `FALHOU` e `INTERROMPIDA` são desfechos legítimos — não são erros do
     * registro —, mas nenhum deles libera o cutover. É essa distinção que um
     * script de deploy consulta.
     */
    public function test_apenas_concluida_libera_o_cutover(): void
    {
        $this->assertTrue(SituacaoDaConversao::CONCLUIDA->liberaCutover());

        foreach ([SituacaoDaConversao::EM_ANDAMENTO, SituacaoDaConversao::FALHOU, SituacaoDaConversao::INTERROMPIDA] as $bloqueante) {
            $this->assertFalse(
                $bloqueante->liberaCutover(),
                "{$bloqueante->value} não pode liberar o cutover",
            );
        }
    }

    /** Execução encerrada não reabre: reabrir apagaria a evidência. */
    public function test_estado_final_nao_volta_para_em_andamento(): void
    {
        foreach ([SituacaoDaConversao::CONCLUIDA, SituacaoDaConversao::FALHOU, SituacaoDaConversao::INTERROMPIDA] as $final) {
            $this->assertTrue($final->final());

            $this->assertFalse(
                $final->podeIrPara(SituacaoDaConversao::EM_ANDAMENTO),
                "{$final->value} não pode reabrir",
            );

            $this->assertFalse(
                $final->podeIrPara(SituacaoDaConversao::CONCLUIDA),
                "{$final->value} não pode virar CONCLUIDA depois de encerrado",
            );
        }

        $this->assertTrue(
            SituacaoDaConversao::EM_ANDAMENTO->podeIrPara(SituacaoDaConversao::CONCLUIDA),
        );
    }
}
