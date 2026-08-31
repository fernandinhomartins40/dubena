<?php

namespace App\Domain\Financeiro;

use App\Models\Financeiro\PlanoConta;
use Illuminate\Support\Facades\DB;

/**
 * F5-01 — copia o plano de contas modelo para uma revenda.
 *
 * ## O ponto de partida que faltava
 *
 * Uma revenda nova entrava no SaaS com a árvore vazia: o DRE agrupava tudo em
 * "Sem plano" e a conciliação contábil não tinha para onde apontar. O sistema
 * funcionava e não servia.
 *
 * ## Copia, não referencia
 *
 * As linhas viram registros próprios em `planos_conta`, do grupo. A revenda
 * renomeia, desativa e acrescenta o que quiser sem afetar ninguém — e uma
 * correção posterior no modelo **não** reescreve o que ela já ajustou.
 *
 * O contrário (apontar para o catálogo) seria mais barato e errado: a revenda
 * veria sua própria contabilidade mudar sozinha num deploy da plataforma.
 *
 * ## Idempotente por descrição
 *
 * Copiar duas vezes não duplica. A chave natural é `(grupo, descrição,
 * pagarreceber)`, não o código: código é o campo que a revenda mais mexe — ela
 * numera do jeito do contador dela —, e um `firstOrCreate` por código
 * recriaria a árvore inteira depois da primeira renumeração.
 */
class PlanoContaModeloService
{
    /**
     * Copia o modelo para o grupo. Devolve quantas linhas foram criadas.
     *
     * `$tenantAccountId` é propagado porque a trigger de hierarquia (F1-08)
     * recusa pai e filho de tenants distintos — copiar só os pais com tenant
     * deixaria os filhos órfãos e a inserção falharia no meio.
     */
    public function copiarParaGrupo(int $grupoId, ?int $tenantAccountId = null, string $perfil = 'padrao'): int
    {
        $modelo = DB::table('plano_conta_modelos')
            ->where('perfil', $perfil)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->get();

        if ($modelo->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($modelo, $grupoId, $tenantAccountId) {
            // Mapa do id no MODELO para o id criado no grupo. Sem ele, `pai_id`
            // apontaria para a linha do catálogo de plataforma — que é de outra
            // tabela, e a FK nem aceitaria.
            $mapa = [];
            $criadas = 0;

            foreach ($modelo as $linha) {
                $paiId = $linha->pai_id !== null ? ($mapa[$linha->pai_id] ?? null) : null;

                // O modelo vem ordenado por `ordem`, e os pais têm ordem menor
                // que a dos filhos. Se ainda assim um filho chegar antes do pai,
                // é erro de catálogo: criar o filho na raiz esconderia isso e
                // produziria uma árvore torta que ninguém sabe explicar.
                if ($linha->pai_id !== null && $paiId === null) {
                    continue;
                }

                $existente = PlanoConta::withoutGrupo()
                    ->where('grupo_id', $grupoId)
                    ->where('descricao', $linha->descricao)
                    ->where('pagarreceber', $linha->pagarreceber)
                    ->first();

                if ($existente !== null) {
                    $mapa[$linha->id] = $existente->id;

                    continue;
                }

                $criada = PlanoConta::withoutGrupo()->create([
                    'grupo_id' => $grupoId,
                    'tenant_account_id' => $tenantAccountId,
                    'pai_id' => $paiId,
                    'codigo' => $linha->codigo,
                    'descricao' => $linha->descricao,
                    'pagarreceber' => $linha->pagarreceber,
                    'nivel' => $linha->nivel,
                    'ativo' => true,
                ]);

                $mapa[$linha->id] = $criada->id;
                $criadas++;
            }

            return $criadas;
        });
    }
}
