<?php

namespace Database\Seeders;

use App\Domain\Caixa\CaixaService;
use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\PedidoService;
use App\Domain\Satelite\ComodatoService;
use App\Domain\Satelite\ValeGasService;
use App\Domain\Tenant\TenantContext;
use App\Models\Apoio\Banco;
use App\Models\Apoio\Segmento;
use App\Models\Caixa\Conta;
use App\Models\Cliente\Cliente;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\CentroCusto;
use App\Models\Financeiro\FinanceiroParcela;
use App\Models\Financeiro\PlanoConta;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoOperacao;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Produto\ProdutoClasse;
use App\Models\Produto\UnidadeMedida;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\Convenio;
use App\Models\Satelite\ValeGas;
use Illuminate\Database\Seeder;

/**
 * Seed de HOMOLOGAÇÃO (C3) — popula as tabelas de negócio com dados realistas e
 * ÍNTEGROS, passando pelos Services onde há regra (estoque/pedido/caixa/satélite),
 * de modo que os saldos nasçam consistentes e os invariantes (Σ movimentos = saldo)
 * fiquem verdes. Idempotente o suficiente para rodar sobre um banco já semeado pelo
 * DeployAdminSeeder (reaproveita a empresa-matriz). NÃO é para produção.
 *
 * Ordem (topológica): apoio → geográfico → produtos/estoque → clientes →
 * situações/operações → pedidos (geram estoque+financeiro) → caixa (abre+baixa) →
 * satélites (convênio/vale-gás/comodato).
 */
class HomologSeeder extends Seeder
{
    private Empresa $empresa;

    private int $grupoId;

    public function run(): void
    {
        // Garante a empresa-matriz + admin (idempotente) e ativa o tenant para que
        // BelongsToTenant/BelongsToGrupo preencham empresa_id/grupo_id sozinhos.
        $this->call(DeployAdminSeeder::class);
        $this->call(RbacSeeder::class);

        $this->empresa = Empresa::query()->orderBy('id')->firstOrFail();
        $this->grupoId = (int) $this->empresa->grupo_id;

        app(TenantContext::class)->set($this->empresa->id, $this->grupoId);

        $this->apoio();
        $this->estados();
        $this->geografico();
        $produtos = $this->produtosEEstoque();
        $clientes = $this->clientes();
        $this->financeiroCadastros();
        $situacaoConcluido = $this->situacoesEOperacoes();
        $this->pedidos($clientes, $produtos, $situacaoConcluido);
        $this->caixa();
        $this->satelites($clientes, $produtos);

        $this->command?->info('HomologSeeder concluído.');
    }

