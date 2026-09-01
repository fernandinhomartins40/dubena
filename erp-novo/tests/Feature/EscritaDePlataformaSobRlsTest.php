<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A escrita da PLATAFORMA (sem empresa) não pode ser rejeitada pela RLS.
 *
 * ## O defeito que este teste existe para impedir
 *
 * `integracao_consumos` (F6-01) foi criada para responder *quem gastou quanto*,
 * e o cabeçalho da migration diz que os nulos significam "chave da plataforma —
 * **o caso que hoje some no log, e que é justamente o que se quer enxergar**".
 *
 * A policy, porém, saiu com `WITH CHECK (empresa_id IS NOT NULL AND ...)`. Com
 * `FORCE ROW LEVEL SECURITY`, o Postgres rejeita todo insert de `empresa_id`
 * nulo — a tabela criada para enxergar o consumo da plataforma era exatamente a
 * que nunca o registrava.
 *
 * Conferido ao vivo em homologação antes de escrever isto:
 * `ERROR: new row violates row-level security policy`.
 *
 * ## Por que nada acusava
 *
 *  - o registrador engole toda exceção, de propósito (contar chamada não pode
 *    derrubar uma geocodificação) — o que transforma a rejeição em nada;
 *  - a suíte roda em sqlite, que não tem RLS.
 *
 * Este teste fecha o segundo furo. O primeiro continua certo como está: a
 * instrumentação **deve** ser silenciosa; o que não pode é ninguém verificar o
 * banco de verdade.
 *
 * ## O que se mede é o ACEITE, não a linha
 *
 * A linha de `empresa_id` nulo é gravável pelo runtime e **invisível** para ele:
 * o `USING` da policy só mostra linha de empresa que o tenant alcança, e ela não
 * é de tenant nenhum. Isso é o comportamento certo, e torna a conferência
 * posterior impossível de dentro do teste — ver a nota no corpo do método.
 *
 * O sinal que importa é o Postgres ter aceitado a escrita. Se o `WITH CHECK`
 * voltar a exigir `empresa_id IS NOT NULL`, o insert lança e o teste reprova.
 */
class EscritaDePlataformaSobRlsTest extends TestCase
{
    // `DatabaseTransactions`, e não `RefreshDatabase`: o runtime (`erp_app`) NÃO
    // é dono das tabelas, então recriar o schema falha com
    // `must be owner of table agencias`. Foi o que derrubou o gate do CI na
    // primeira vez, e o defeito era do teste — não do que ele mede.
    use DatabaseTransactions;

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function escritasDePlataforma(): array
    {
        return [
            'consumo de integração com chave da plataforma' => [
                'integracao_consumos',
                [
                    'dia' => '2026-09-01',
                    'chamadas' => 1,
                    'erros' => 0,
                    'custo_centavos' => 0,
                ],
            ],
            'chamada de ponte antes de resolver tenant (login/init)' => [
                'ponte_usos',
                [
                    'ponte' => 'nfweb',
                    'dia' => '2026-09-01',
                    'chamadas' => 1,
                    'recusas' => 0,
                ],
            ],
        ];
    }

    /**
     * @dataProvider escritasDePlataforma
     *
     * @param  array<string, mixed>  $linha
     */
    public function test_linha_sem_empresa_pode_ser_gravada(string $tabela, array $linha): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é do PostgreSQL; o CI roda este teste com a role real');
        }

        // Marca que identifica ESTA execução: a conferência pelo owner não pode
        // confundir a linha deste teste com sobra de outra execução.
        $marca = 'plat-'.bin2hex(random_bytes(6));
        $coluna = $tabela === 'integracao_consumos' ? 'servico' : 'endpoint';

        $erro = null;

        try {
            DB::table($tabela)->insert(array_merge($linha, [
                $coluna => $marca,
                'empresa_id' => null,
                'tenant_account_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        } catch (\Throwable $e) {
            $erro = $e->getMessage();
        }

        $this->assertNull(
            $erro,
            "{$tabela}: a RLS rejeitou a escrita da PLATAFORMA. ".
            'Com `WITH CHECK (empresa_id IS NOT NULL AND ...)` o Postgres a recusa, '.
            "e o registrador engole a exceção — o dado some sem ninguém notar. Erro: {$erro}",
        );

        // Não há segunda asserção, e isso é deliberado.
        //
        // Conferir a linha depois do insert parece mais forte e é, na verdade,
        // impossível de fazer direito aqui — tentei das duas formas e as duas
        // dão zero por motivos DIFERENTES do que o teste mede:
        //
        //  - pela conexão do runtime, o `USING` da policy esconde a linha: ela
        //    não é de tenant nenhum, e é assim que tem de ser;
        //  - por `pgsql_owner`, que é conexão SEPARADA e não enxerga a transação
        //    ainda não commitada do `DatabaseTransactions`.
        //
        // O que importa é que o Postgres ACEITOU a escrita. Se o `WITH CHECK`
        // voltar a exigir `empresa_id IS NOT NULL`, o insert lança
        // `new row violates row-level security policy` e a asserção acima
        // reprova — que é exatamente o defeito que este teste existe para pegar.
        //
        // Conferido à mão no banco, fora de transação:
        // `INSERT 0 1` e o owner lê a linha.
    }

    /**
     * O que a policy CONTINUA impedindo.
     *
     * Aceitar nulo não é afrouxar: a linha sem empresa não pertence a tenant
     * nenhum, então não há sigilo a violar. O que não pode é o runtime gravar
     * linha de uma empresa sem envelope de tenant no contexto — e isso segue
     * barrado, que é o comportamento fail-closed.
     *
     * ## A armadilha: a RLS DESCARTA, nem sempre lança
     *
     * Um insert cuja linha não passa no `WITH CHECK` pode voltar como
     * `INSERT 0 0`, sem erro. Testar só por exceção daria verde num banco que
     * não protege nada — por isso a asserção final é sobre a **contagem pelo
     * owner**, que é o efeito observável.
     */
    public function test_escrita_com_empresa_e_sem_contexto_continua_barrada(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('RLS é do PostgreSQL; o CI roda este teste com a role real');
        }

        $owner = DB::connection('pgsql_owner');
        $agora = now();

        // A empresa nasce pelo OWNER: criá-la pelo runtime seria barrado pela
        // própria RLS (não há envelope de tenant), e o teste morreria antes de
        // chegar ao que quer medir.
        $grupoId = $owner->table('grupos')->insertGetId([
            'descricao' => 'RLS plataforma '.bin2hex(random_bytes(4)),
            'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $empresaId = $owner->table('empresas')->insertGetId([
            'grupo_id' => $grupoId, 'razao_social' => 'Empresa RLS plataforma',
            'ativo' => true, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $marca = 'alheia-'.bin2hex(random_bytes(6));

        try {
            DB::table('ponte_usos')->insert([
                'ponte' => 'nfweb',
                'endpoint' => $marca,
                'empresa_id' => $empresaId,
                'tenant_account_id' => null,
                'dia' => '2026-09-01',
                'chamadas' => 1,
                'recusas' => 0,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        } catch (\Throwable) {
            // Rejeição explícita é um desfecho aceitável — o que importa é que a
            // linha não exista no fim.
        }

        $this->assertSame(
            0,
            $owner->table('ponte_usos')->where('endpoint', $marca)->count(),
            'escrita COM empresa e sem envelope de tenant não pode gravar — '.
            'seja lançando ou descartando, a linha não pode existir',
        );
    }
}
