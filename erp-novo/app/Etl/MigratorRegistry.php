<?php

namespace App\Etl;

use App\Etl\Contracts\Migrator;
use App\Etl\Migrators\AppGasEmCasaMigrator;
use App\Etl\Migrators\CadastrosApoioMigrator;
use App\Etl\Migrators\CadastrosContabeisMigrator;
use App\Etl\Migrators\CaixaMigrator;
use App\Etl\Migrators\ClientesMigrator;
use App\Etl\Migrators\CobrancaMigrator;
use App\Etl\Migrators\ComplementosMigrator;
use App\Etl\Migrators\CrmMigrator;
use App\Etl\Migrators\EmpresaConfigMigrator;
use App\Etl\Migrators\EmpresasMigrator;
use App\Etl\Migrators\EstadosMigrator;
use App\Etl\Migrators\EstoqueMigrator;
use App\Etl\Migrators\FinanceiroMigrator;
use App\Etl\Migrators\FiscalMigrator;
use App\Etl\Migrators\FrotaMigrator;
use App\Etl\Migrators\GeograficoMigrator;
use App\Etl\Migrators\GestaoMigrator;
use App\Etl\Migrators\FiscalConfigMigrator;
use App\Etl\Migrators\MobileMigrator;
use App\Etl\Migrators\MonitoraLegadoMigrator;
use App\Etl\Migrators\PagamentoMigrator;
use App\Etl\Migrators\PedidosMigrator;
use App\Etl\Migrators\ProdutosMigrator;
use App\Etl\Migrators\RhMigrator;
use App\Etl\Migrators\SatelitesMigrator;
use App\Etl\Migrators\UsersMigrator;

/**
 * Registro central dos migrators, em ordem de dependência.
 * Cada fase do plano (N1..N11) adiciona seus migrators aqui.
 */
final class MigratorRegistry
{
    /** @return list<class-string<Migrator>> */
    public static function all(): array
    {
        return [
            // N0 — exemplo ponta-a-ponta
            EstadosMigrator::class,

            // N1 — cadastros base
            EmpresasMigrator::class,
            EmpresaConfigMigrator::class,
            // Usuários ANTES de pedidos: atendente/entregador referenciam user_id
            // do legado (achado P0 da auditoria — sem isso, viram null).
            UsersMigrator::class,
            CadastrosApoioMigrator::class,
            // Plano de contas, centros de custo e operacoes fiscais — dao
            // significado ao financeiro (DRE) e ao fiscal.
            CadastrosContabeisMigrator::class,

            // N2 — geográfico + clientes
            GeograficoMigrator::class,
            ClientesMigrator::class,

            // N3 — produtos + estoque
            ProdutosMigrator::class,
            EstoqueMigrator::class,

            // N4 — pedidos / vendas
            PedidosMigrator::class,

            // N5 — financeiro (a pagar/receber)
            FinanceiroMigrator::class,

            // N6 — caixa / conta / cheque
            CaixaMigrator::class,

            // N7 — cobrança (boleto/pix)
            CobrancaMigrator::class,

            // N8 — satélites (convênio/vale-gás/comodato)
            SatelitesMigrator::class,

            // N9 — fiscal (NF-e/NFC-e/CF-e)
            FiscalMigrator::class,
            // Malha fiscal: regras de imposto (grupos, CST/CEST, imposto por
            // produto/UF/lei) — sem elas a emissão de NF-e não tem base tributária.
            FiscalConfigMigrator::class,

            // N10 — mobile (devices + pagamentos online)
            MobileMigrator::class,

            // Monitora ORIGINAL (MySQL) — veículos/cercas/última posição.
            // (O antigo MonitoraMigrator era código morto: lia tabelas
            // `monitora_*` que nunca existiram no legado — removido.)
            MonitoraLegadoMigrator::class,

            // App "Gás em Casa" (MySQL sgcm_api) — o que só existe no app
            // (endereços de entrega, avaliações). Depende de clientes+pedidos.
            AppGasEmCasaMigrator::class,

            // Complementos: funcionalidades que ficaram fora da reescrita
            // (transferências, volumes de NF, histórico de boleto, ...).
            ComplementosMigrator::class,

            // F15 — cauda longa (RH, frota, CRM, gestão, pagamentos)
            RhMigrator::class,
            FrotaMigrator::class,
            CrmMigrator::class,
            GestaoMigrator::class,
            PagamentoMigrator::class,
        ];
    }

    /** Resolve instâncias ordenadas por dependência (topological sort simples). */
    public static function resolved(): array
    {
        $classes = self::all();
        /** @var array<string,Migrator> $byName */
        $byName = [];
        foreach ($classes as $class) {
            $m = app($class);
            $byName[$m->nome()] = $m;
        }

        $ordered = [];
        $visiting = [];
        $visit = function (Migrator $m) use (&$visit, &$ordered, &$visiting, $byName) {
            if (isset($ordered[$m->nome()])) {
                return;
            }
            if (isset($visiting[$m->nome()])) {
                throw new \RuntimeException("Dependência cíclica no ETL: {$m->nome()}");
            }
            $visiting[$m->nome()] = true;
            foreach ($m->dependeDe() as $dep) {
                if (isset($byName[$dep])) {
                    $visit($byName[$dep]);
                }
            }
            unset($visiting[$m->nome()]);
            $ordered[$m->nome()] = $m;
        };

        foreach ($byName as $m) {
            $visit($m);
        }

        return array_values($ordered);
    }
}
