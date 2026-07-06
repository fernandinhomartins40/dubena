<?php

namespace App\Domain\Cobranca\Contracts;

/**
 * Driver de PIX (F6 do plano de segurança multi-tenant — GATE bancário).
 *
 * Contrato do PSP: registra a cobrança imediata e devolve os artefatos que o
 * app exibe (copia-e-cola/QR). A CREDENCIAL vem sempre resolvida pela EMPRESA DO
 * RECURSO (pedido/parcela) — nunca do env, nunca do TenantContext ambient (I1).
 *
 * Implementações: FakePixDriver (dev/CI — BR Code sintético, ignora credencial);
 * drivers reais por PSP entram na homologação bancária (PIX_DRIVER=<psp>).
 */
interface PixDriver
{
    /** Nome do driver ('fake', 'itau', …) — telemetria/gates. */
    public function nome(): string;

    /**
     * Registra a cobrança imediata no PSP com a credencial DA EMPRESA.
     *
     * @param  array{txid:string, valor:float, expira_segundos:int}  $dados
     * @param  array{psp:string, client_id:?string, client_secret:?string, chave:?string, ambiente:string}  $credencial
     * @return array{copia_e_cola:string, qrcode:?string}
     */
    public function criarCobranca(array $dados, array $credencial): array;
}
