<?php

namespace App\Domain\Auditoria;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Leitura da trilha de auditoria para consumo humano.
 *
 * O que está em `audit_logs` é técnico: nome de tabela, verbo do Eloquent e um
 * JSON de colunas. Este serviço traduz para a linha do tempo que o dono do
 * negócio lê — "Maria desativou o cliente João Silva porque mudou de cidade".
 */
class ConsultaTrilha
{
    /** @var list<string> */
    private const CAMPOS_CUSTO_PRODUTO = [
        'custo_medio', 'custo_frete', 'customedio', 'custofrete',
    ];

    /**
     * Linha do tempo geral, com filtros opcionais.
     *
     * @param  array<string,mixed>  $filtros
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function geral(int $empresaId, array $filtros = [], int $porPagina = 50): LengthAwarePaginator
    {
        return $this->base($empresaId, $filtros)->paginate($porPagina);
    }

    /**
     * Trilha de UM registro (ex.: tudo que aconteceu com o cliente 50218).
     *
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function doRegistro(int $empresaId, string $entidade, int $entidadeId, int $porPagina = 100): LengthAwarePaginator
    {
        return $this->base($empresaId, [])
            ->where('entidade', $entidade)
            ->where('entidade_id', $entidadeId)
            ->paginate($porPagina);
    }

    /**
     * Resumo por ação de um registro — alimenta o agrupamento "por tipo de
     * ação" da tela do cliente, sem precisar carregar a trilha inteira.
     *
     * @return array<int, array{acao: string, rotulo: string, total: int, ultima: string|null}>
     */
    public function resumoPorAcao(int $empresaId, string $entidade, int $entidadeId): array
    {
        return AuditLog::query()
            ->where('empresa_id', $empresaId)
            ->where('entidade', $entidade)
            ->where('entidade_id', $entidadeId)
            ->selectRaw('acao, count(*) as total, max(criado_em) as ultima')
            ->groupBy('acao')
            ->orderByDesc('ultima')
            ->get()
            ->map(fn ($l) => [
                'acao' => $l->acao,
                'rotulo' => CatalogoAuditoria::rotuloAcao($l->acao),
                'total' => (int) $l->total,
                'ultima' => $l->ultima,
                'sensivel' => CatalogoAuditoria::acaoSensivel($l->acao),
            ])
            ->all();
    }

    /**
     * @param  array<string,mixed>  $filtros
     * @return Builder<AuditLog>
     */
    private function base(int $empresaId, array $filtros): Builder
    {
        return AuditLog::query()
            // Filtro explícito por empresa ALÉM da RLS (defense-in-depth): a
            // trilha é o registro de quem fez o quê e não pode cruzar tenant.
            ->where('empresa_id', $empresaId)
            ->with('user:id,name')
            ->when($filtros['entidade'] ?? null, fn (Builder $q, $e) => $q->where('entidade', $e))
            ->when($filtros['entidade_id'] ?? null, fn (Builder $q, $id) => $q->where('entidade_id', $id))
            ->when($filtros['acao'] ?? null, fn (Builder $q, $a) => $q->where('acao', $a))
            ->when($filtros['user_id'] ?? null, fn (Builder $q, $u) => $q->where('user_id', $u))
            // Recorte "aquele clique": todas as linhas nascidas da mesma
            // requisição. Continua preso à empresa acima — o fio é um filtro
            // dentro do tenant, nunca uma porta para fora dele.
            ->when($filtros['correlacao'] ?? null, fn (Builder $q, $c) => $q->where('correlation_id', $c))
            ->when($filtros['apenas_sensiveis'] ?? false, fn (Builder $q) => $q->whereIn('acao', CatalogoAuditoria::ACOES_SENSIVEIS))
            // whereDate: `criado_em` é datetime, e comparar com a string 'Y-m-d'
            // perderia o último dia do intervalo (armadilha conhecida do repo).
            ->when($filtros['inicio'] ?? null, fn (Builder $q, $i) => $q->whereDate('criado_em', '>=', $i))
            ->when($filtros['fim'] ?? null, fn (Builder $q, $f) => $q->whereDate('criado_em', '<=', $f))
            ->orderByDesc('criado_em')
            ->orderByDesc('id'); // desempate estável quando o instante é o mesmo
    }

