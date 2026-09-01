<?php

namespace App\Domain\Cobranca\Drivers;

use App\Domain\Cobranca\Contracts\PixDriver;
use App\Domain\Tenant\TenantContext;
use App\Models\Empresa;

/**
 * Driver PIX FAKE (dev/homolog/CI). Gera um BR Code sintético determinístico —
 * sem rede, sem credencial. O copia-e-cola de PRODUÇÃO vem do PSP (driver real);
 * este payload existe só para teste/exibição.
 */
class FakePixDriver implements PixDriver
{
    public function nome(): string
    {
        return 'fake';
    }

    public function criarCobranca(array $dados, array $credencial): array
    {
        if (app()->isProduction()) {
            throw new \RuntimeException(
                'FakePixDriver é proibido em produção — nenhuma cobrança PIX sintética foi criada.',
            );
        }

        $txid = (string) $dados['txid'];
        $valor = (float) $dados['valor'];

        // O campo 60 do BR Code é o NOME DO RECEBEDOR, e estava fixo em
        // `GASEMCASA` — a primeira revenda (F8).
        //
        // Aqui é um Fake, então nada é cobrado de verdade. Mas o payload sai na
        // tela e no app do cliente durante toda a homologação: cada revenda que
        // testasse o PIX veria o nome da concorrente como recebedor. E o
        // tamanho vinha fixo em `09` junto com o nome, então trocar um sem o
        // outro produziria um EMV inválido.
        //
        // O nome vem do tenant, com o da plataforma como último recurso —
        // truncado em 25, que é o limite do campo no padrão.
        $recebedor = mb_strtoupper(mb_substr(
            (string) (app(TenantContext::class)->empresaId() !== null
                ? Empresa::query()->find(app(TenantContext::class)->empresaId())?->nome_fantasia
                : null) ?: config('app.name', 'RECEBEDOR'),
            0, 25,
        ));

        // Representação simplificada do payload EMV (BR Code) — antes vivia no
        // PixService::montarBrCode.
        $copiaECola = sprintf('00020126%s52040000530398654%02d%.2f5802BR60%02d%s62%02d%s6304',
            strlen($txid) + 22, strlen(number_format($valor, 2, '.', '')) + 0, $valor,
            strlen($recebedor), $recebedor, strlen($txid) + 4, $txid);

        return ['copia_e_cola' => $copiaECola, 'qrcode' => null];
    }
}
