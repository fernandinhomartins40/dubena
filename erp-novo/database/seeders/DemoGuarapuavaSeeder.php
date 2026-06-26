<?php

namespace Database\Seeders;

use App\Domain\Caixa\CaixaService;
use App\Domain\Estoque\EstoqueService;
use App\Domain\Pedido\PedidoService;
use App\Domain\Satelite\ComodatoService;
use App\Domain\Satelite\ConvenioFechamentoService;
use App\Domain\Satelite\ValeGasService;
use App\Domain\Tenant\TenantContext;
use App\Models\Apoio\Banco;
use App\Models\Apoio\Cargo;
use App\Models\Apoio\ContaMovimentoTipo;
use App\Models\Apoio\Parentesco;
use App\Models\Apoio\Segmento;
use App\Models\Apoio\TelefoneTipo;
use App\Models\Apoio\TipoExame;
use App\Models\Apoio\TipoPessoa;
use App\Models\Caixa\Cheque;
use App\Models\Caixa\Conta;
use App\Models\Cliente\Cliente;
use App\Models\Cliente\ClienteDependente;
use App\Models\Cliente\ClientePreco;
use App\Models\Cliente\ClienteTelefone;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Estoque\EstoqueSaldo;
use App\Models\Estoque\Setor;
use App\Models\Financeiro\CentroCusto;
use App\Models\Financeiro\Financeiro;
use App\Models\Financeiro\FinanceiroParcela;
use App\Models\Financeiro\PlanoConta;
use App\Models\Fiscal\MalhaFiscal;
use App\Models\Fiscal\OperacaoFiscal;
use App\Models\Frota\TipoCombustivel;
use App\Models\Frota\Veiculo;
use App\Models\Frota\VeiculoAbastecimento;
use App\Models\Frota\VeiculoDocumento;
use App\Models\Frota\VeiculoPneu;
use App\Models\Frota\VeiculoTipo;
use App\Models\Frota\VeiculoTrocaOleo;
use App\Models\Geografico\Bairro;
use App\Models\Geografico\Cidade;
use App\Models\Geografico\Rua;
use App\Models\Gestao\Documento;
use App\Models\Gestao\EmpresaBem;
use App\Models\Gestao\Mcmm;
use App\Models\Crm\Checklist;
use App\Models\Crm\ChecklistPergunta;
use App\Models\Crm\MetaVenda;
use App\Models\Crm\PosVenda;
use App\Models\Crm\Promocao;
use App\Models\Crm\Sorteio;
use App\Models\Crm\SorteioNumero;
use App\Models\Pedido\Pedido;
use App\Models\Pedido\PedidoOperacao;
use App\Models\Pedido\PedidoSituacao;
use App\Models\Produto\Produto;
use App\Models\Produto\ProdutoClasse;
use App\Models\Produto\UnidadeMedida;
use App\Models\Rh\Colaborador;
use App\Models\Rh\ColaboradorExame;
use App\Models\Rh\ColaboradorFamilia;
use App\Models\Rh\ColaboradorTurno;
use App\Models\Pagamento\GasDoPovoBeneficio;
use App\Models\Satelite\Comodato;
use App\Models\Satelite\Convenio;
use App\Models\Satelite\ValeGas;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * DEMO Guarapuava — massa de dados realista e ÍNTEGRA para testar TODAS as telas.
 *
 * Endereços usam Guarapuava/PR (cidade, bairros oficiais e ruas reais com CEP,
 * em database/seeders/data/guarapuava.php). Onde há regra de negócio (estoque,
 * pedido, caixa, satélites) passa pelos Services → saldos coerentes e invariantes
 * verdes, como em produção.
 *
 * Volume ALTO (paginação/listas cheias). NÃO é para produção real.
 * Uso: php artisan migrate:fresh --seed --seeder=Database\\Seeders\\DemoGuarapuavaSeeder
 *      (ou configure como seeder padrão — ver DatabaseSeeder).
 */
class DemoGuarapuavaSeeder extends Seeder
{
    private Empresa $empresa;
    private int $grupoId;
    /** @var array<string, mixed> */
    private array $geo;

