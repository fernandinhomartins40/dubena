<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\Http\Requests\ClienteRequest;
use Illuminate\Support\Facades\Validator;

/**
 * FASE 4 (fix Postgres) — a validação `unique` do cadastro de cliente quebrava
 * com 500 (SQLSTATE 22P02) quando cliente_id vinha vazio: a regra virava
 * "unique:clientes,rg,,id,..." → SQL `"id" <> ''`, que o Postgres rejeita numa
 * coluna integer. Garantimos que as regras geradas usam 'NULL' (não '') no
 * except e que validá-las não estoura QueryException.
 */
class ClienteRequestUniquePgTest extends TestCase
{
    private function regrasComClienteId($clienteId): array
    {
        $req = ClienteRequest::create('/cliente', 'POST', [
            'cliente_id'    => $clienteId,
            'tipopessoa_id' => '1F',     // pessoa física → exercita regras de rg/cpf
            'nome'          => 'Fulano de Teste',
            'uf'            => 'PR',
            'rg'            => '97.306.885',
        ]);
        $req->setContainer(app());
        // empresa_padrao é lida nas regras (Session).
        \Session::put('empresa_padrao', (object) ['id' => 1]);

        return $req->rules();
    }

    /** Com cliente_id vazio, o except das regras unique é 'NULL' (não ''). */
    public function test_regra_unique_usa_NULL_quando_sem_id()
    {
        $regras = $this->regrasComClienteId('');

        $this->assertArrayHasKey('rg', $regras);
        $this->assertStringContainsString(',NULL,id,', is_array($regras['rg']) ? implode('|', $regras['rg']) : $regras['rg'],
            "Com cliente_id vazio o except deveria ser NULL (evita \"id\" <> '' no Postgres)");
    }

    /** Validar a regra de rg gerada NÃO estoura QueryException no Postgres. */
    public function test_validacao_unique_rg_nao_estoura_500()
    {
        $regras = $this->regrasComClienteId('');
        $rgRule = $regras['rg'];

        try {
            Validator::make(['rg' => '97.306.885'], ['rg' => $rgRule])->fails();
            $this->assertTrue(true);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->fail('Regra unique de rg estourou QueryException (bug Postgres 22P02): ' . $e->getMessage());
        }
    }
}
