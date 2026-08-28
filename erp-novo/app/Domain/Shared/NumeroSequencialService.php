<?php

namespace App\Domain\Shared;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Geração de número sequencial atômica (com lock pessimista).
 *
 * Base para a numeração fiscal (NF-e/NFC-e) — preserva a regra crítica do legado
 * (Empresa::lockForUpdate() em trataNumNF/checarNfNumero) que impede números de
 * nota duplicados sob concorrência. No N9 a numeração fiscal usa este serviço.
 *
 * Usa SELECT ... FOR UPDATE dentro de transação. Funciona em PostgreSQL;
 * em SQLite (testes) o lock é no-op mas a lógica de incremento é a mesma.
 */
final class NumeroSequencialService
{
    /**
     * Retorna o próximo número de uma sequência identificada por $chave
     * (ex.: "nfe:empresa:12:serie:1"). Incrementa atomicamente.
     */
    public function proximo(string $chave, int $minimoAtual = 0): int
    {
        return DB::transaction(function () use ($chave, $minimoAtual) {
            // Duas requisicoes podem tentar criar a mesma chave ao mesmo tempo.
            // A UNIQUE + insert idempotente cria o ponto de lock sem gerar 500.
            DB::table('sequencias')->insertOrIgnore(array_filter([
                'chave' => $chave,
                'empresa_id' => self::empresaDaChave($chave),
                'valor' => max(0, $minimoAtual),
                'created_at' => now(),
                'updated_at' => now(),
            ], fn ($valor) => $valor !== null));

            $linha = DB::table('sequencias')
                ->where('chave', $chave)
                ->lockForUpdate()
                ->first();

            // Sob RLS a linha pode existir e ser invisivel para este contexto:
            // o insertOrIgnore nao cria e o select nao acha. Sem esta guarda o
            // acesso a ->valor daria erro fatal em vez de dizer o que houve.
            if ($linha === null) {
                throw new RuntimeException(
                    "Sequencia [{$chave}] nao e visivel no contexto atual — numeracao nao pode ser emitida sem envelope de tenant."
                );
            }

            $proximo = max((int) $linha->valor, $minimoAtual) + 1;
            DB::table('sequencias')->where('chave', $chave)->update([
                'valor' => $proximo,
                'updated_at' => now(),
            ]);

            return $proximo;
        });
    }

    /**
     * A empresa sempre esteve dentro da chave; a coluna `empresa_id` apenas
     * torna a convencao explicita para a RLS. Os dois unicos formatos sao
     * `nf:{empresa}:{modelo}:{serie}` (ModeloDocumento) e
     * `boleto:empresa:{empresa}:banco:...` (CnabDriverBase). Mesma extracao da
     * migration 2026_08_29_001600 — alterar um lado exige alterar o outro.
     */
    public static function empresaDaChave(string $chave): ?int
    {
        $partes = explode(':', $chave);

        $bruto = match (true) {
            str_starts_with($chave, 'nf:') => $partes[1] ?? null,
            str_starts_with($chave, 'boleto:empresa:') => $partes[2] ?? null,
            default => null,
        };

        return is_string($bruto) && ctype_digit($bruto) ? (int) $bruto : null;
    }

    /** Define o valor atual de uma sequência (ex.: ETL importando a numeração da empresa legada). */
    public function definir(string $chave, int $valor): void
    {
        DB::table('sequencias')->updateOrInsert(
            ['chave' => $chave],
            array_filter([
                'valor' => $valor,
                'empresa_id' => self::empresaDaChave($chave),
                'updated_at' => now(),
                'created_at' => now(),
            ], fn ($v) => $v !== null),
        );

        // `sequencias` tem RLS por empresa. Se o chamador nao enxerga a linha, o
        // UPDATE casa zero linhas e o updateOrInsert cai num INSERT que a policy
        // recusa — ou pior, grava sem dono. Falhar aqui e melhor do que o ETL
        // relatar sucesso e a primeira NF-e sair com numero ja autorizado.
        $gravado = DB::table('sequencias')->where('chave', $chave)->value('valor');
        if ((int) $gravado !== $valor) {
            throw new RuntimeException(
                "Sequencia [{$chave}] nao pode ser definida: a linha nao e visivel no contexto atual. "
                .'Rode o ETL/seed com a conexao proprietaria ou com envelope de tenant ativo.'
            );
        }
    }
}
