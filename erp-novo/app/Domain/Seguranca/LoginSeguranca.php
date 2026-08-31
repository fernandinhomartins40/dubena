<?php

namespace App\Domain\Seguranca;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Regras de segurança do login (A5): trilha em `login_logs` e LOCKOUT por falhas
 * recentes, além do rate-limit por IP do throttle.
 *
 * O lockout conta falhas em dois eixos, e cada um cobre um ataque diferente:
 *
 *  - por E-MAIL, barra brute-force contra uma conta específica, mesmo que o
 *    atacante troque de IP a cada tentativa;
 *  - por IP, barra varredura de muitos e-mails a partir de um ponto.
 *
 * F2-07 — o contador por IP é escopado ao TENANT do e-mail alvo.
 *
 * Sem esse escopo, o eixo de IP vira uma arma apontada para o próprio cliente:
 * duas revendas atrás do mesmo CGNAT de operadora (comum na cidade pequena, que
 * é justamente o público de um ERP de GLP) compartilham o contador, e a primeira
 * a errar cinco vezes tira a segunda do ar sem saber que ela existe.
 *
 * O escopo preserva as duas defesas — varredura dentro de um tenant continua
 * barrada, ataque a uma conta continua barrado — e só impede que o dano
 * atravesse a fronteira. Que é a diferença entre defender o cliente e puni-lo
 * pelo comportamento de um estranho.
 */
class LoginSeguranca
{
    /** Falhas toleradas na janela antes de bloquear. */
    private const MAX_FALHAS = 5;

    /** Janela de contagem das falhas (minutos). */
    private const JANELA_MIN = 15;

    /** O e-mail/IP está bloqueado por excesso de falhas recentes? */
    public function bloqueado(string $email, string $ip): bool
    {
        $desde = now()->subMinutes(self::JANELA_MIN);

        // Eixo do e-mail: sem escopo, de propósito. A conta é a mesma pessoa em
        // qualquer lugar, e um atacante que troca de IP não pode zerar a conta.
        $porEmail = LoginLog::query()
            ->where('email', $email)->where('sucesso', false)
            ->where('criado_em', '>=', $desde)->count();

        if ($porEmail >= self::MAX_FALHAS) {
            return true;
        }

        $empresaId = $this->empresaDo($email);

        // E-mail que não corresponde a usuário nenhum não tem tenant, e portanto
        // não tem contador de IP. Isso é deliberado: contar essas falhas num
        // balde global daria ao atacante uma alavanca para derrubar um IP
        // inteiro de propósito, bastando enviar e-mails inventados.
        //
        // A conta continua protegida pelo eixo do e-mail acima, e o volume bruto
        // pelo throttle da rota — que é o lugar certo para tratar tráfego sem
        // identidade.
        if ($empresaId === null) {
            return false;
        }

        return LoginLog::query()
            ->where('ip', $ip)
            ->where('empresa_id', $empresaId)
            ->where('sucesso', false)
            ->where('criado_em', '>=', $desde)
            ->count() >= self::MAX_FALHAS;
    }

    /**
     * Empresa do dono do e-mail, ou null se ele não existe.
     *
     * `withoutGlobalScopes`: o login acontece ANTES de haver tenant resolvido —
     * sem isso a consulta não enxergaria usuário nenhum e todo lockout por IP
     * deixaria de valer.
     */
    private function empresaDo(string $email): ?int
    {
        $id = User::withoutGlobalScopes()->where('email', $email)->value('empresa_id');

        return $id !== null ? (int) $id : null;
    }

    /** Registra uma tentativa (sucesso/falha) com o motivo. */
    public function registrar(Request $request, string $email, bool $sucesso, string $motivo, ?int $userId = null, ?int $empresaId = null): void
    {
        LoginLog::create([
            'user_id' => $userId,
            'email' => $email,
            // Falha por senha errada não traz o usuário, e é a mais comum de
            // todas. Sem preencher a empresa aqui, o contador por IP nunca
            // enxergaria justamente as tentativas que deveria contar.
            'empresa_id' => $empresaId ?? $this->empresaDo($email),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'sucesso' => $sucesso,
            'motivo' => $motivo,
        ]);
    }
}
