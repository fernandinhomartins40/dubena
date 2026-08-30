<?php

namespace Tests\Feature;

use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Fiscal\NotaFiscal;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contrato dos campos que a TELA de NF-e consome.
 *
 * Por que existe: a tela lia os nomes do schema LEGADO (`nfnumero`,
 * `chaveacesso`, `nfsituacao_id`) enquanto a API entrega os do schema novo
 * (`numero`, `chave`, `situacao`). Nada quebrava — nem build, nem runtime: os
 * campos vinham `undefined` e as 241 mil notas apareciam como linhas vazias,
 * com "/" no número, travessão em tudo e "Pendente" em todas.
 *
 * É a pior classe de defeito: silencioso e só visível para quem olha a tela.
 * Este teste fixa os nomes; renomear um campo no backend passa a quebrar aqui,
 * e não na cara do usuário.
 */
class ContratoNotaFiscalApiTest extends TestCase
{
    use RefreshDatabase;

    /** Campos que `NfeTab.tsx` lê de cada linha. */
    private const CAMPOS_DA_TELA = [
        'id', 'modelo', 'serie', 'numero', 'chave',
        'situacao', 'emitida_em', 'valor_total',
    ];

    public function test_listagem_de_notas_entrega_os_campos_que_a_tela_le(): void
    {
        $empresa = Empresa::factory()->create();
        $cliente = Cliente::withoutTenant()->create([
            'nome' => 'Cliente Teste',
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente' => true,
            'ativo' => true,
        ]);

        NotaFiscal::withoutTenant()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
            'cliente_id' => $cliente->id,
            'modelo' => '55',
            'tipo' => 'S',
            'serie' => 1,
            'numero' => 361778,
            'chave' => str_repeat('4', 44),
            'protocolo' => '141261329354394',
            'valor_total' => 116.08,
            'situacao' => 'AUTORIZADA',
            'emitida_em' => now(),
        ]);

        $token = $this->usuarioFiscal($empresa);

        $resposta = $this->withToken($token)->getJson('/api/admin/fiscal/nfe')->assertOk();

        $linha = $resposta->json('data.0');
        $this->assertIsArray($linha, 'a listagem precisa devolver ao menos uma nota');

        foreach (self::CAMPOS_DA_TELA as $campo) {
            $this->assertArrayHasKey(
                $campo,
                $linha,
                "a tela de NF-e lê `{$campo}`; a API não está devolvendo esse campo"
            );
        }

        // O nome do cliente é exibido na coluna "Cliente".
        $this->assertSame('Cliente Teste', $linha['cliente']['nome'] ?? null);

        // A situação tem de ser um dos rótulos que a tela sabe traduzir —
        // um valor fora da lista cai no fallback e some da tela.
        $this->assertContains($linha['situacao'], [
            'RASCUNHO', 'EMITIDA', 'AUTORIZADA', 'REJEITADA', 'DENEGADA', 'CANCELADA',
        ]);

        // E os campos precisam vir PREENCHIDOS, não só existir.
        $this->assertSame(361778, (int) $linha['numero']);
        $this->assertSame(44, strlen((string) $linha['chave']));
    }

    private function usuarioFiscal(Empresa $empresa): string
    {
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'grupo_id' => $empresa->grupo_id,
        ]);

        $papel = Role::create(['grupo_id' => $empresa->grupo_id, 'nome' => 'Fiscal']);
        $papel->permissions()->sync([
            Permission::firstOrCreate(['chave' => 'fiscal.view'])->id,
        ]);
        $user->roles()->attach($papel->id, ['empresa_id' => $empresa->id]);

        return $user->createToken('teste')->plainTextToken;
    }
}
