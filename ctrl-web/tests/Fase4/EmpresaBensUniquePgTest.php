<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\Http\Requests\EmpresaBensRequest;
use Illuminate\Support\Facades\Validator;

/**
 * M1.1 (fix Postgres) — a validação `unique` do cadastro de bem da empresa
 * quebrava com 500 (22P02) no update quando id vinha vazio (regra virava
 * "...,descricao,,id,..." → `"id" <> ''`). Mesmo bug do ClienteRequest.
 */
class EmpresaBensUniquePgTest extends TestCase
{
    private function regras($id): array
    {
        \Session::put('empresa_padrao', (object) ['id' => 1]);
        $req = EmpresaBensRequest::create('/empresabem', 'PUT', [
            'id'        => $id,
            'descricao' => 'Bem Teste',
        ]);
        $req->setContainer(app());
        return $req->rules();
    }

    /** Com id vazio (update sem id), o except vira NULL — não '' . */
    public function test_unique_usa_NULL_quando_id_vazio()
    {
        $regras = $this->regras('');
        $this->assertStringContainsString(',NULL,id,', $regras['descricao']);
        $this->assertStringContainsString(',NULL,id,', $regras['numeroserie']);
    }

    /** Validar a regra não estoura QueryException (22P02) no Postgres. */
    public function test_validacao_nao_estoura_500()
    {
        $regras = $this->regras('');
        try {
            Validator::make(['descricao' => 'Bem Teste'], ['descricao' => $regras['descricao']])->fails();
            $this->assertTrue(true);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->fail('unique de empresabems estourou QueryException (22P02): ' . $e->getMessage());
        }
    }

    /** Com id presente, o except é o próprio id (comportamento de update). */
    public function test_unique_usa_id_quando_presente()
    {
        $regras = $this->regras('42');
        $this->assertStringContainsString(',42,id,', $regras['descricao']);
    }
}
