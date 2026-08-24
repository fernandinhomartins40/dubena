<?php

namespace App\Domain\Alerta;

use App\Models\Alerta;
use Illuminate\Support\Facades\DB;

/**
 * A central de alertas — fila de averiguação que a equipe tria.
 *
 * **A regra que define o desenho: deduplicar.** Um cron que roda toda semana
 * sobre o mesmo cliente parado geraria 52 alertas idênticos por ano. A equipe
 * pararia de olhar a tela na terceira semana, e o alerta que importa se perderia
 * no meio. Por isso existe `chave`: enquanto houver um alerta PENDENTE com
 * aquela chave, a rodada seguinte ATUALIZA (número novo, contador de
 * ocorrências) em vez de criar outro.
 *
 * **Ocorrências contam história.** Um alerta que persiste há 12 rodadas diz algo
 * diferente de um que apareceu ontem — e é o que separa "cliente entrou em
 * férias" de "vasilhame não volta há três meses".
 *
 * **Resolver não é apagar.** Alerta triado vira histórico com quem decidiu e por
 * quê. Se o mesmo problema voltar depois, abre um alerta novo — e o anterior
 * continua provando que alguém olhou.
 */
class AlertaService
{
    /**
     * Cria ou atualiza um alerta pela chave.
     *
     * @param  array<string,mixed>  $atributos
     */
    public function registrar(int $empresaId, int $grupoId, string $origem, string $chave, array $atributos): Alerta
    {
        return DB::transaction(function () use ($empresaId, $grupoId, $origem, $chave, $atributos) {
            $existente = Alerta::query()
                ->where('empresa_id', $empresaId)
                ->where('chave', $chave)
                ->whereIn('situacao', [Alerta::ABERTO, Alerta::EM_ANALISE])
                ->first();

            if ($existente !== null) {
                // Os números são recalculados a cada rodada; o que NÃO se mexe é
                // a situação e o responsável — sobrescrevê-los devolveria para a
                // fila um alerta que alguém já assumiu.
                $existente->forceFill(array_merge(
                    array_diff_key($atributos, array_flip(['situacao', 'responsavel_user_id'])),
                    [
                        'ocorrencias' => $existente->ocorrencias + 1,
                        'ultima_ocorrencia' => now(),
                    ],
                ))->save();

                return $existente->refresh();
            }

            return Alerta::create(array_merge([
                'empresa_id' => $empresaId,
                'grupo_id' => $grupoId,
                'origem' => $origem,
                'chave' => $chave,
                'situacao' => Alerta::ABERTO,
                'ocorrencias' => 1,
                'ultima_ocorrencia' => now(),
            ], $atributos));
        });
    }

    /**
     * Fecha alertas de uma origem cuja causa deixou de existir.
     *
     * O cliente voltou a comprar, o comodato foi devolvido, o vencimento foi
     * renovado — o alerta some sozinho. Sem isto a fila só cresceria, e um
     * problema resolvido continuaria pedindo visita.
     *
     * @param  list<string>  $chavesVivas  as que a rodada atual reafirmou
     */
    public function encerrarAusentes(int $empresaId, string $origem, array $chavesVivas): int
    {
        $query = Alerta::query()
            ->where('empresa_id', $empresaId)
            ->where('origem', $origem)
            ->whereIn('situacao', [Alerta::ABERTO, Alerta::EM_ANALISE]);

        if ($chavesVivas !== []) {
            $query->whereNotIn('chave', $chavesVivas);
        }

        return $query->update([
            'situacao' => Alerta::RESOLVIDO,
            'resolucao' => 'Encerrado automaticamente: a condição que originou o alerta deixou de existir.',
            'resolvido_em' => now(),
        ]);
    }

    /** Alguém assumiu a averiguação. */
    public function assumir(Alerta $alerta, int $userId): Alerta
    {
        $alerta->update([
            'situacao' => Alerta::EM_ANALISE,
            'responsavel_user_id' => $userId,
        ]);

        return $alerta->refresh();
    }

    /**
     * Encerra com o desfecho.
     *
     * `IGNORADO` é desfecho legítimo e distinto de `RESOLVIDO`: "o cliente
     * fechou as férias coletivas, é normal" não é a mesma coisa que "recolhemos
     * os vasilhames". Contar os dois juntos esconderia uma régua mal calibrada.
     */
    public function encerrar(Alerta $alerta, string $situacao, ?string $resolucao, int $userId): Alerta
    {
        $alerta->update([
            'situacao' => $situacao,
            'resolucao' => $resolucao,
            'resolvido_em' => now(),
            'resolvido_por' => $userId,
        ]);

        return $alerta->refresh();
    }

    /**
     * Contagem por situação e severidade — o cabeçalho da central.
     *
     * @return array<string,int>
     */
    public function resumo(int $empresaId): array
    {
        $linhas = Alerta::query()
            ->where('empresa_id', $empresaId)
            ->selectRaw('situacao, severidade, count(*) as n')
            ->groupBy('situacao', 'severidade')
            ->get();

        $resumo = ['abertos' => 0, 'em_analise' => 0, 'alta' => 0, 'media' => 0, 'baixa' => 0];

        foreach ($linhas as $l) {
            if ($l->situacao === Alerta::ABERTO) {
                $resumo['abertos'] += $l->n;
            }
            if ($l->situacao === Alerta::EM_ANALISE) {
                $resumo['em_analise'] += $l->n;
            }
            if (in_array($l->situacao, [Alerta::ABERTO, Alerta::EM_ANALISE], true)) {
                $resumo[mb_strtolower($l->severidade)] = ($resumo[mb_strtolower($l->severidade)] ?? 0) + $l->n;
            }
        }

        return $resumo;
    }
}