    /**
     * Traduz uma linha da trilha para o formato que a tela exibe.
     *
     * @return array<string, mixed>
     */
    public function apresentar(AuditLog $log, bool $mostrarCusto = true): array
    {
        $depois = $log->depois ?? [];

        return [
            'id' => $log->id,
            'entidade' => $log->entidade,
            'entidade_rotulo' => CatalogoAuditoria::rotuloEntidade($log->entidade),
            'entidade_id' => $log->entidade_id,
            // Nome do registro no momento da ação (gravado pela ação semântica)
            // ou o que estiver no diff — um cliente renomeado depois não deve
            // reescrever o passado da trilha.
            'alvo' => $depois['alvo'] ?? $depois['nome'] ?? ($log->antes['nome'] ?? null),
            'acao' => $log->acao,
            'acao_rotulo' => CatalogoAuditoria::rotuloAcao($log->acao),
            'sensivel' => CatalogoAuditoria::acaoSensivel($log->acao),
            // A coluna vem primeiro; o JSON é o fallback das linhas gravadas
            // antes de `motivo` virar coluna (F2-06). A trilha é append-only:
            // não se reescreve o passado para uniformizar o formato.
            'motivo' => $log->motivo ?? $depois['motivo'] ?? null,
            'autor' => $log->user?->name,
            'autor_id' => $log->user_id,
            // O fio que liga esta linha às outras da mesma ação — é por ele que
            // se responde "o que mais aconteceu naquele clique".
            'correlacao' => $log->correlation_id,
            'ip' => $log->ip,
            'criado_em' => $log->criado_em?->toIso8601String(),
            'alteracoes' => $this->diff($log, $mostrarCusto),
        ];
    }

    /**
     * Payload técnico usado pelo relatório legado, sanitizado na leitura. A
     * trilha armazenada permanece completa para observadores autorizados.
     *
     * @return array{antes: array<string,mixed>, depois: array<string,mixed>}
     */
    public function valoresBrutos(AuditLog $log, bool $mostrarCusto = true): array
    {
        $antes = $log->antes ?? [];
        $depois = $log->depois ?? [];

        if (! $mostrarCusto) {
            $antes = $this->removerCustos($antes);
            $depois = $this->removerCustos($depois);
        }

        return ['antes' => $antes, 'depois' => $depois];
    }

    /**
     * Diff campo a campo, já com rótulo legível e sem os campos de ruído.
     *
     * @return list<array{campo: string, rotulo: string, de: mixed, para: mixed}>
     */
    private function diff(AuditLog $log, bool $mostrarCusto): array
    {
        $antes = $log->antes ?? [];
        $depois = $log->depois ?? [];

        if (! $mostrarCusto) {
            $antes = $this->removerCustos($antes);
            $depois = $this->removerCustos($depois);
        }

        // Chaves de controle da ação semântica não são "campos alterados".
        $ignorar = array_merge(CatalogoAuditoria::CAMPOS_OCULTOS, ['motivo', 'alvo']);
        if (! $mostrarCusto) {
            $ignorar = array_merge($ignorar, self::CAMPOS_CUSTO_PRODUTO);
        }

        $campos = array_diff(
            array_unique(array_merge(array_keys($antes), array_keys($depois))),
            $ignorar,
        );

        $saida = [];
        foreach ($campos as $campo) {
            $de = $antes[$campo] ?? null;
            $para = $depois[$campo] ?? null;
            if ($de === $para) {
                continue;
            }
            // `desativado_por` guarda um id; na trilha ele já aparece como
            // autor da ação, então repetir "1" só confunde.
            if ($campo === 'desativado_por') {
                continue;
            }
            $saida[] = [
                'campo' => $campo,
                'rotulo' => CatalogoAuditoria::rotuloCampo($campo),
                'de' => $this->valorLegivel($campo, $de),
                'para' => $this->valorLegivel($campo, $para),
            ];
        }

        return $saida;
    }

    /** @param array<string,mixed> $dados @return array<string,mixed> */
    private function removerCustos(array $dados): array
    {
        foreach ($dados as $chave => $valor) {
            if (is_string($chave) && in_array($chave, self::CAMPOS_CUSTO_PRODUTO, true)) {
                unset($dados[$chave]);

                continue;
            }

            if (is_array($valor)) {
                $dados[$chave] = $this->removerCustos($valor);
            }
        }

        return $dados;
    }

    /**
     * Valor em linguagem de gente.
     *
     * `ativo` é o caso que mais engana: como boolean cru vira "Sim → Não", que
     * se lê ao contrário do que aconteceu (o cadastro foi DESATIVADO). Campos de
     * estado ganham o vocabulário do domínio.
     */
    private function valorLegivel(string $campo, mixed $v): string
    {
        if ($campo === 'ativo' && is_bool($v)) {
            return $v ? 'Ativo' : 'Desativado';
        }

        if (in_array($campo, ['baixado', 'cancelado'], true) && is_bool($v)) {
            return $v ? 'Sim' : 'Não';
        }

        return match (true) {
            $v === null || $v === '' => '—',
            is_bool($v) => $v ? 'Sim' : 'Não',
            is_array($v) => json_encode($v, JSON_UNESCAPED_UNICODE) ?: '—',
            default => (string) $v,
        };
    }
}
