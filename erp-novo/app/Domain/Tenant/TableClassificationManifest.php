<?php

namespace App\Domain\Tenant;

use LogicException;

/**
 * F1-02: a classificacao e uma decisao revisavel, nunca um heuristico por nome.
 */
final class TableClassificationManifest
{
    public const CLASSES = ['PLATFORM', 'TENANT', 'COMPANY', 'DERIVED', 'STAGING'];

    /** @param array<string, array{class: string, owner: string, justification: string, opcional?: bool}> $entries */
    public function __construct(private readonly array $entries) {}

    /** @param list<string> $effectiveTables */
    public function assertComplete(array $effectiveTables): void
    {
        $effective = array_values(array_unique($effectiveTables));
        sort($effective);
        $declared = array_keys($this->entries);
        sort($declared);

        // Tabela `opcional` é a que só existe em ALGUM banco — um backup feito à
        // mão numa base específica, por exemplo. Se estiver lá, tem de estar
        // classificada (por isso fica no manifesto); se não estiver, não é
        // divergência. Sem isto o portão só aprovava no banco onde o backup
        // foi feito e reprovava em todo banco novo, que é o caso do SaaS.
        $obrigatorias = array_keys(array_filter($this->entries, fn (array $e) => empty($e['opcional'])));

        $missing = array_values(array_diff($effective, $declared));
        $obsolete = array_values(array_diff($obrigatorias, $effective));
        if ($missing !== [] || $obsolete !== []) {
            throw new LogicException(sprintf(
                'Manifesto de classificacao divergente. Ausentes: [%s]. Obsoletas: [%s].',
                implode(', ', $missing),
                implode(', ', $obsolete),
            ));
        }

        foreach ($this->entries as $table => $entry) {
            if (! in_array($entry['class'] ?? null, self::CLASSES, true)) {
                throw new LogicException("Classe invalida para {$table}.");
            }
            if (trim((string) ($entry['owner'] ?? '')) === '') {
                throw new LogicException("Owner ausente para {$table}.");
            }
            if (trim((string) ($entry['justification'] ?? '')) === '') {
                throw new LogicException("Justificativa ausente para {$table}.");
            }
        }
    }
}
