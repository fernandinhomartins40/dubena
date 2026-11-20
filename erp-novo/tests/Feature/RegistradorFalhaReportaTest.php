<?php

namespace Tests\Feature;

use App\Domain\Estoque\EstoqueService;
use App\Domain\Integracao\ConsumoDeIntegracao;
use App\Domain\Pedido\EfeitoPedido;
use App\Domain\Pedido\PedidoService;
use App\Domain\Seguranca\AuditoriaSeguranca;
use App\Http\Controllers\Concerns\AutorizaPorPermissao;
use App\Models\AuditLog;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estoque\Setor;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\User;
use App\Models\Venda\AlcadaDesconto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Registrador que engole falha esconde defeito: foi um `catch` mudo em
 * `ConsumoDeIntegracao` que deixou a policy de `integracao_consumos` recusar toda
 * linha em produção sem ninguém ver. A decisão continua sendo "trilha não derruba
 * a operação" — estes testes fixam que a operação segue E que a falha é reportada.
 */
class RegistradorFalhaReportaTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumo_de_integracao_sem_tabela_segue_e_reporta(): void
    {
        Exceptions::fake();
        Schema::drop('integracao_consumos');

        app(ConsumoDeIntegracao::class)->registrar('geocoding');
        $this->assertSame([], app(ConsumoDeIntegracao::class)->resumo(null, '2026-01-01', '2026-12-31'));

        // Uma falha na escrita, outra na leitura.
        Exceptions::assertReportedCount(2);
    }

    public function test_negacao_com_trilha_quebrada_ainda_e_403_e_reporta(): void
    {
        Exceptions::fake();
        $this->mock(AuditoriaSeguranca::class)
            ->shouldReceive('registrar')->andThrow(new \RuntimeException('trilha fora do ar'));

        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $request = Request::create('/api/admin/qualquer');
        $request->setUserResolver(fn () => $user);

        $controller = new class
        {
            use AutorizaPorPermissao;

            public function chamar(Request $r): void
            {
                $this->autorizar($r, 'permissao.que.ninguem.tem');
            }
        };

        try {
            $controller->chamar($request);
            $this->fail('A negação deveria abortar com 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'trilha fora do ar');
    }

    public function test_recusa_de_alcada_com_trilha_quebrada_ainda_recusa_e_reporta(): void
    {
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $setor = Setor::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $produto = Produto::factory()->create([
            'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
            'preco_venda' => 100, 'preco_venda_minimo' => null,
        ]);
        $cliente = Cliente::factory()->create(['empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id]);
        $situacao = PedidoSituacao::factory()->efeito(EfeitoPedido::PENDENTE)
            ->create(['grupo_id' => $empresa->grupo_id]);
        app(EstoqueService::class)->entrada($setor->id, $produto->id, 1000, 10);
        AlcadaDesconto::create(['empresa_id' => $empresa->id, 'percentual_max' => 10, 'ativo' => true]);
        $this->actingAs($user);

        Exceptions::fake();
        // Só a linha da recusa falha; o resto da trilha segue normal.
        AuditLog::creating(function (AuditLog $log) {
            if ($log->entidade === 'alcada_desconto') {
                throw new \RuntimeException('audit_logs recusou');
            }
        });

        try {
            app(PedidoService::class)->criar([
                'empresa_id' => $empresa->id, 'grupo_id' => $empresa->grupo_id,
                'cliente_id' => $cliente->id, 'pedidosituacao_id' => $situacao->id,
                'setor_id' => $setor->id, 'user_id' => $user->id,
            ], [['produto_id' => $produto->id, 'quantidade' => 2, 'desconto' => 21]]);
            $this->fail('O desconto acima do teto deveria ser recusado.');
        } catch (\DomainException) {
            // a recusa — a regra — continua valendo
        }

        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'audit_logs recusou');
    }
}
