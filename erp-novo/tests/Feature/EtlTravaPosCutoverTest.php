<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * T6.6.5 — trava contra recarga depois do cutover.
 *
 * **O perigo que ela evita.** A recarga é upsert preservando id: re-rodar
 * `etl:run` sobrescreve qualquer linha de id legado editada no sistema novo.
 * Antes do cutover isso é a característica desejada — é o que torna a recarga
 * final possível. Depois, é destruição silenciosa: o cliente cujo endereço o
 * atendente corrigiu ontem volta ao endereço errado do legado, sem erro, sem
 * log, sem ninguém perceber até a entrega falhar.
 *
 * A detecção é por **evidência no banco** (existe pedido com id acima da faixa
 * do legado), não por flag que alguém precisa lembrar de ligar na hora certa.
 *
 * ⚠️ Estes testes rodam sem conexão `legado` configurada — que é justamente o
 * cenário de dev/CI. Eles fixam o comportamento **seguro na ausência de
 * legado**: não travar. Travar aí impediria o uso normal do comando, e a
 * proteção real só faz sentido quando há uma faixa de ids legada com que
 * comparar.
 */
class EtlTravaPosCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_nunca_e_bloqueado(): void
    {
        // Simular não grava nada — bloquear seria impedir justamente a
        // ferramenta que se usa para verificar se a recarga é segura.
        $codigo = Artisan::call('etl:run', ['--dry-run' => true, 'migrator' => 'estados']);

        $this->assertSame(0, $codigo);
        $this->assertStringNotContainsString('RECARGA BLOQUEADA', Artisan::output());
    }

    public function test_sem_legado_acessivel_nao_trava(): void
    {
        // Sem a origem, não há faixa de ids com que comparar: afirmar "o
        // cutover aconteceu" seria chute. Em dev/CI o legado não existe, e
        // travar aqui quebraria o fluxo normal de trabalho.
        $codigo = Artisan::call('etl:run', ['migrator' => 'estados']);

        $this->assertSame(0, $codigo);
        $this->assertStringNotContainsString('RECARGA BLOQUEADA', Artisan::output());
    }

    public function test_flag_de_escape_existe_e_esta_documentada(): void
    {
        // A trava precisa ter saída: há cenários legítimos de recarga forçada
        // (correção de migrator com backup recente). O que ela impede é a
        // recarga DISTRAÍDA.
        $definicao = Artisan::all()['etl:run']->getDefinition();

        $this->assertTrue($definicao->hasOption('eu-sei-o-que-estou-fazendo'));

        $descricao = $definicao->getOption('eu-sei-o-que-estou-fazendo')->getDescription();
        $this->assertStringContainsString('cutover', mb_strtolower($descricao));
    }
}