    public function run(): void
    {
        $this->call(DeployAdminSeeder::class);
        $this->call(RbacSeeder::class);

        $this->empresa = Empresa::query()->orderBy('id')->firstOrFail();
        $this->grupoId = (int) $this->empresa->grupo_id;
        app(TenantContext::class)->set($this->empresa->id, $this->grupoId);

        $this->geo = require database_path('seeders/data/guarapuava.php');
        mt_srand(2025); // determinístico: mesma massa a cada reset

        $this->command?->info('→ apoio / cadastros base');
        $this->apoio();
        $this->estados();
        [$cidade, $bairros, $ruasPorBairro] = $this->geografico();

        $this->command?->info('→ produtos + estoque');
        [$produtos, $setores] = $this->produtosEEstoque();

        $this->command?->info('→ financeiro: plano de contas / centros');
        $this->financeiroCadastros();

        $this->command?->info('→ fiscal: malha + operações');
        $this->fiscal();

        $this->command?->info('→ clientes (200) com telefones/preços/dependentes');
        $clientes = $this->clientes($cidade, $bairros, $ruasPorBairro, $produtos);

        $this->command?->info('→ RH: colaboradores (30) + frota (20)');
        $colaboradores = $this->rh($bairros);
        $this->frota($colaboradores);

        $this->command?->info('→ situações + pedidos (efeitos reais via Service)');
        [$situacoes, $operacao] = $this->situacoesEOperacoes();
        $this->pedidos($clientes, $produtos, $setores, $situacoes, $operacao);

        $this->command?->info('→ caixa + cheques + financeiro avulso');
        $this->caixaEFinanceiro($clientes);

        $this->command?->info('→ satélites: convênio / vale-gás / comodato / gás do povo');
        $this->satelites($clientes, $produtos, $setores);

        $this->command?->info('→ CRM: promoções / sorteios / metas / pós-venda / checklist');
        $this->crm($clientes, $colaboradores);

        $this->command?->info('→ gestão: MCMM / documentos / bens');
        $this->gestao($produtos, $colaboradores);

        $this->command?->info('✓ DemoGuarapuavaSeeder concluído — banco populado.');
    }

    // ───────────────────────── apoio / cadastros ─────────────────────────

