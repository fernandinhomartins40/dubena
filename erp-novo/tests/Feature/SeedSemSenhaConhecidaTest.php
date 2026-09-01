<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * F7-11 — nenhum seeder cria conta com senha conhecida ou default operacional.
 *
 * ## O que a conferência encontrou
 *
 * Os seeders de deploy já usavam `ResolveSenhaSeed`, que é bem feito: exige a
 * variável em produção, e fora dela **gera uma senha aleatória e a anuncia** —
 * quem roda vê a senha uma vez, e ela não fica no código.
 *
 * Dois ficaram de fora:
 *
 *  - **`AcessoRedeDubenaSeeder`** tinha `env('DONO_SEED_PASSWORD', 'dono@2026')`.
 *    O default é o problema: entra sozinho quando a variável não está definida,
 *    e esse usuário é o **dono da rede**, que enxerga todas as filiais;
 *  - **`PerfisCampoTesteSeeder`** tem senha fixa `teste123` — legítima, porque é
 *    fixture e o app precisa de credencial estável para testar login. O que
 *    faltava era a trava: `db:seed --class=` não pergunta em que ambiente está.
 *
 * ## Por que um guardião, e não só a correção
 *
 * Senha fixa em seeder é a coisa mais natural do mundo de escrever — o seeder
 * existe justamente para deixar o ambiente utilizável rápido. O custo só aparece
 * quando alguém roda em produção "só para popular os cadastros".
 */
class SeedSemSenhaConhecidaTest extends TestCase
{
    /**
     * Seeders que podem ter senha literal, com o motivo.
     *
     * A licença é nominal e o teste conferе que o arquivo existe: lista que
     * envelhece protege um seeder que já foi renomeado.
     *
     * @var array<string,string>
     */
    private const FIXTURES = [
        'PerfisCampoTesteSeeder.php' => 'fixture do app; o login de teste precisa de credencial estável',
    ];

    /** @return list<string> */
    private function seeders(): array
    {
        $arquivos = glob(base_path('database/seeders/*.php')) ?: [];

        return array_values(array_filter($arquivos, 'is_file'));
    }

    /**
     * Nenhum seeder operacional carrega senha literal.
     *
     * O padrão procurado é o que de fato aconteceu: `env('X', 'senha')` — o
     * default que entra sozinho — e `Hash::make('literal')`.
     */
    public function test_nenhum_seeder_operacional_tem_senha_literal(): void
    {
        $achados = [];
        $varridos = 0;

        foreach ($this->seeders() as $arquivo) {
            $nome = basename($arquivo);

            if (isset(self::FIXTURES[$nome])) {
                continue;
            }

            $varridos++;
            $conteudo = (string) file_get_contents($arquivo);

            foreach (explode("\n", $conteudo) as $n => $linha) {
                $semEspaco = ltrim($linha);

                if (str_starts_with($semEspaco, '*') || str_starts_with($semEspaco, '//')) {
                    continue;
                }

                // `env('ALGO_PASSWORD', 'valor')` — o default é o defeito.
                $temDefault = preg_match(
                    "/env\(\s*'[A-Z_]*(PASSWORD|SENHA)[A-Z_]*'\s*,\s*'[^']+'/i",
                    $linha,
                ) === 1;

                // `Hash::make('literal')` com string, não variável.
                $temHashLiteral = preg_match("/Hash::make\(\s*'[^']+'\s*\)/", $linha) === 1;

                if ($temDefault || $temHashLiteral) {
                    $achados[] = $nome.':'.($n + 1);
                }
            }
        }

        $this->assertGreaterThan(5, $varridos, 'a varredura precisa ter alcançado os seeders');
        $this->assertSame(
            [],
            $achados,
            'use ResolveSenhaSeed: senha default entra sozinha quando a variável não está definida',
        );
    }

    /**
     * Toda fixture com senha fixa recusa produção.
     *
     * A senha estável é o que torna a fixture útil; a trava é o que a torna
     * segura. Uma sem a outra é conta aberta num ambiente real.
     */
    public function test_fixture_com_senha_fixa_recusa_producao(): void
    {
        foreach (array_keys(self::FIXTURES) as $nome) {
            $caminho = base_path('database/seeders/'.$nome);

            $this->assertFileExists($caminho, "{$nome} está na lista mas não existe — remova a entrada.");

            $conteudo = (string) file_get_contents($caminho);

            $this->assertStringContainsString(
                "environment('production')",
                $conteudo,
                "{$nome} tem senha conhecida e precisa recusar produção",
            );
        }
    }

    /**
     * O trait canônico continua exigindo a variável em produção.
     *
     * É ele que sustenta a correção; se alguém afrouxar a exigência, todos os
     * seeders de deploy passam a aceitar senha ausente sem avisar.
     */
    public function test_o_trait_canonico_exige_a_variavel_em_producao(): void
    {
        $conteudo = (string) file_get_contents(
            base_path('database/seeders/Concerns/ResolveSenhaSeed.php'),
        );

        $this->assertStringContainsString("environment('production')", $conteudo);
        $this->assertStringContainsString('obrigatória em produção', $conteudo);
        $this->assertStringContainsString('Str::random', $conteudo, 'fora de produção, gera em vez de fixar');
    }
}
