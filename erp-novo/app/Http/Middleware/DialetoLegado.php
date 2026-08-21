<?php

namespace App\Http\Middleware;

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
            return $response;   // PDF (DANFE, boleto) passa intacto
        }

        $status = $response->getStatusCode();
        $corpo = $response->getData(true);

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
