<?php

namespace App\Console\Commands;

use App\Domain\Legado\UsoDaPonte;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * F9-07 — o relatório que autoriza remover uma ponte.
 *
 * ## O ponto todo é mostrar o ZERO
 *
 * Um relatório que lista só o que foi chamado responde "o que está em uso". A
 * pergunta desta tarefa é a oposta: **o que já pode sair**. Por isso o comando
 * parte das rotas registradas no router — as 29 — e cruza com a medição, em vez
 * de partir da tabela de medição.
 *
 * A diferença não é cosmética. Partindo da tabela, um endpoint que nunca foi
 * chamado simplesmente não aparece, e o silêncio se confunde com ausência de
 * dado. Partindo das rotas, ele aparece com `0` e a leitura é inequívoca.
 *
 * É a mesma armadilha que esta base já pagou duas vezes: registry vazio
 * imprimindo "concluído", e teste que varria zero arquivos passando. Ausência
 * precisa ser afirmada, nunca inferida do vazio.
 *
 * ## O que o número NÃO decide sozinho
 *
 * Zero chamadas em 90 dias autoriza *propor* a remoção, não executá-la. O
 * MovelApp está em `targetSdk 28` e não publica na Play Store: um vendedor com
 * APK antigo pode não ter aberto o app no recorte medido. O comando diz o fato;
 * a remoção continua sendo decisão de quem conhece a operação.
 */
class PonteUso extends Command
{
    protected $signature = 'ponte:uso
        {--dias=90 : tamanho do recorte, em dias}
        {--ponte= : limita a `movelapp` ou `nfweb`}';

    protected $description = 'Mostra o uso das pontes legadas por endpoint, inclusive os com ZERO chamadas (F9-07). Não altera nada.';

    public function handle(UsoDaPonte $uso): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $filtro = $this->option('ponte') ? (string) $this->option('ponte') : null;

        $fim = now()->toDateString();
        $inicio = now()->subDays($dias)->toDateString();

        try {
            $medido = collect($uso->resumo($inicio, $fim, $filtro))
                ->keyBy(fn ($l) => $l['ponte'].'/'.$l['endpoint']);
        } catch (\Throwable $e) {
            // Falha de leitura reprova, e não imprime tabela.
            //
            // Uma tabela com zero em tudo é exatamente o relatório que autoriza
            // remover as 29 rotas. Mostrá-la depois de não conseguir ler seria
            // apresentar a ausência de dado como se fosse a medição.
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rotas = $this->rotasDePonte($filtro);

        if ($rotas === []) {
            // Registry vazio imprimindo sucesso é o defeito que esta base
            // persegue. Se não achei rota nenhuma, o problema é o comando, não
            // a ausência de uso.
            $this->error('Nenhuma rota de ponte encontrada — o comando não está enxergando o router.');

            return self::FAILURE;
        }

        $linhas = [];
        $semUso = 0;

        foreach ($rotas as $chave => $rota) {
            $dado = $medido->get($chave);
            $chamadas = (int) ($dado['chamadas'] ?? 0);

            if ($chamadas === 0) {
                $semUso++;
            }

            $linhas[] = [
                $rota['ponte'],
                $rota['endpoint'],
                $chamadas,
                (int) ($dado['recusas'] ?? 0),
                (int) ($dado['revendas_identificadas'] ?? 0),
                (int) ($dado['chamadas_sem_tenant'] ?? 0),
                $dado['ultima_chamada_em'] ?? '—',
            ];
        }

        // Ordenado por chamadas crescente: quem pode sair primeiro aparece
        // primeiro, que é a pergunta do relatório.
        usort($linhas, fn ($a, $b) => $a[2] <=> $b[2]);

        $this->info("Uso das pontes legadas — últimos {$dias} dias ({$inicio} a {$fim})");
        $this->newLine();

        $this->table(
            // "sem tenant" ao lado de "revendas" porque `login`/`init` nunca
            // têm empresa resolvida: lidas isoladas, as duas colunas mentem.
            ['ponte', 'endpoint', 'chamadas', 'recusas', 'revendas', 'sem tenant', 'última chamada'],
            $linhas,
        );

        $total = count($linhas);
        $this->newLine();
        $this->line("{$semUso} de {$total} endpoints sem nenhuma chamada no recorte.");

        if ($semUso > 0) {
            $this->line('Zero chamadas AUTORIZA PROPOR a remoção, não executá-la: o MovelApp');
            $this->line('não publica na Play Store, e um APK em campo pode não ter aberto no período.');
        }

        return self::SUCCESS;
    }

    /**
     * As rotas de ponte, lidas do router.
     *
     * O critério é o middleware `dialeto.legado` — o mesmo que faz a medição.
     * Casar pelo prefixo da URI seria frágil: uma rota movida de grupo sairia do
     * relatório sem sair da medição, e o relatório passaria a mentir por
     * omissão.
     *
     * @return array<string, array{ponte:string, endpoint:string}>
     */
    private function rotasDePonte(?string $filtro): array
    {
        $saida = [];

        foreach (Route::getRoutes() as $rota) {
            $dialeto = null;

            foreach ($rota->gatherMiddleware() as $m) {
                if (is_string($m) && str_starts_with($m, 'dialeto.legado')) {
                    $dialeto = str_contains($m, ':') ? explode(':', $m, 2)[1] : 'data';
                    break;
                }
            }

            if ($dialeto === null) {
                continue;
            }

            $ponte = $dialeto === 'dados' ? 'movelapp' : 'nfweb';

            if ($filtro !== null && $ponte !== $filtro) {
                continue;
            }

            $endpoint = (string) (last(explode('/', $rota->uri())) ?: $rota->uri());

            $saida[$ponte.'/'.$endpoint] = ['ponte' => $ponte, 'endpoint' => $endpoint];
        }

        return $saida;
    }
}
