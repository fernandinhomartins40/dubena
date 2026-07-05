<?php

namespace App\Domain\Mobile\Drivers;

use App\Domain\Mobile\Contracts\PagamentoDriver;
use Illuminate\Support\Facades\Http;

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
            $aprovado = $resp->successful() && (string) ($j['returnCode'] ?? '') === '00';

            return [
                'aprovado' => $aprovado,
                'tid' => $j['tid'] ?? null,
                'nsu' => $j['nsu'] ?? null,
                'autorizacao' => $j['authorizationCode'] ?? null,
                'bandeira' => $j['brand'] ?? null,
                'mensagem' => (string) ($j['returnMessage'] ?? ($aprovado ? 'Aprovado' : 'Recusado')),
            ];
        } catch (\Throwable $e) {
            return ['aprovado' => false, 'tid' => null, 'nsu' => null, 'autorizacao' => null, 'bandeira' => null, 'mensagem' => $e->getMessage()];
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

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        // Multi-tenant: PV/token vêm da EMPRESA ativa (IntegracaoTenant), não mais
        // do env global — cada revenda cobra pelo SEU credenciamento eRede. Fallback
        // env só para dev/homolog (uma conta de teste).
        $cred = app(\App\Domain\Integracao\IntegracaoTenant::class)->cartao();
        $url = rtrim((string) $cred['url'], '/');

        return Http::timeout(20)->acceptJson()
            ->withBasicAuth((string) $cred['pv'], (string) $cred['token'])
            ->baseUrl($url);
    }
}
