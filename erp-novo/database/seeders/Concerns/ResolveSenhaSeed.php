<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Str;

/**
 * Política de credenciais de bootstrap (T1.1 do PLANO_PRODUCAO).
 *
 * Antes, os seeders traziam senha default literal no código versionado, e um
 * `.env` de produção mal preenchido nascia com acesso administrativo total
 * conhecido por qualquer um que lesse o repositório — fail-open.
 *
 * A regra passa a ser fail-close: em produção a env var é OBRIGATÓRIA e
 * precisa ter força mínima; fora de produção o default é GERADO na hora e
 * ecoado no console, nunca fixo no código.
 */
trait ResolveSenhaSeed
{
    /** Tamanho mínimo exigido da senha de bootstrap em produção. */
    private int $minimoSenhaSeed = 12;

    /**
     * Resolve o e-mail de bootstrap.
     *
     * O e-mail é identificador, não segredo: mantém default em qualquer
     * ambiente. Quem protege a conta é a senha (ver senhaSeed).
     */
    protected function emailSeed(string $envVar, string $default): string
    {
        $email = trim((string) env($envVar, ''));

        return $email !== '' ? $email : $default;
    }

    /**
     * Resolve a senha de bootstrap.
     *
     * Em produção: exige a env var e no mínimo {$this->minimoSenhaSeed} caracteres.
     * Fora: gera uma senha aleatória e a ecoa no console (o operador precisa vê-la
     * para entrar; ela não fica registrada em lugar nenhum além do output).
     *
     * @throws \RuntimeException em produção quando ausente ou fraca
     */
    protected function senhaSeed(string $envVar, string $rotulo): string
    {
        $senha = (string) env($envVar, '');

        if ($senha !== '') {
            if (app()->environment('production') && mb_strlen($senha) < $this->minimoSenhaSeed) {
                throw new \RuntimeException(
                    "{$envVar} é fraca demais para produção: exige no mínimo {$this->minimoSenhaSeed} caracteres."
                );
            }

            return $senha;
        }

        if (app()->environment('production')) {
            throw new \RuntimeException(
                "{$envVar} é obrigatória em produção: defina-a no .env antes de rodar os seeders de deploy."
            );
        }

        $gerada = Str::random(24);

        $this->command?->warn(
            "{$rotulo}: {$envVar} ausente — senha gerada para este ambiente: {$gerada}"
        );
        $this->command?->warn('Anote agora; ela não será exibida de novo.');

        return $gerada;
    }
}
