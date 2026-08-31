<?php

namespace App\Domain\Mobile\Drivers;

use App\Domain\Mobile\Contracts\PagamentoDriver;
use Illuminate\Support\Str;

/**
 * Driver de pagamento para DEV/HOMOLOG/CI (sem gateway real). Aprova exceto quando
 * o token começa com "nego" (para testar a recusa). NÃO usar em produção: lá entra
 * o driver real da Rede (porta eRede + PV/token).
 */
class FakePagamentoDriver implements PagamentoDriver
{
    /**
     * F5-05 — defesa em profundidade: o fake nao existe em producao.
     *
     * O container ja recusa a configuracao que ativaria um fake em producao
     * (`exigirDriverReal`), e essa e a protecao principal. Esta guarda cobre o
     * caminho que ela nao alcanca: instanciar a classe diretamente, sem passar
     * pela resolucao por interface.
     *
     * Duas travas para o mesmo risco porque o custo de errar aqui e pagamento sintetico
     * entrando na operacao real — e o modo de falhar e silencioso: o sistema
     * responde "sucesso" e nenhum pagamento sintetico foi autorizado de verdade.
     */
    public function __construct()
    {
        if (app()->isProduction()) {
            throw new \RuntimeException(
                static::class.' e proibido em producao — nenhum pagamento sintetico foi autorizado.',
            );
        }
    }

    public function gateway(): string
    {
        return 'fake';
    }

    public function autorizar(array $dados): array
    {
        $aprovado = ! str_starts_with(strtolower((string) ($dados['token'] ?? '')), 'nego');

        return [
            'aprovado' => $aprovado,
            'tid' => $aprovado ? Str::upper(Str::random(20)) : null,
            'nsu' => $aprovado ? (string) random_int(100000, 999999) : null,
            'autorizacao' => $aprovado ? (string) random_int(100000, 999999) : null,
            'bandeira' => 'VISA',
            'mensagem' => $aprovado ? 'Autorizado' : 'Transação negada',
        ];
    }

    public function estornar(string $tid): array
    {
        return ['cancelado' => true, 'mensagem' => 'Estorno confirmado (teste).'];
    }
}
