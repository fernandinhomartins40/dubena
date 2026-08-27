<?php

namespace App\Domain\Shared;

use Illuminate\Support\Facades\DB;

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
            DB::table('sequencias')->insertOrIgnore([
                'chave' => $chave,
                'valor' => max(0, $minimoAtual),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $linha = DB::table('sequencias')
                ->where('chave', $chave)
                ->lockForUpdate()
                ->first();

            $proximo = max((int) $linha->valor, $minimoAtual) + 1;
            DB::table('sequencias')->where('chave', $chave)->update([
                'valor' => $proximo,
                'updated_at' => now(),
            ]);

            return $proximo;
        });
    }

    /** Define o valor atual de uma sequência (ex.: ETL importando a numeração da empresa legada). */
    public function definir(string $chave, int $valor): void
    {
        DB::table('sequencias')->updateOrInsert(
            ['chave' => $chave],
            ['valor' => $valor, 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
