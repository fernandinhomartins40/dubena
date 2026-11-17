<?php

namespace App\Domain\Seguranca;

use Laravel\Sanctum\Sanctum;

/**
 * PIE P2-E-06 / R3-C — token de conta desativada deixa de valer na hora.
 *
 * `ativo` só era lido no LOGIN. Um token emitido antes continuava aceito até
 * expirar (24 h): o funcionário demitido e desativado no painel seguia operando
 * pelo app e pela API, e o admin da plataforma desativado seguia suspendendo
 * revendas. Desativar é o gesto que o dono conhece para cortar um acesso — ele
 * precisa cortar.
 *
 * O ponto é o callback do guard do Sanctum, e não um middleware, porque vale
 * para TODO tokenable (usuário do painel, dos apps e admin da plataforma) em
 * toda rota, inclusive as que alguém criar amanhã sem lembrar desta regra.
 *
 * Só nega quando o modelo diz `ativo = false` explicitamente: tokenable sem a
 * coluna não muda de comportamento.
 */
final class TokenDeContaAtiva
{
    public static function registrar(): void
    {
        Sanctum::authenticateAccessTokensUsing(static function ($token, bool $valido): bool {
            if (! $valido) {
                return false;
            }

            $dono = $token->tokenable;
            if ($dono === null) {
                return false;
            }

            $ativo = $dono->getAttribute('ativo');

            return $ativo === null || (bool) $ativo;
        });
    }
}
