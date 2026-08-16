<?php

namespace App\Etl\Migrators;

use App\Etl\Contracts\Migrator;
use App\Etl\Invariants\IntegrityInvariant;
use App\Etl\Support\MigrationContext;
use App\Etl\Support\MigrationResult;

/**
 * F15 — Pagamentos: **sem origem no legado** (correção da T2.3 do PLANO_PRODUCAO).
 *
 * A versão anterior lia `cartaotransacoes` e `gasdopovobeneficios`, duas
 * tabelas que **nunca existiram** no Oracle: o migrator fora escrito contra um
 * schema imaginado, "rodava com sucesso" e migrava zero linhas. A checagem
 * `hasTable` da `CountInvariant` foi o que tornou isso visível.
 *
 * **O que a investigação encontrou** (`user_tables` do Oracle, grafias reais):
 *
 * | Tabela candidata  | Linhas | Veredito |
 * |-------------------|--------|----------|
 * | `PIXTRANSACTIONS` | 4.961  | Já migrada pelo `CobrancaMigrator` (`pixLegado()`) — está no MAPA do espelho e chega íntegra a `pix_cobrancas`. Trazê-la aqui duplicaria a carga. |
 * | `BENEFICIARIOS`   |   480  | NÃO corresponde a `gasdopovo_beneficios`. É um CADASTRO DE PROGRAMA (`codbenef`, `descricao`, `datainicio`, `datafim`, `uf` — todas do PR), sem cliente, NIS, valor ou competência; o destino é o BENEFÍCIO CONCEDIDO a um cliente. Mapear uma na outra inventaria dado. Decisão registrada na T2.7. |
 * | Transações de cartão | — | Não existe tabela de origem: o módulo não existia no legado. |
 *
 * **Por que o migrator continua existindo.** Os destinos (`cartao_transacoes`,
 * `gasdopovo_beneficios`) são alimentados pela OPERAÇÃO do sistema novo, não
 * pela carga histórica. Manter a classe registrada — sem invariante de
 * contagem, que só mentiria — preserva o ponto de extensão e, sobretudo,
 * deixa a ausência de origem **escrita e rastreável** em vez de implícita.
 * Foi o implícito que produziu o defeito original.
 *
 * As invariantes de INTEGRIDADE seguem valendo: se algum dia essas tabelas
 * receberem linhas, elas não podem apontar para empresa inexistente.
 */
final class PagamentoMigrator implements Migrator
{
    private ?MigrationContext $ctxAtual = null;

    public function nome(): string
    {
        return 'pagamentos';
    }

    public function dependeDe(): array
    {
        return ['empresas', 'clientes', 'pedidos'];
    }

    public function migrar(MigrationContext $ctx): MigrationResult
    {
        $this->ctxAtual = $ctx;

        // Nada a migrar POR DESENHO (não por falha): ver o quadro no docblock.
        // O aviso é explícito para que "não migrou nada" nunca mais se confunda
        // com "não conseguiu migrar" na leitura do relatório do ETL.
        return new MigrationResult($this->nome(), 0, 0, 0, [
            'pagamentos: sem origem no legado — PIX vem do CobrancaMigrator, '
                .'BENEFICIARIOS é cadastro de programa (≠ benefício concedido) '
                .'e cartão não existia no legado. Ver T2.3/T2.7.',
        ]);
    }

    public function invariantes(): array
    {
        $ctx = $this->ctxAtual ?? new MigrationContext;
        if (! $this->legadoDisponivel($ctx)) {
            return [];
        }

        // SEM CountInvariant: não há par origem→destino a comparar. Declarar
        // uma contagem contra tabela inexistente foi exatamente o defeito que
        // a T2.3 corrige.
        return [
            new IntegrityInvariant($ctx, 'cartao_transacoes', 'empresa_id', 'empresas'),
            new IntegrityInvariant($ctx, 'gasdopovo_beneficios', 'empresa_id', 'empresas'),
        ];
    }

    private function legadoDisponivel(MigrationContext $ctx): bool
    {
        try {
            $ctx->legado()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