    private function apoio(): void
    {
        foreach (['Caixa Econômica', 'Itaú', 'Banco do Brasil', 'Bradesco', 'Santander', 'Sicredi'] as $i => $n) {
            Banco::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => $n], ['ativo' => true, 'codigo' => str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)]);
        }
        foreach (['Residencial', 'Comercial', 'Industrial', 'Revenda', 'Rural'] as $s) {
            Segmento::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => $s], ['ativo' => true]);
        }
        TipoPessoa::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Pessoa Física'], ['tipopessoacadastro' => 'F', 'ativo' => true]);
        TipoPessoa::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Pessoa Jurídica'], ['tipopessoacadastro' => 'J', 'ativo' => true]);
        TelefoneTipo::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Celular'], ['celular' => true, 'ativo' => true]);
        TelefoneTipo::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Residencial'], ['celular' => false, 'ativo' => true]);
        foreach (['Cônjuge', 'Filho(a)', 'Pai', 'Mãe', 'Irmão(ã)'] as $p) {
            Parentesco::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => $p], ['ativo' => true]);
        }
        foreach (['Admissional' => true, 'Periódico' => false, 'Demissional' => false] as $e => $adm) {
            TipoExame::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => $e], ['admissional' => $adm, 'ativo' => true]);
        }
        foreach (['Atendente', 'Entregador', 'Caixa', 'Gerente', 'Motorista', 'Estoquista'] as $c) {
            Cargo::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => $c], ['ativo' => true, 'salario_base' => mt_rand(1600, 4500)]);
        }
        ContaMovimentoTipo::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Recebimento de Venda'], ['pagarreceber' => 'R', 'ativo' => true]);
        ContaMovimentoTipo::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Pagamento a Fornecedor'], ['pagarreceber' => 'P', 'ativo' => true]);
    }

    private function estados(): void
    {
        $ufs = [
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia',
            'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás',
            'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
            'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
            'SE' => 'Sergipe', 'TO' => 'Tocantins',
        ];
        foreach ($ufs as $uf => $nome) {
            Estado::firstOrCreate(['uf' => $uf], ['descricao' => $nome]);
        }
    }

    /** @return array{0: Cidade, 1: array<string, Bairro>, 2: array<string, list<Rua>>} */
    private function geografico(): array
    {
        $c = $this->geo['cidade'];
        $cidade = Cidade::firstOrCreate(
            ['grupo_id' => $this->grupoId, 'descricao' => $c['descricao'], 'uf' => $c['uf']],
            ['cod_ibge' => $c['cod_ibge'], 'ativo' => true],
        );

        $bairros = [];
        foreach ($this->geo['bairros'] as $nome => $_) {
            $bairros[$nome] = Bairro::firstOrCreate(
                ['grupo_id' => $this->grupoId, 'cidade_id' => $cidade->id, 'descricao' => $nome],
                ['ativo' => true],
            );
        }

        $ruasPorBairro = [];
        foreach ($this->geo['ruas'] as $bairroNome => $ruas) {
            $bairro = $bairros[$bairroNome] ?? null;
            if (! $bairro) {
                continue;
            }
            foreach ($ruas as [$desc, $cep]) {
                // ruas não tem bairro_id no schema; agrupamos por bairro só na memória do seed.
                $ruasPorBairro[$bairroNome][] = Rua::firstOrCreate(
                    ['grupo_id' => $this->grupoId, 'cidade_id' => $cidade->id, 'descricao' => $desc],
                    ['cep' => $cep, 'ativo' => true],
                );
            }
        }

        return [$cidade, $bairros, $ruasPorBairro];
    }

    // ───────────────────────── produtos + estoque ─────────────────────────

    /** @return array{0: list<Produto>, 1: array<string, Setor>} */
    private function produtosEEstoque(): array
    {
        $glp = ProdutoClasse::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'GLP'], ['ativo' => true]);
        $agua = ProdutoClasse::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Água'], ['ativo' => true]);
        $aces = ProdutoClasse::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Acessórios'], ['ativo' => true]);
        $un = UnidadeMedida::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Unidade'], ['sigla' => 'UN', 'ativo' => true]);

        $defs = [
            // [descricao, classe, venda, custo]
            ['Botijão P13 (13kg)', $glp, 120.00, 95.00],
            ['Botijão P13 - Recarga', $glp, 110.00, 88.00],
            ['Botijão P20 (20kg)', $glp, 195.00, 160.00],
            ['Botijão P45 (45kg)', $glp, 420.00, 350.00],
            ['Botijão P8 (8kg)', $glp, 90.00, 72.00],
            ['Botijão P2 (2kg)', $glp, 55.00, 40.00],
            ['Água Mineral 20L', $agua, 14.00, 8.00],
            ['Água Mineral 10L', $agua, 9.00, 5.00],
            ['Galão Vazio 20L', $agua, 25.00, 18.00],
            ['Mangueira p/ Gás 1,20m', $aces, 18.00, 9.00],
            ['Regulador de Gás', $aces, 45.00, 28.00],
            ['Kit Instalação Fogão', $aces, 80.00, 50.00],
        ];
        $produtos = [];
        foreach ($defs as [$desc, $classe, $venda, $custo]) {
            $produtos[] = Produto::firstOrCreate(
                ['grupo_id' => $this->grupoId, 'descricao' => $desc],
                [
                    'produtoclasse_id' => $classe->id, 'unidademedida_id' => $un->id,
                    'preco_venda' => $venda, 'preco_venda_minimo' => round($venda * 0.92, 2),
                    'custo_medio' => $custo, 'dias_giro' => mt_rand(7, 30), 'ativo' => true,
                ],
            );
        }

        $estoque = app(EstoqueService::class);
        $setores = [
            'Depósito' => Setor::firstOrCreate(['empresa_id' => $this->empresa->id, 'descricao' => 'Depósito'], ['ativo' => true]),
            'Loja' => Setor::firstOrCreate(['empresa_id' => $this->empresa->id, 'descricao' => 'Loja'], ['ativo' => true]),
            'Caminhão 01' => Setor::firstOrCreate(['empresa_id' => $this->empresa->id, 'descricao' => 'Caminhão 01'], ['ativo' => true]),
        ];
        foreach ($produtos as $p) {
            foreach ($setores as $setor) {
                if (EstoqueSaldo::withoutTenant()->where('setor_id', $setor->id)->where('produto_id', $p->id)->doesntExist()) {
                    $estoque->entrada($setor->id, $p->id, mt_rand(200, 800), (float) $p->custo_medio, 'seed-inicial');
                }
            }
        }

        return [$produtos, $setores];
    }

    private function financeiroCadastros(): void
    {
        $rec = PlanoConta::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Receitas', 'pagarreceber' => 'R'], ['codigo' => '1', 'nivel' => 1]);
        PlanoConta::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Receita de Vendas', 'pagarreceber' => 'R'], ['codigo' => '1.01', 'nivel' => 2, 'pai_id' => $rec->id]);
        $desp = PlanoConta::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Despesas', 'pagarreceber' => 'P'], ['codigo' => '2', 'nivel' => 1]);
        PlanoConta::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Compra de Mercadorias', 'pagarreceber' => 'P'], ['codigo' => '2.01', 'nivel' => 2, 'pai_id' => $desp->id]);
        PlanoConta::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Despesas Operacionais', 'pagarreceber' => 'P'], ['codigo' => '2.02', 'nivel' => 2, 'pai_id' => $desp->id]);
        CentroCusto::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Matriz'], ['codigo' => '01', 'nivel' => 1]);
        CentroCusto::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Entregas'], ['codigo' => '02', 'nivel' => 1]);
        CentroCusto::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Administrativo'], ['codigo' => '03', 'nivel' => 1]);
    }

    private function fiscal(): void
    {
        foreach (['Grupo GLP Padrão', 'Grupo Acessórios'] as $g) {
            MalhaFiscal::firstOrCreate(['grupo_id' => $this->grupoId, 'tipo' => 'grupos-fiscais', 'descricao' => $g], ['ativo' => true]);
        }
        foreach (['00' => 'Tributada integralmente', '40' => 'Isenta', '60' => 'ICMS cobrado por ST'] as $cod => $d) {
            MalhaFiscal::firstOrCreate(['grupo_id' => $this->grupoId, 'tipo' => 'cst-icms', 'codigo' => $cod], ['descricao' => $d, 'ativo' => true]);
        }
        OperacaoFiscal::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Venda de Mercadoria'], ['cfop' => '5102', 'movimenta_estoque' => true, 'movimenta_financeiro' => true, 'ativo' => true]);
        OperacaoFiscal::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Venda c/ ST'], ['cfop' => '5405', 'movimenta_estoque' => true, 'movimenta_financeiro' => true, 'ativo' => true]);
    }

    // ───────────────────────── clientes ─────────────────────────

    /**
     * @param  array<string, Bairro>  $bairros
     * @param  array<string, list<Rua>>  $ruasPorBairro
     * @param  list<Produto>  $produtos
     * @return list<Cliente>
     */
    private function clientes(Cidade $cidade, array $bairros, array $ruasPorBairro, array $produtos): array
    {
        $segmentos = Segmento::query()->where('grupo_id', $this->grupoId)->pluck('id')->all();
        $tipoPF = TipoPessoa::query()->where('descricao', 'Pessoa Física')->value('id');
        $tipoPJ = TipoPessoa::query()->where('descricao', 'Pessoa Jurídica')->value('id');
        $telTipo = TelefoneTipo::query()->where('descricao', 'Celular')->value('id');
        $parentescos = Parentesco::query()->where('grupo_id', $this->grupoId)->pluck('id')->all();
        $bairrosComRua = array_keys($ruasPorBairro);

        $clientes = [];
        for ($i = 0; $i < 200; $i++) {
            $pj = $i % 4 === 0; // 25% PJ
            $bairroNome = $bairrosComRua[$i % count($bairrosComRua)];
            $rua = $this->pick($ruasPorBairro[$bairroNome]);
            $nome = $pj ? $this->pick($this->geo['empresas']).' '.$this->pick($this->geo['sobrenomes']).' Ltda' : $this->nomePessoa();

            $cli = Cliente::create([
                'empresa_id' => $this->empresa->id, 'grupo_id' => $this->grupoId,
                'nome' => $nome,
                'fantasia' => $pj ? explode(' ', $nome)[0] : null,
                'tipopessoa_id' => $pj ? $tipoPJ : $tipoPF,
                'segmento_id' => $segmentos ? $this->pick($segmentos) : null,
                'cpf' => $pj ? null : $this->cpf(),
                'cnpj' => $pj ? $this->cnpj() : null,
                'cliente' => true,
                'fornecedor' => $i % 15 === 0,
                'nfemite' => $pj,
                'gasdopovo' => $i % 12 === 0,
                'ativo' => $i % 25 !== 0, // alguns inativos
                'cidade_id' => $cidade->id, 'bairro_id' => $bairros[$bairroNome]->id, 'rua_id' => $rua->id,
                'endereco' => $rua->descricao, 'numero' => (string) mt_rand(50, 2500),
                'cep' => $rua->cep, 'uf' => 'PR',
                'email' => $this->slug($nome).$i.'@exemplo.com.br',
            ]);
            $clientes[] = $cli;

            // Telefones (1-2)
            ClienteTelefone::create(['cliente_id' => $cli->id, 'telefonetipo_id' => $telTipo, 'telefone' => $this->celular(), 'whatsapp' => true]);
            if ($i % 3 === 0) {
                ClienteTelefone::create(['cliente_id' => $cli->id, 'telefonetipo_id' => $telTipo, 'telefone' => $this->celular(), 'whatsapp' => false]);
            }
            // Preço especial p/ alguns
            if ($i % 6 === 0) {
                $p = $this->pick($produtos);
                ClientePreco::create(['cliente_id' => $cli->id, 'produto_id' => $p->id, 'preco' => round((float) $p->preco_venda * 0.95, 2), 'desconto' => 5]);
            }
            // Dependentes p/ PF ocasional
            if (! $pj && $parentescos && $i % 7 === 0) {
                ClienteDependente::create(['cliente_id' => $cli->id, 'nome' => $this->nomePessoa(), 'parentesco_id' => $this->pick($parentescos), 'ativo' => true]);
            }
        }

        return $clientes;
    }

    // ───────────────────────── RH + frota ─────────────────────────

    /**
     * @param  array<string, Bairro>  $bairros
     * @return list<Colaborador>
     */
    private function rh(array $bairros): array
    {
        $cargos = Cargo::query()->where('grupo_id', $this->grupoId)->pluck('id')->all();
        $parentescos = Parentesco::query()->where('grupo_id', $this->grupoId)->pluck('descricao')->all();

        $cols = [];
        for ($i = 0; $i < 30; $i++) {
            $entregador = $i % 3 === 0;
            $col = Colaborador::create([
                'empresa_id' => $this->empresa->id, 'grupo_id' => $this->grupoId,
                'cargo_id' => $cargos ? $this->pick($cargos) : null,
                'nome' => $this->nomePessoa(),
                'cpf' => $this->cpf(),
                'telefone' => $this->celular(),
                'data_nascimento' => Carbon::now()->subYears(mt_rand(20, 55))->subDays(mt_rand(0, 360)),
                'data_admissao' => Carbon::now()->subMonths(mt_rand(1, 60)),
                'data_desligamento' => $i % 14 === 0 ? Carbon::now()->subMonths(mt_rand(1, 6)) : null,
                'entregador' => $entregador,
                'ativo' => $i % 14 !== 0,
            ]);
            $cols[] = $col;

            // Família
            if ($parentescos && $i % 2 === 0) {
                ColaboradorFamilia::create(['colaborador_id' => $col->id, 'nome' => $this->nomePessoa(), 'parentesco' => $this->pick($parentescos), 'data_nascimento' => Carbon::now()->subYears(mt_rand(1, 20))]);
            }
            // Exame admissional
            ColaboradorExame::create(['colaborador_id' => $col->id, 'tipo' => 'Admissional', 'realizado_em' => $col->data_admissao, 'vencimento' => $col->data_admissao?->copy()->addYear(), 'resultado' => 'apto']);
            // Turnos (seg-sex)
            foreach ([1, 2, 3, 4, 5] as $dia) {
                ColaboradorTurno::create(['colaborador_id' => $col->id, 'dia_semana' => $dia, 'entrada' => '08:00', 'saida' => '18:00']);
            }
        }

        return $cols;
    }

    /** @param list<Colaborador> $colaboradores */
    private function frota(array $colaboradores): void
    {
        $tipo = VeiculoTipo::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Caminhão'], ['ativo' => true]);
        $tipoMoto = VeiculoTipo::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Motocicleta'], ['ativo' => true]);
        $diesel = TipoCombustivel::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Diesel'], ['ativo' => true]);
        $gasolina = TipoCombustivel::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Gasolina'], ['ativo' => true]);

        for ($i = 0; $i < 20; $i++) {
            $moto = $i % 3 === 0;
            $km = mt_rand(20_000, 180_000);
            $v = Veiculo::create([
                'empresa_id' => $this->empresa->id, 'grupo_id' => $this->grupoId,
                'veiculotipo_id' => $moto ? $tipoMoto->id : $tipo->id,
                'tipocombustivel_id' => $moto ? $gasolina->id : $diesel->id,
                'placa' => $this->placa(),
                'descricao' => ($moto ? 'Moto Entrega ' : 'Caminhão GLP ').($i + 1),
                'renavam' => (string) mt_rand(10_000_000_000, 99_999_999_999),
                'km_atual' => $km, 'ativo' => $i % 10 !== 0,
            ]);
            // Abastecimentos (3 por veículo)
            for ($a = 0; $a < 3; $a++) {
                $litros = mt_rand(20, 90);
                $vl = $moto ? 6.10 : 6.40;
                VeiculoAbastecimento::create(['veiculo_id' => $v->id, 'data' => Carbon::now()->subDays($a * 12 + 3), 'km' => $km - $a * 600, 'litros' => $litros, 'valor_litro' => $vl, 'valor_total' => round($litros * $vl, 2), 'tanque_cheio' => true]);
            }
            // Troca de óleo + pneus + documento
            VeiculoTrocaOleo::create(['veiculo_id' => $v->id, 'data' => Carbon::now()->subMonths(2), 'km' => $km - 4000, 'valor' => mt_rand(120, 400), 'observacao' => 'Troca preventiva']);
            VeiculoPneu::create(['veiculo_id' => $v->id, 'posicao' => 'Dianteiro Esquerdo', 'marca' => $this->pick(['Pirelli', 'Goodyear', 'Michelin']), 'data_instalacao' => Carbon::now()->subMonths(5), 'km_instalacao' => $km - 12000]);
            VeiculoDocumento::create(['empresa_id' => $this->empresa->id, 'veiculo_id' => $v->id, 'tipo' => 'Licenciamento', 'numero' => (string) mt_rand(100000, 999999), 'emissao' => Carbon::now()->subMonths(6), 'vencimento' => Carbon::now()->addMonths(6)]);
        }
    }

    // ───────────────────────── pedidos ─────────────────────────

    /** @return array{0: array<string, PedidoSituacao>, 1: PedidoOperacao} */
    private function situacoesEOperacoes(): array
    {
        $op = PedidoOperacao::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Venda'], ['venda_direta' => true, 'ativo' => true]);
        PedidoOperacao::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Disk-Gás'], ['disk' => true, 'ativo' => true]);

        $defs = [
            ['Em Aberto', 'PENDENTE', '#F59E0B', 0],
            ['Em Separação', 'PENDENTE', '#3B82F6', 1],
            ['Em Rota', 'PENDENTE', '#A855F7', 2],
            ['Concluído', 'CONCLUIDO', '#22C55E', 3],
            ['Cancelado', 'CANCELADO', '#EF4444', 4],
        ];
        $situacoes = [];
        foreach ($defs as [$desc, $efeito, $cor, $ordem]) {
            $situacoes[$efeito === 'CONCLUIDO' ? 'concluido' : ($efeito === 'CANCELADO' ? 'cancelado' : $desc)] = PedidoSituacao::firstOrCreate(
                ['grupo_id' => $this->grupoId, 'descricao' => $desc],
                ['efeito' => $efeito, 'cor' => $cor, 'ordem' => $ordem, 'ativo' => true],
            );
        }

        return [$situacoes, $op];
    }

    /**
     * @param  list<Cliente>  $clientes
     * @param  list<Produto>  $produtos
     * @param  array<string, Setor>  $setores
     * @param  array<string, PedidoSituacao>  $situacoes
     */
    private function pedidos(array $clientes, array $produtos, array $setores, array $situacoes, PedidoOperacao $operacao): void
    {
        $svc = app(PedidoService::class);
        $loja = $setores['Loja']->id;
        $vendaveis = array_values($produtos); // todos os produtos são vendáveis
        $pendentes = array_values(array_filter($situacoes, fn ($s) => $s->efeito->value === 'PENDENTE'));

        for ($i = 0; $i < 500; $i++) {
            $cliente = $clientes[$i % count($clientes)];
            // 60% concluído, 25% pendente (espalhado nas colunas), 15% cancelado
            $r = $i % 20;
            $situacao = $r < 12 ? $situacoes['concluido'] : ($r < 17 ? $this->pick($pendentes) : $situacoes['cancelado']);

            $qtdItens = 1 + ($i % 3);
            $itens = [];
            for ($j = 0; $j < $qtdItens; $j++) {
                $p = $vendaveis[($i + $j) % count($vendaveis)];
                $itens[] = ['produto_id' => $p->id, 'quantidade' => 1 + (($i + $j) % 4)];
            }

            $svc->criar(
                [
                    'empresa_id' => $this->empresa->id, 'grupo_id' => $this->grupoId,
                    'cliente_id' => $cliente->id, 'setor_id' => $loja,
                    'pedidooperacao_id' => $operacao->id,
                    'pedidosituacao_id' => $situacao->id,
                    'entregador_user_id' => null,
                    'entrega_urgente' => $i % 9 === 0,
                    'datahora' => Carbon::now()->subDays($i % 60)->subHours($i % 24),
                ],
                $itens,
            );
        }
    }

    // ───────────────────────── caixa + cheques + financeiro ─────────────────────────

    /** @param list<Cliente> $clientes */
    private function caixaEFinanceiro(array $clientes): void
    {
        $svc = app(CaixaService::class);
        $conta = Conta::query()->where('empresa_id', $this->empresa->id)->where('descricao', 'Caixa Geral')->first();
        if (! $conta) {
            $conta = $svc->criarConta([
                'empresa_id' => $this->empresa->id, 'grupo_id' => $this->grupoId,
                'descricao' => 'Caixa Geral', 'tipo' => 'caixa', 'saldo_inicial' => 2000.00,
            ]);
            $svc->abrir($conta->id);
        }
        // Conta bancária adicional
        Conta::firstOrCreate(['empresa_id' => $this->empresa->id, 'descricao' => 'Banco - Conta Movimento'], ['grupo_id' => $this->grupoId, 'tipo' => 'banco', 'saldo_inicial' => 15000, 'saldo_atual' => 15000, 'ativo' => true]);

        // Baixa ~80 parcelas a receber (entra no caixa).
        FinanceiroParcela::query()
            ->whereHas('financeiro', fn ($q) => $q->where('pagarreceber', 'R')->where('cancelado', false))
            ->where('baixado', false)->limit(80)->get()
            ->each(function ($parcela) use ($svc, $conta) {
                try { $svc->baixarParcela($conta->id, $parcela->id); } catch (\Throwable) { /* ignora colisão idempotente */ }
            });

        // Contas a pagar avulsas (compra de mercadoria a fornecedores).
        $fin = app(\App\Domain\Financeiro\FinanceiroService::class);
        $fornecedores = array_values(array_filter($clientes, fn ($c) => $c->fornecedor));
        $planoPagar = PlanoConta::query()->where('descricao', 'Compra de Mercadorias')->value('id');
        $centro = CentroCusto::query()->where('descricao', 'Matriz')->value('id');
        foreach (array_slice($fornecedores, 0, 15) as $k => $forn) {
            $fin->criar([
                'empresa_id' => $this->empresa->id, 'grupo_id' => $this->grupoId,
                'cliente_id' => $forn->id, 'pagarreceber' => 'P',
                'planoconta_id' => $planoPagar, 'centrocusto_id' => $centro,
                'valor' => mt_rand(800, 6000), 'descricao' => 'Compra de botijões - lote '.($k + 1),
                'data_emissao' => Carbon::now()->subDays($k * 3)->toDateString(),
            ], numParcelas: 1 + ($k % 3), diasPrimeira: 15 + $k);
        }

        // Cheques recebidos
        $banco = Banco::query()->where('grupo_id', $this->grupoId)->first();
        foreach (array_slice($clientes, 0, 12) as $k => $cli) {
            Cheque::create([
                'empresa_id' => $this->empresa->id, 'grupo_id' => $this->grupoId,
                'cliente_id' => $cli->id, 'banco_id' => $banco?->id,
                'especie' => 'recebido', 'numero' => (string) mt_rand(100000, 999999),
                'agencia' => (string) mt_rand(1000, 4999), 'conta_corrente' => (string) mt_rand(10000, 99999).'-'.mt_rand(0, 9),
                'titular' => $cli->nome, 'valor' => mt_rand(100, 1200),
                'bom_para' => Carbon::now()->addDays(mt_rand(5, 45)), 'situacao' => $k % 3 === 0 ? 'COMPENSADO' : 'CARTEIRA',
            ]);
        }
    }

    // ───────────────────────── satélites ─────────────────────────

    /**
     * @param  list<Cliente>  $clientes
     * @param  list<Produto>  $produtos
     * @param  array<string, Setor>  $setores
     */
    private function satelites(array $clientes, array $produtos, array $setores): void
    {
        $glp = array_values(array_filter($produtos, fn ($p) => str_contains($p->descricao, 'P13')))[0] ?? $produtos[0];

        // Convênios (8) + fechamento de 1 (gera financeiro).
        $convClientes = array_values(array_filter($clientes, fn ($c) => $c->tipopessoa_id && $c->cnpj));
        $convenios = [];
        foreach (array_slice($convClientes, 0, 8) as $k => $cli) {
            $convenios[] = Convenio::firstOrCreate(
                ['empresa_id' => $this->empresa->id, 'cliente_id' => $cli->id],
                ['grupo_id' => $this->grupoId, 'descricao' => 'Convênio '.$cli->fantasia, 'dia_fechamento' => 25, 'dia_vencimento' => 10, 'ativo' => true],
            );
        }

        // Vale-gás (40 cupons) via Service.
        for ($i = 0; $i < 40; $i++) {
            app(ValeGasService::class)->emitir([
                'empresa_id' => $this->empresa->id, 'grupo_id' => $this->grupoId,
                'cliente_id' => $clientes[$i]->id, 'produto_id' => $glp->id,
                'valor' => (float) $glp->preco_venda,
                'validade' => Carbon::now()->addMonths(3)->toDateString(),
            ]);
        }

        // Comodatos (15) via Service — move estoque (vasilhame emprestado).
        $deposito = $setores['Depósito']->id;
        for ($i = 0; $i < 15; $i++) {
            app(ComodatoService::class)->emprestar([
                'empresa_id' => $this->empresa->id, 'grupo_id' => $this->grupoId,
                'cliente_id' => $clientes[$i + 50]->id, 'produto_id' => $glp->id,
                'setor_id' => $deposito, 'quantidade' => 1 + ($i % 3),
            ]);
        }

        // Gás do Povo (benefício social) p/ clientes marcados.
        $gpClientes = array_values(array_filter($clientes, fn ($c) => $c->gasdopovo));
        foreach (array_slice($gpClientes, 0, 12) as $cli) {
            GasDoPovoBeneficio::firstOrCreate(
                ['empresa_id' => $this->empresa->id, 'cliente_id' => $cli->id, 'competencia' => Carbon::now()->format('Y-m')],
                ['nis' => (string) mt_rand(10_000_000_000, 99_999_999_999), 'valor' => 110.00, 'situacao' => 'ATIVO'],
            );
        }
    }

    // ───────────────────────── CRM ─────────────────────────

    /**
     * @param  list<Cliente>  $clientes
     * @param  list<Colaborador>  $colaboradores
     */
    private function crm(array $clientes, array $colaboradores): void
    {
        Promocao::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Black Friday GLP'], ['inicio' => Carbon::now()->startOfMonth(), 'fim' => Carbon::now()->endOfMonth(), 'desconto_percentual' => 10, 'ativo' => true]);
        Promocao::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Cliente Fiel'], ['inicio' => Carbon::now()->subMonth(), 'fim' => Carbon::now()->addMonth(), 'desconto_percentual' => 5, 'ativo' => true]);

        // Sorteio com números p/ 30 clientes.
        $sorteio = Sorteio::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Sorteio de Natal'], ['data_sorteio' => Carbon::now()->addMonths(2), 'situacao' => 'ABERTO']);
        for ($i = 0; $i < 30; $i++) {
            SorteioNumero::firstOrCreate(['sorteio_id' => $sorteio->id, 'numero' => str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)], ['cliente_id' => $clientes[$i]->id]);
        }

        // Metas de venda por colaborador (competência atual).
        foreach (array_slice($colaboradores, 0, 12) as $col) {
            MetaVenda::firstOrCreate(
                ['empresa_id' => $this->empresa->id, 'colaborador_id' => $col->id, 'competencia' => Carbon::now()->format('Y-m')],
                ['meta_valor' => mt_rand(8000, 25000), 'realizado_valor' => mt_rand(2000, 24000)],
            );
        }

        // Pós-venda (NPS) p/ 40 clientes.
        for ($i = 0; $i < 40; $i++) {
            PosVenda::create([
                'empresa_id' => $this->empresa->id, 'cliente_id' => $clientes[$i]->id,
                'data' => Carbon::now()->subDays($i), 'nota' => mt_rand(6, 10),
                'canal' => $this->pick(['telefone', 'whatsapp', 'app']), 'situacao' => $i % 4 === 0 ? 'pendente' : 'concluido',
                'observacao' => 'Entrega avaliada.',
            ]);
        }

        // Checklist operacional + perguntas.
        $chk = Checklist::firstOrCreate(['grupo_id' => $this->grupoId, 'descricao' => 'Checklist de Entrega'], ['ativo' => true]);
        foreach (['Veículo limpo?', 'EPI em uso?', 'Documentos em dia?', 'Troco conferido?'] as $o => $perg) {
            ChecklistPergunta::firstOrCreate(['checklist_id' => $chk->id, 'pergunta' => $perg], ['ordem' => $o]);
        }
    }

    // ───────────────────────── gestão ─────────────────────────

    /**
     * @param  list<Produto>  $produtos
     * @param  list<Colaborador>  $colaboradores
     */
    private function gestao(array $produtos, array $colaboradores): void
    {
        // MCMM (movimentação de controle) — 20 lançamentos.
        for ($i = 0; $i < 20; $i++) {
            $p = $produtos[$i % count($produtos)];
            Mcmm::create(['empresa_id' => $this->empresa->id, 'data' => Carbon::now()->subDays($i), 'descricao' => 'Ajuste de controle '.($i + 1), 'tipo' => $i % 2 ? 'entrada' : 'saida', 'quantidade' => mt_rand(1, 20), 'produto_id' => $p->id]);
        }
        // Documentos da empresa.
        foreach (['Alvará de Funcionamento', 'Licença Ambiental', 'AVCB - Bombeiros', 'Contrato Social'] as $d) {
            Documento::firstOrCreate(['empresa_id' => $this->empresa->id, 'descricao' => $d], ['tipo' => 'empresa', 'numero' => (string) mt_rand(1000, 99999), 'emissao' => Carbon::now()->subYear(), 'validade' => Carbon::now()->addYear()]);
        }
        // Bens / patrimônio.
        foreach ([['Empilhadeira', 45000], ['Computador Caixa', 3500], ['Balança Industrial', 2800], ['Tanque GLP', 80000]] as [$desc, $valor]) {
            EmpresaBem::firstOrCreate(['empresa_id' => $this->empresa->id, 'descricao' => $desc], ['data_aquisicao' => Carbon::now()->subYears(2), 'valor_aquisicao' => $valor, 'taxa_depreciacao_anual' => 10, 'valor_residual' => $valor * 0.1, 'ativo' => true]);
        }
        unset($colaboradores);
    }

    // ───────────────────────── helpers ─────────────────────────

    /** @template T @param list<T> $arr @return T */
    private function pick(array $arr)
    {
        return $arr[array_rand($arr)];
    }

    private function nomePessoa(): string
    {
        return $this->pick($this->geo['nomes']).' '.$this->pick($this->geo['sobrenomes']);
    }

    private function slug(string $s): string
    {
        $s = strtolower(preg_replace('/[^a-zA-Z]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s));
        return substr($s, 0, 12);
    }

    private function cpf(): string
    {
        return sprintf('%03d.%03d.%03d-%02d', mt_rand(0, 999), mt_rand(0, 999), mt_rand(0, 999), mt_rand(0, 99));
    }

    private function cnpj(): string
    {
        return sprintf('%02d.%03d.%03d/0001-%02d', mt_rand(0, 99), mt_rand(0, 999), mt_rand(0, 999), mt_rand(0, 99));
    }

    private function celular(): string
    {
        return sprintf('(42) 9%04d-%04d', mt_rand(0, 9999), mt_rand(0, 9999));
    }

    private function placa(): string
    {
        $l = fn () => chr(mt_rand(65, 90));
        return $l().$l().$l().mt_rand(0, 9).$l().mt_rand(0, 9).mt_rand(0, 9);
    }
}
