<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * As rotas continuam cacheáveis? (`php artisan route:cache`)
 *
 * Existe por causa de um custo concreto: sem `route:cache`, TODA requisição —
 * e todo processo do scheduler, que são muitos por minuto — reparseia as ~1000
 * linhas de routes/api.php do zero. Foi parte do que levou a VPS a load 293 e à
 * limitação de CPU imposta pelo provedor.
 *
 * O detalhe que torna este guardião necessário: uma closure de ação numa rota
 * faz `route:cache` FALHAR, e a falha aparece no deploy, não em quem escreveu a
 * rota. Como o entrypoint roda o cache em produção, uma closure adicionada hoje
 * só se manifestaria na próxima subida de container — longe da mudança que a
 * causou, e com o container sem subir.
 */
class RotasCacheaveisTest extends TestCase
{
    public function test_nenhuma_rota_usa_closure_como_acao(): void
    {
        $comClosure = [];
        $total = 0;

        foreach (Route::getRoutes() as $rota) {
            $total++;

            // Rotas do proprio framework (`up` do health check, `storage/{path}`
            // do storage:link em dev) sao closures e continuam cacheaveis — o
            // Laravel sabe serializar as suas. Medir essas so faria o guardiao
            // falhar por algo que ninguem aqui pode corrigir.
            if (in_array($rota->uri(), ['up', 'storage/{path}'], true)) {
                continue;
            }

            // `uses` é string ("Controller@metodo") quando a ação é um
            // controller, e Closure quando está escrita inline na rota.
            if (($rota->getAction()['uses'] ?? null) instanceof \Closure) {
                $comClosure[] = $rota->methods()[0].' '.$rota->uri();
            }
        }

        // Sem este piso, um dia em que as rotas não carregassem (bootstrap
        // quebrado, provider removido) daria verde varrendo zero rotas — o
        // guardião passaria a proteger nada, em silêncio.
        $this->assertGreaterThan(400, $total, 'Poucas rotas carregadas: a varredura não está vendo o roteador real.');

        $this->assertSame([], $comClosure,
            "Rota(s) com closure de ação impedem `route:cache`. Mova para um controller:\n- ".
            implode("\n- ", $comClosure));
    }
}
