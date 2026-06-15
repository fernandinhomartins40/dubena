<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Gravação de colunas binárias (bytea no PostgreSQL).
 *
 * O ERP foi escrito para Oracle e usava `->updateLob([], ['col' => $bin])`
 * (macro do driver oci8 yajra) para gravar BLOBs. Esse método NÃO existe no
 * driver pgsql — quebra em produção. No Postgres a coluna virou `bytea`
 * (ver App\Helpers\MigrationHelper::addBinary) e o valor binário precisa ser
 * enviado via bind PDO::PARAM_LOB para não corromper os bytes.
 *
 * Este helper centraliza essa gravação, substituindo as 8 chamadas legadas
 * de updateLob por uma tradução única e testável.
 */
class BlobWriter
{
    /**
     * Atualiza uma coluna bytea de uma tabela para o id informado.
     *
     * @param string $table  nome da tabela (ex.: 'empresas')
     * @param int    $id     valor da PK (coluna id)
     * @param string $column coluna bytea (ex.: 'logoimg')
     * @param string $binary conteúdo binário cru (ex.: base64_decode(...))
     * @return bool
     */
    public static function update($table, $id, $column, $binary)
    {
        $pdo = DB::connection()->getPdo();
        // Aspas duplas: identificadores; valores via bind para evitar SQLi/corrupção.
        $sql = "UPDATE {$table} SET {$column} = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $binary, PDO::PARAM_LOB);
        $stmt->bindValue(2, (int) $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
