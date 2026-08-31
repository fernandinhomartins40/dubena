<?php

namespace App\Domain\Mobile\Drivers;

use App\Domain\Integracao\IntegracaoTenant;
use App\Domain\Mobile\Contracts\PagamentoDriver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver eRede REAL (F12 — GATE Rede). Autoriza/estorna cartão via API eRede
 * (PV + token). Ativado por PAGAMENTO_DRIVER=erede + EREDE_PV/EREDE_TOKEN; em
 * CI/homolog usa-se o Fake. NÃO exercido pela suíte (gate externo) — homologação
 * real com a adquirente.
 */
class EredeDriver implements PagamentoDriver
{
    public function gateway(): string
    {
        return 'erede';
    }

    /**
     * @param  array{valor:float, parcelas:int, token:string}  $dados
     * @return array{aprovado:bool, tid:?string, nsu:?string, autorizacao:?string, bandeira:?string, mensagem:string}
     */
    public function autorizar(array $dados): array
    {
        // Credencial resolvida FORA do try: sem credencial da empresa (fail-closed
        // da FASE 2) a exceção PROPAGA para virar 503 — não pode ser engolida e
        // virar "recusado" com mensagem interna vazando ao app.
        $client = $this->client();

        try {
            $resp = $client->post('/transactions', [
                'amount' => (int) round($dados['valor'] * 100),
                'installments' => max(1, (int) $dados['parcelas']),
                'cardToken' => $dados['token'],
                'kind' => 'credit',
            ]);

            $j = $resp->json();

            // 5xx é falha DELA, não recusa: o pedido pode ter sido processado
            // antes do erro. Só 4xx e um `returnCode` de negação são resposta.
            if ($resp->serverError()) {
                Log::warning('eRede devolveu erro de servidor — resultado indeterminado.', [
                    'status' => $resp->status(),
                ]);

                return [
                    'aprovado' => false,
                    'indeterminado' => true,
                    'tid' => $j['tid'] ?? null, 'nsu' => null, 'autorizacao' => null, 'bandeira' => null,
                    'mensagem' => 'A operadora respondeu com erro ('.$resp->status().'). Consulte antes de cobrar de novo.',
                ];
            }

            $aprovado = $resp->successful() && (string) ($j['returnCode'] ?? '') === '00';

            return [
                'aprovado' => $aprovado,
                'indeterminado' => false,
                'tid' => $j['tid'] ?? null,
                'nsu' => $j['nsu'] ?? null,
                'autorizacao' => $j['authorizationCode'] ?? null,
                'bandeira' => $j['brand'] ?? null,
                'mensagem' => (string) ($j['returnMessage'] ?? ($aprovado ? 'Aprovado' : 'Recusado')),
            ];
        } catch (\Throwable $e) {
            // F6-08 — rede indisponível NÃO é recusa de negócio.
            //
            // Chegar aqui significa que a operadora não respondeu: conexão
            // recusada, timeout, DNS. E o timeout costuma acontecer *depois* que
            // ela autorizou — é a resposta que se perde no caminho.
            //
            // Devolver isto como recusa simples faria a venda ser refeita, e o
            // cliente seria cobrado duas vezes. `aprovado` continua `false`
            // porque não se entrega mercadoria sobre uma dúvida; `indeterminado`
            // é o que diz que a dúvida existe.
            Log::warning('eRede não respondeu — resultado indeterminado.', [
                'erro' => $e->getMessage(),
                'classe' => $e::class,
            ]);

            return [
                'aprovado' => false,
                'indeterminado' => true,
                'tid' => null, 'nsu' => null, 'autorizacao' => null, 'bandeira' => null,
                'mensagem' => 'A operadora não respondeu. Consulte antes de cobrar de novo: '.$e->getMessage(),
            ];
        }
    }

    /** @return array{cancelado:bool, mensagem:string} */
    public function estornar(string $tid): array
    {
        $client = $this->client();

        try {
            $resp = $client->post("/transactions/{$tid}/refunds", []);
            $j = $resp->json();
            $ok = $resp->successful() && in_array((string) ($j['returnCode'] ?? ''), ['00', '359', '360'], true);

            return ['cancelado' => $ok, 'mensagem' => (string) ($j['returnMessage'] ?? ($ok ? 'Estornado' : 'Falha no estorno'))];
        } catch (\Throwable $e) {
            return ['cancelado' => false, 'mensagem' => $e->getMessage()];
        }
    }

    private function client(): PendingRequest
    {
        // Multi-tenant: PV/token vêm da EMPRESA ativa (IntegracaoTenant), não mais
        // do env global — cada revenda cobra pelo SEU credenciamento eRede. Fallback
        // env só para dev/homolog (uma conta de teste).
        $cred = app(IntegracaoTenant::class)->cartao();
        $url = rtrim((string) $cred['url'], '/');

        return Http::timeout(20)->acceptJson()
            ->withBasicAuth((string) $cred['pv'], (string) $cred['token'])
            ->baseUrl($url);
    }
}
