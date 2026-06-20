<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\Processors\EstoqueProcessor;
use App\Estoquesetor;
use App\Estoquesetorhistorico;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;

/**
 * F5 — BASELINE (caracterização) do EstoqueProcessor ANTES de expô-lo na API.
 * Trava o comportamento atual (movimentação de saldo + permiteestoquenegativo)
 * para detectar regressão. NÃO altera o motor.
 */
class EstoqueProcessorBaselineTest extends TestCase
{
    use DatabaseTransactions;

    private $admin;
    private $setorId;
    private $produtoId;

    private function preparar(bool $permiteNegativo = false)
    {
        \Artisan::call('db:seed', ['--class' => '\DeployAdminSeeder', '--force' => true]);
        \Artisan::call('db:seed', ['--class' => '\RbacSeeder', '--force' => true]);
        $this->admin = \App\User::where('email', env('ADMIN_SEED_EMAIL', 'admin'))->first();
        $grupo = optional($this->admin->empresa)->grupo_id ?? 1;
        $empresaId = $this->admin->empresa_id;

        // O motor lê Session::get('empresa_padrao') e a config da empresa.
        Session::put('empresa_padrao', \App\Empresa::find($empresaId));

        // Cria setor ANTES (config exige setorprincipal_id NOT NULL).
        $this->setorId = \DB::table('setors')->insertGetId([
            'grupo_id' => $grupo, 'empresa_id' => $empresaId, 'cidade_id' => 1, 'bairro_id' => 1,
            'descricao' => 'Setor Estq', 'numero' => '1', 'cep' => '00000000', 'latitude' => 0, 'longitude' => 0,
            'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \App\Empresaconfig::updateOrCreate(
            ['empresa_id' => $empresaId, 'grupo_id' => $grupo],
            ['permiteestoquenegativo' => $permiteNegativo ? 1 : 0, 'diastrabalhadosemana' => 6, 'setorprincipal_id' => $this->setorId]
        );

        $classe = \DB::table('produtoclasses')->insertGetId(['grupo_id' => $grupo, 'descricao' => 'C', 'tipo' => 'P', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $unidade = \DB::table('unidademedidas')->insertGetId(['grupo_id' => $grupo, 'descricao' => 'UN', 'sigla' => 'UN', 'ativo' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->produtoId = \DB::table('produtos')->insertGetId([
            'grupo_id' => $grupo, 'empresa_id' => $empresaId, 'produtoclasse_id' => $classe, 'unidademedida_id' => $unidade,
            'descricao' => 'Prod Estq', 'vasilhameretornavel' => false, 'ativo' => 1, 'nfepermite' => 0,
            'customedio' => 0, 'custofrete' => 0, 'precovenda' => 0, 'precovendaminimo' => 0, 'pesoliquido' => 0, 'pesobruto' => 0,
            'observacao' => '', 'ean' => '', 'ncm' => '', 'especie' => '', 'marca' => '', 'nfedescricaofiscal' => '',
            'nfetipoitem' => 0, 'nfeextipi' => '', 'nfecodgen' => 0, 'nfecodlst' => 0, 'nfenatrec' => '',
            'nfecodenquadramentoipi' => 0, 'nfecprodanp' => '', 'nfeqbcprod' => 0, 'nfevaliqprod' => 0, 'nfevcide' => 0,
            'pGNi' => 0, 'pGNn' => 0, 'pGLP' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function hist(string $mov, $qtde): Estoquesetorhistorico
    {
        $h = new Estoquesetorhistorico();
        $h->user_id = $this->admin->id;
        $h->setor_id = $this->setorId;
        $h->produto_id = $this->produtoId;
        $h->movimentacao = $mov;
        $h->quantidade = $qtde;
        $h->motivo = 'teste baseline';
        $h->datahora = now();
        $h->datahoracompetencia = now();
        $h->entidade = 'Teste';
        $h->entidade_id = 0;
        $h->grupo_id = optional($this->admin->empresa)->grupo_id ?? 1;
        $h->empresa_id = $this->admin->empresa_id;
        return $h;
    }

    public function test_entrada_cria_e_incrementa_saldo()
    {
        $this->preparar();
        $proc = new EstoqueProcessor();

        $this->assertTrue($proc->movimentarEstoque([$this->hist('ENTRADA', 10)]), implode(';', $proc->getErrors()));
        $saldo = Estoquesetor::where(['produto_id' => $this->produtoId, 'setor_id' => $this->setorId])->value('quantidade');
        $this->assertEquals(10, (float) $saldo);

        $proc->movimentarEstoque([$this->hist('ENTRADA', 5)]);
        $this->assertEquals(15, (float) Estoquesetor::where(['produto_id' => $this->produtoId, 'setor_id' => $this->setorId])->value('quantidade'));
    }

    public function test_saida_reduz_saldo()
    {
        $this->preparar();
        $proc = new EstoqueProcessor();
        $proc->movimentarEstoque([$this->hist('ENTRADA', 20)]);
        $this->assertTrue($proc->movimentarEstoque([$this->hist('SAIDA', 8)]), implode(';', $proc->getErrors()));
        $this->assertEquals(12, (float) Estoquesetor::where(['produto_id' => $this->produtoId, 'setor_id' => $this->setorId])->value('quantidade'));
    }

    public function test_saida_sem_saldo_bloqueada_quando_nao_permite_negativo()
    {
        $this->preparar(false);
        $proc = new EstoqueProcessor();
        // SAÍDA sem saldo e empresa não permite negativar → falha.
        $this->assertFalse($proc->movimentarEstoque([$this->hist('SAIDA', 5)]));
        $this->assertNotEmpty($proc->getErrors());
    }

    public function test_saida_negativa_permitida_quando_config_permite()
    {
        $this->preparar(true);
        $proc = new EstoqueProcessor();
        $this->assertTrue($proc->movimentarEstoque([$this->hist('SAIDA', 5)]), implode(';', $proc->getErrors()));
        $this->assertEquals(-5, (float) Estoquesetor::where(['produto_id' => $this->produtoId, 'setor_id' => $this->setorId])->value('quantidade'));
    }
}
