<?php

namespace App\Monitora\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helper de migração — FASE 3 (migração Oracle/MySQL → PostgreSQL).
 *
 * Centraliza conversões de DDL que diferem entre bancos, mantendo as migrations
 * legíveis e portáveis. Cada método é no-op ou faz o equivalente correto
 * conforme o driver da conexão ativa.
 */
class MigrationHelper
{
    /** Driver da conexão padrão (pgsql, oracle, mysql...). */
    public static function driver()
    {
        return DB::connection()->getDriverName();
    }

    public static function isPgsql()
    {
        return self::driver() === 'pgsql';
    }

    public static function isOracle()
    {
        return self::driver() === 'oracle';
    }

    /**
     * Converte uma coluna para boolean.
     * No PostgreSQL exige USING explícito (não há cast implícito de int→bool).
     * Trata valores 1/0, 't'/'f', 'true'/'false'.
     *
     * @param string $table
     * @param string $column
     * @param bool   $nullable
     * @param int|null $default 1/0 ou null
     */
    public static function toBoolean($table, $column, $nullable = true, $default = 1)
    {
        if (self::isPgsql()) {
            $using = "CASE WHEN {$column}::text IN ('1','t','true','TRUE','y','Y') THEN true " .
                     "WHEN {$column} IS NULL THEN NULL ELSE false END";
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE boolean USING ({$using})");

            if (!is_null($default)) {
                $def = $default ? 'true' : 'false';
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$def}");
            }
            if ($nullable) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL");
            } else {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
            }
            return;
        }

        // Outros bancos: caminho padrão do Laravel (requer doctrine/dbal).
        Schema::table($table, function ($t) use ($column, $nullable, $default) {
            $col = $t->boolean($column);
            if ($nullable) $col->nullable();
            if (!is_null($default)) $col->default($default);
            $col->change();
        });
    }

    /**
     * Converte uma coluna para numeric/decimal.
     * No PostgreSQL exige USING explícito quando a origem é texto.
     * Trata vírgula decimal e strings vazias (→ NULL).
     *
     * @param string $table
     * @param string $column
     * @param int $precision
     * @param int $scale
     * @param bool $nullable
     */
    public static function toDecimal($table, $column, $precision = 15, $scale = 4, $nullable = true)
    {
        if (self::isPgsql()) {
            // Normaliza: vírgula→ponto, vazio→NULL, antes do cast numérico.
            $using = "NULLIF(replace({$column}::text, ',', '.'), '')::numeric({$precision},{$scale})";
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE numeric({$precision},{$scale}) USING ({$using})");
            if ($nullable) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL");
            }
            return;
        }

        Schema::table($table, function ($t) use ($column, $precision, $scale, $nullable) {
            $col = $t->decimal($column, $precision, $scale);
            if ($nullable) $col->nullable();
            $col->change();
        });
    }

    /**
     * Converte uma coluna para double precision (lat/long etc.).
     * No PostgreSQL exige USING explícito quando a origem é texto.
     */
    public static function toDouble($table, $column, $nullable = true)
    {
        if (self::isPgsql()) {
            $using = "NULLIF(replace({$column}::text, ',', '.'), '')::double precision";
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE double precision USING ({$using})");
            if ($nullable) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL");
            }
            return;
        }
        Schema::table($table, function ($t) use ($column, $nullable) {
            $col = $t->double($column, 23, 15);
            if ($nullable) $col->nullable();
            $col->change();
        });
    }

    /**
     * Adiciona uma coluna binária (Oracle BLOB → bytea no Postgres).
     */
    public static function addBinary($table, $column)
    {
        if (self::isPgsql()) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN {$column} bytea");
        } elseif (self::isOracle()) {
            DB::statement("ALTER TABLE {$table} ADD {$column} BLOB");
        } else {
            Schema::table($table, function ($t) use ($column) {
                $t->binary($column)->nullable();
            });
        }
    }

    /**
     * Adiciona uma coluna de texto longo (Oracle CLOB → text no Postgres).
     */
    public static function addLongText($table, $column)
    {
        if (self::isPgsql()) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN {$column} text");
        } elseif (self::isOracle()) {
            DB::statement("ALTER TABLE {$table} ADD {$column} CLOB");
        } else {
            Schema::table($table, function ($t) use ($column) {
                $t->longText($column)->nullable();
            });
        }
    }

    /**
     * Torna uma coluna NULL ou NOT NULL (Oracle MODIFY → Postgres ALTER COLUMN).
     */
    public static function setNullable($table, $column, $nullable = true)
    {
        if (self::isPgsql()) {
            $action = $nullable ? 'DROP NOT NULL' : 'SET NOT NULL';
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} {$action}");
        } elseif (self::isOracle()) {
            $null = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE {$table} MODIFY {$column} DEFAULT NULL {$null}");
        } else {
            // MySQL exige redefinir o tipo no MODIFY; aqui assumimos uso via dbal.
            Schema::table($table, function ($t) use ($column, $nullable) {
                $col = $t->integer($column);
                if ($nullable) $col->nullable();
                $col->change();
            });
        }
    }

    /**
     * Executa um DDL bruto APENAS no Oracle (ex.: NLS_SORT). No-op nos demais.
     */
    public static function oracleOnly($sql)
    {
        if (self::isOracle()) {
            DB::statement($sql);
        }
    }
}
