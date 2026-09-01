<?php

namespace App\Http\Middleware;

use App\Domain\Legado\UsoDaPonte;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F0 — traduz a resposta do erp-novo para o dialeto que os apps legados esperam.
 *
 * **O problema.** MovelApp e NFWEB só falam com o ctrl-web, e o contrato dele é
 * o oposto do nosso:
 *
 *  | | ctrl-web | erp-novo |
 *  |---|---|---|
 *  | sucesso | `{data, msg, status:"OK"}` HTTP 200 | `{data}` 200/201 |
 *  | erro | `{msg, status:"NOK"}` **HTTP 200** | `{message}` HTTP 4xx/5xx |
 *  | recusa de negócio | `{msg, status:"OPS"}` **HTTP 200** | `{message}` HTTP 422 |
 *
 * Um app legado apontado direto ao erp-novo receberia 4xx onde espera 200 e
 * trataria recusa legítima ("sem limite no convênio") como falha de rede.
 *
 * **Dois dialetos, não um.** Lendo o legado: `customHelper::responseSuccess`
 * devolve a carga em `data` (o NFWEB lê `data`, Http.js:164), mas o
 * `ApiController` — que atende o MovelApp — devolve em **`dados`**
 * (ApiController::getVeiculos:169, e CadastroImportActivity lê
 * `json.getJSONArray("dados")`). A ponte precisa dos dois; por isso o parâmetro
 * de chave.
 *
 * **O que NÃO é traduzido:** o IDOR de tenant. O legado confia no `revenda_id`
 * que o app envia (`ApiController:34` — `Empresa::find($data['revenda_id'])` sem
 * checar o token). Aqui o tenant continua saindo do token; `revenda_id` é aceito
 * e VALIDADO (ver ValidaRevendaLegado). Compatibilidade de formato, nunca de
 * vulnerabilidade.
 *
 * Este middleware é ponte com data para morrer: quando o app unificado
 * substituir os dois legados (F9), ele sai junto.
 */
class DialetoLegado
{
    public function handle(Request $request, Closure $next, string $chave = 'data'): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse) {
            // PDF (DANFE, boleto) passa intacto — mas CONTA. `visualizarDanfe`
            // e `visualizarBoleto` são endpoints como os outros, e deixá-los
            // fora da medição faria dois deles parecerem mortos (F9-07).
            $this->medir($request, $chave, recusada: false);

            return $response;
        }

        $status = $response->getStatusCode();
        $corpo = $response->getData(true);

        // A recusa de REGRA (422 → `OPS`) conta separada da chamada bem
        // sucedida: endpoint muito chamado e sempre recusado não é uso, é app
        // velho insistindo — e lê-lo como "está em uso" adiaria a remoção para
        // sempre.
        $this->medir($request, $chave, recusada: $status === 422);

        // 2xx → OK. A carga do erp-novo vem em `data`; o legado espera em
        // `data` (NFWEB) ou `dados` (MovelApp).
        if ($status >= 200 && $status < 300) {
            return response()->json([
                $chave => $corpo['data'] ?? $corpo,
                'msg' => 'Sucesso!',
                'status' => 'OK',
            ], 200);
        }

        // 422 = DomainException/validação: o domínio dizendo "isto não pode".
        // É o que o legado chama de OPS — recusa de REGRA, que o app trata como
        // resposta válida e mostra ao operador (Http.js:164). Confundir isto com
        // NOK faria o vendedor ver "erro de conexão" no lugar de "sem limite no
        // convênio".
        $tipo = $status === 422 ? 'OPS' : 'NOK';

        return response()->json([
            'msg' => $this->mensagem($corpo),
            'status' => $tipo,
        ], 200);   // sempre 200: é o contrato do legado
    }

    /**
     * Conta a chamada (F9-07).
     *
     * Fica AQUI, e não em cada controller, porque este middleware já envolve as
     * 29 rotas de ponte: um endpoint novo passa a ser medido sem que ninguém
     * lembre de instrumentá-lo. Instrumentação que depende de lembrar é
     * instrumentação que fica incompleta — e uma medição incompleta é pior que
     * nenhuma, porque autoriza remover o que ela não viu.
     *
     * A chave do dialeto identifica a ponte: `dados` é o MovelApp, `data` é o
     * NFWEB (ver a tabela no topo da classe). É o mesmo parâmetro que a rota já
     * declara, então não há um segundo lugar para manter em sincronia.
     *
     * ## Síncrono, e não em fila
     *
     * A escrita acontece no ciclo da resposta, somando latência a um app que faz
     * polling. É 1 SELECT + 1 UPDATE numa tabela indexada e agregada por dia —
     * custo fixo, não proporcional ao volume, porque a mesma linha é
     * incrementada o dia inteiro.
     *
     * Uma fila tiraria isso do caminho crítico ao preço de um job por chamada
     * de ponte, com a fila competindo com a entrega de pedido. Se a medição
     * aparecer no tempo de resposta, o passo certo é medir antes de mover.
     */
    private function medir(Request $request, string $chave, bool $recusada): void
    {
        $endpoint = $request->route()?->uri();

        if ($endpoint === null) {
            return;
        }

        // O nome no dialeto do legado é o último segmento — é ele que está
        // compilado dentro do APK, e é por ele que se decide o que pode sair.
        $endpoint = (string) (last(explode('/', $endpoint)) ?: $endpoint);

        app(UsoDaPonte::class)->registrar(
            ponte: $chave === 'dados' ? 'movelapp' : 'nfweb',
            endpoint: $endpoint,
            empresaId: $request->user()?->empresa_id,
            recusada: $recusada,
            // Os APKs mais antigos não mandam versão; nulo é o caso esperado, e
            // o resumo trata isso como "versão desconhecida" em vez de fingir.
            versaoApp: $request->header('X-App-Versao'),
        );
    }

    /**
     * A mensagem que o operador lê. Erro de validação do Laravel vem em `errors`
     * (campo => [mensagens]); achatamos para uma linha, porque a tela do app
     * legado mostra string única.
     *
     * @param  array<string,mixed>  $corpo
     */
    private function mensagem(array $corpo): string
    {
        if (isset($corpo['errors']) && is_array($corpo['errors'])) {
            $primeiro = collect($corpo['errors'])->flatten()->first();
            if (is_string($primeiro) && $primeiro !== '') {
                return $primeiro;
            }
        }

        return (string) ($corpo['message'] ?? $corpo['msg'] ?? 'Erro ao processar a requisição.');
    }
}
