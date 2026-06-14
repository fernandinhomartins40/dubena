<?php

namespace App\Helpers;

/**
 * Geradores de SQL hierárquico para PostgreSQL.
 *
 * O ERP foi escrito para Oracle e usa START WITH ... CONNECT BY para percorrer
 * a árvore de plano de contas (paiplanoconta_id). O Postgres não tem CONNECT BY;
 * usa WITH RECURSIVE. Estes helpers centralizam a tradução, mantendo as queries
 * dos fechamentos (DRE/Balanço) legíveis e consistentes.
 *
 * Convenção da árvore de planocontas: cada linha tem paiplanoconta_id apontando
 * para o pai; nivel=1 é a raiz da classificação contábil.
 */
class SqlRecursivo
{
    /**
     * Subquery ESCALAR que sobe da conta $startExpr até o ancestral de nivel=1
     * e retorna UMA coluna dele.
     *
     * Equivale ao Oracle:
     *   (select {$coluna} from planocontas where nivel = 1
     *    start with id = {$startExpr} connect by id = prior paiplanoconta_id)
     *
     * @param string $coluna   coluna a retornar (ex.: 'id', 'descricao')
     * @param string $startExpr expressão/coluna do id inicial (ex.: 'plano_id')
     * @return string SQL pronto p/ embutir (já entre parênteses)
     */
    public static function ancestralNivel1Escalar($coluna, $startExpr)
    {
        return "(WITH RECURSIVE sobe AS (" .
            " SELECT id, descricao, nivel, paiplanoconta_id FROM planocontas WHERE id = {$startExpr}" .
            " UNION ALL" .
            " SELECT p.id, p.descricao, p.nivel, p.paiplanoconta_id FROM planocontas p" .
            "   JOIN sobe ON p.id = sobe.paiplanoconta_id" .
            ") SELECT {$coluna} FROM sobe WHERE nivel = 1 LIMIT 1)";
    }

    /**
     * Subquery que retorna TODOS os ids da subárvore (o nó inicial + ancestrais),
     * subindo por paiplanoconta_id a partir de $startInExpr.
     *
     * Equivale ao Oracle:
     *   select id from planocontas
     *   start with id in ({$startInExpr}) connect by prior paiplanoconta_id = id
     *
     * @param string $startInExpr subquery ou lista p/ o START WITH ... IN (...)
     * @return string SQL (não embrulhado em parênteses externos)
     */
    public static function ancestraisPorIn($startInExpr)
    {
        return "WITH RECURSIVE sobe AS (" .
            " SELECT id, paiplanoconta_id FROM planocontas WHERE id in ({$startInExpr})" .
            " UNION ALL" .
            " SELECT p.id, p.paiplanoconta_id FROM planocontas p" .
            "   JOIN sobe ON p.id = sobe.paiplanoconta_id" .
            ") SELECT id FROM sobe";
    }
}