    private function apoio(): void
    {
        foreach (['Caixa Econômica', 'Itaú', 'Banco do Brasil', 'Bradesco', 'Santander'] as $nome) {
            Banco::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => $nome], ['ativo' => true]);
        }
        foreach (['Residencial', 'Comercial', 'Industrial', 'Revenda'] as $seg) {
            Segmento::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => $seg], ['ativo' => true]);
        }
    }

    /** Estados (a FK cidades.uf → estados.uf exige). Os 27 UFs do Brasil. */
    private function estados(): void
    {
        $ufs = [
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
            'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins',
        ];
        foreach ($ufs as $uf => $nome) {
            Estado::firstOrCreate(['uf' => $uf], ['descricao' => $nome]);
        }
    }

    private function geografico(): void
    {
        $cidade = Cidade::firstOrCreate(
            ['grupo_id' => $this->grupoId, 'descricao' => 'São Paulo', 'uf' => 'SP'],
            ['ativo' => true],
        );
        foreach (['Centro', 'Jardins', 'Mooca', 'Santana', 'Tatuapé'] as $b) {
            Bairro::firstOrCreate(['grupo_id' => $this->grupoId, 'cidade_id' => $cidade->id, 'descricao' => $b], ['ativo' => true]);
        }
    }

    /** @return list<Produto> */
    private function produtosEEstoque(): array
    {
        $classe = ProdutoClasse::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'GLP'], ['ativo' => true]);
        $un = UnidadeMedida::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Unidade'], ['sigla' => 'UN', 'ativo' => true]);

        $defs = [
            ['Botijão P13', 110.00, 90.00],
            ['Botijão P45', 380.00, 320.00],
            ['Água Mineral 20L', 12.00, 7.00],
            ['Botijão P20', 180.00, 150.00],
        ];

        $produtos = [];
        foreach ($defs as [$desc, $venda, $custo]) {
            $produtos[] = Produto::firstOrCreate(
                ['grupo_id' => $this->grupoId, 'descricao' => $desc],
                [
                    'produtoclasse_id' => $classe->id,
                    'unidademedida_id' => $un->id,
                    'preco_venda' => $venda,
                    'custo_medio' => $custo,
                    'ativo' => true,
                ],
            );
        }

        // Setores + saldo inicial via EstoqueService (gera histórico → invariante verde).
        $estoque = app(EstoqueService::class);
        $deposito = Setor::firstOrCreate(['empresa_id' => $this->empresa->id, 'descricao' => 'Depósito'], ['ativo' => true]);
        $loja = Setor::firstOrCreate(['empresa_id' => $this->empresa->id, 'descricao' => 'Loja'], ['ativo' => true]);

        foreach ($produtos as $p) {
            foreach ([$deposito, $loja] as $setor) {
                // Só dá entrada inicial se ainda não houver saldo (idempotência).
                if (EstoqueSaldo::withoutTenant()
                    ->where('setor_id', $setor->id)->where('produto_id', $p->id)->doesntExist()) {
                    $estoque->entrada($setor->id, $p->id, 500, (float) $p->custo_medio, 'seed-inicial', null, null);
                }
            }
        }

        return $produtos;
    }

    /** @return list<Cliente> */
    private function clientes(): array
    {
        if (Cliente::query()->count() >= 20) {
            return Cliente::query()->limit(20)->get()->all();
        }

        return Cliente::factory()->count(20)->create([
            'empresa_id' => $this->empresa->id,
            'grupo_id' => $this->grupoId,
        ])->all();
    }

    private function financeiroCadastros(): void
    {
        PlanoConta::firstOrCreate(['descricao' => 'Receita de Vendas', 'pagarreceber' => 'R'], ['codigo' => '1.01']);
        PlanoConta::firstOrCreate(['descricao' => 'Despesas Operacionais', 'pagarreceber' => 'P'], ['codigo' => '2.01']);
        CentroCusto::firstOrCreate(['descricao' => 'Matriz'], ['codigo' => '01']);
    }

    /** Cria as situações (com efeitos) + operação; retorna a situação CONCLUÍDO. */
    private function situacoesEOperacoes(): PedidoSituacao
    {
        PedidoOperacao::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Venda'], ['ativo' => true]);

        $pendente = PedidoSituacao::firstOrCreate(
            ['grupo_id' => $this->grupoId, 'descricao' => 'Pendente'],
            ['efeito' => 'PENDENTE', 'ativo' => true],
        );
        $concluido = PedidoSituacao::firstOrCreate(
            ['grupo_id' => $this->grupoId, 'descricao' => 'Concluído'],
            ['efeito' => 'CONCLUIDO', 'ativo' => true],
        );
        PedidoSituacao::firstOrCreate(
            ['grupo_id' => $this->grupoId, 'descricao' => 'Cancelado'],
            ['efeito' => 'CANCELADO', 'ativo' => true],
        );

        return $concluido;
    }

    /**
     * @param  list<Cliente>  $clientes
     * @param  list<Produto>  $produtos
     */
    private function pedidos(array $clientes, array $produtos, PedidoSituacao $concluido): void
    {
        if (Pedido::query()->count() >= 30) {
            return; // já semeado
        }

        $svc = app(PedidoService::class);
        $setorLoja = Setor::query()->where('empresa_id', $this->empresa->id)->where('descricao', 'Loja')->value('id');

        for ($i = 0; $i < 30; $i++) {
            $cliente = $clientes[$i % count($clientes)];
            $produto = $produtos[$i % count($produtos)];

            $svc->criar(
                [
                    'empresa_id' => $this->empresa->id,
                    'grupo_id' => $this->grupoId,
                    'cliente_id' => $cliente->id,
                    'setor_id' => $setorLoja,
                    'pedidosituacao_id' => $concluido->id,
                    'datahora' => now()->subDays($i % 30),
                ],
                [
                    ['produto_id' => $produto->id, 'quantidade' => 1 + ($i % 3)],
                ],
            );
        }
    }

    private function caixa(): void
    {
        $svc = app(CaixaService::class);

        $conta = Conta::query()->where('empresa_id', $this->empresa->id)->where('descricao', 'Caixa Geral')->first();
        if (! $conta) {
            $conta = $svc->criarConta([
                'empresa_id' => $this->empresa->id,
                'grupo_id' => $this->grupoId,
                'descricao' => 'Caixa Geral',
                'saldo_inicial' => 1000.00,
            ]);
            $svc->abrir($conta->id);

            // Baixa algumas parcelas a receber geradas pelos pedidos (entra no caixa).
            FinanceiroParcela::query()
                ->whereHas('financeiro', fn ($q) => $q->where('pagarreceber', 'R')->where('cancelado', false))
                ->where('baixado', false)
                ->limit(10)->get()
                ->each(fn ($parcela) => $svc->baixarParcela($conta->id, $parcela->id));
        }
    }

    /**
     * @param  list<Cliente>  $clientes
     * @param  list<Produto>  $produtos
     */
    private function satelites(array $clientes, array $produtos): void
    {
        // Convênio (1 cliente vira conveniado).
        Convenio::firstOrCreate(
            ['empresa_id' => $this->empresa->id, 'cliente_id' => $clientes[0]->id],
            ['grupo_id' => $this->grupoId, 'descricao' => 'Convênio Empresa A', 'ativo' => true],
        );

        // Vale-gás (cupom pré-pago) via Service.
        if (ValeGas::query()->count() === 0) {
            app(ValeGasService::class)->emitir([
                'empresa_id' => $this->empresa->id,
                'grupo_id' => $this->grupoId,
                'cliente_id' => $clientes[1]->id,
                'produto_id' => $produtos[0]->id,
                'valor' => (float) $produtos[0]->preco_venda,
            ]);
        }

        // Comodato (vasilhame emprestado) via Service — move estoque.
        if (Comodato::query()->count() === 0) {
            $setor = Setor::query()->where('empresa_id', $this->empresa->id)->where('descricao', 'Depósito')->value('id');
            app(ComodatoService::class)->emprestar([
                'empresa_id' => $this->empresa->id,
                'grupo_id' => $this->grupoId,
                'cliente_id' => $clientes[2]->id,
                'produto_id' => $produtos[0]->id,
                'setor_id' => $setor,
                'quantidade' => 2,
            ]);
        }
    }
}
