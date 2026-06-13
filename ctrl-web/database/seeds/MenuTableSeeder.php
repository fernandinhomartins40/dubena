s
<?php

use App\User;
use App\Menu;
use App\Empresa;
use Illuminate\Database\Seeder;

class MenuTableSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $menus = Menu::where('parent_id', '!=', null)->orderBy('parent_id', 'desc')->get();
        foreach ($menus as $menu) {
            // if($menu->users() !== null) {
            $menu->menuuser()->delete();
            // }
            $menu->delete();
        }

        $menus = Menu::all();
        foreach ($menus as $menu) {
            $menu->delete();
        }
        $i = 1;
        //cadastros **MESTRE
        $this->checaIds($i, NULL, 'Cadastros', '', 10);
        $i = $this->submenuCadastro($i, $i + 1);

        //operaçõe
        $this->checaIds($i, NULL, 'Operações', '', 450);
        $i = $this->submenuOperacoes($i, $i + 1);

        //financeir
        $this->checaIds($i, NULL, 'Financeiros', '', 460);
        $i = $this->submenuFinanceiroPrincipal($i, $i + 1);

        $this->checaIds($i, NULL, 'Ferramentas', '', 460);
        $i = $this->submenuFerramenas($i, $i + 1);

        $this->checaIds($i, NULL, 'Relatórios', '', 460);
        $i = $this->submenuRelatorios($i, $i + 1);

        $users = User::where('ativo', true)->get();
        // dd($users);
        $empresas_id = Empresa::where('ativo', true)->select('id')->get();
        foreach ($users as $user) {
            $menus = Menu::all();
            $user->empresas()->detach();
            foreach ($empresas_id as $emp) {
                foreach ($menus as $menu) {
                    DB::table('menuusers')->insert([
                        "empresa_id" => $emp->id,
                        "menu_id"    => $menu->id,
                        "user_id"    => $user->id,
                        "visualizar" => 1,
                        "criar"      => 1,
                        "editar"     => 1,
                        "baixar"     => 1,
                        "alerta"     => 0,
                        "deletar"    => 1,
                    ]);
                    $menu->ordem = $menu->id;
                    $menu->save();
                }
            }
            $user->empresas()->attach($empresas_id);
        }
    }

    //cadastro id => 1000
    private function submenuCadastro($p_id, $i)
    {
        //Administração **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Administração', '', 101);
        $i = $this->submenuAdministracao($id, $i);

        //clientes/fornecedores **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Clientes/Fornecedores', '', 100);
        $i = $this->submenuClientesFornecedores($id, $i);

        //colaboradores **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Colaboradores', '', 50);
        $i = $this->submenuColaboradores($id, $i);

        //Financeiro **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Financeiros', '', 355);
        $i = $this->submenuFinanceiroCadastros($id, $i);

        //Gerais **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Gerais', '', 395);
        $i = $this->submenuGerais($id, $i);

        //Produtos **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Produtos', '', 50);
        $i = $this->submenuProdutos($id, $i);

        //Veículos **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Veículos', '', 50);
        $i = $this->submenuVeiculos($id, $i);

        //Empresas **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Empresas', '', 50);
        $i = $this->submenuEmpresas($id, $i);

        return $i;
    }

    //Operacoes id => 2
    private function submenuOperacoes($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Estoques', '', 455);
        $i = $this->submenuEstoque($id, $i);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Fiscais', '', 460);
        $i = $this->submenuFiscal($id, $i);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Gestão de Veículos', '', 455);
        $i = $this->submenuGestaoVeiculos($id, $i);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Vale Gás', '', 460);
        $i = $this->submenuValeGas($id, $i);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Comodatos', 'comodato.index', 460);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Fechamentos de Convênios', 'fechamentoconvenio.index', 460);

        $id = $i++;
        $this->checaIds($id, $p_id, 'MCMM', 'mcmm.index', 460);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Notificar Aplicativo', 'appnotification.index', 460);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Pedidos', 'pedido.index', 455);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Pesquisa de Checklists', 'checklist.index', 465);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Pesquisa de Pós-Venda', 'posvenda.index', 465);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Rastreamento de Veículos', 'monitoramento.acesso', 465);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Venda Ativa', 'vendaativa.index', 465);

        return $i;
    }

    //FINANCEIRO id => 3
    private function submenuFinanceiroPrincipal($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Boletos', '', 190);
        $i = $this->submenuFinanceiroBoletos($id, $i);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Caixas', 'caixa.index', 10);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Cheques Emitidos', 'chequeemitido.index', 50);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Cheques Recebidos', 'chequerecebido.index', 50);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Contas a Receber', 'contasreceber.index', 20);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Contas a Pagar', 'contaspagar.index', 20);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Importação Relatório de Cartão', 'importReportCartao.index', 20);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Lançamento de Despesa', 'financeiro.createDespesa', 40);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Lançamento de Receita', 'financeiro.createReceita', 50);

        return $i;
    }

    //boletos id => 30
    private function submenuFinanceiroBoletos($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Gerar Boletos', 'boleto.index', 190);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Remessa de Boletos', 'remessa.index', 190);

        return $i;
    }

    //ferramentas id => 4
    private function submenuFerramenas($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Configurações da Empresa', 'empresaconfig.index', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Definir Papéis', 'definir.index', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Senha Mestra', 'empresaconfig.senhamestre', 460);

        return $i;
    }

    //ferramentas id => 5
    private function submenuRelatorios($p_id, $i)
    {
        $id = $i++;
        //relatorios 1 **PAI
        $this->checaIds($id, $p_id, 'Administrativo', '', 50);
        $i = $this->submenuReportAdministrativo($id, $i);
        //relatorios 1 **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Financeiros', '', 50);
        $i = $this->submenuReportFinanceiro($id, $i);

        // $this->checaIds(150000, 5, 'Notas Fiscais', '', 50);
        // $this->submenuReportNotasFiscais();
        //relatorios 2 **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Operacionais', '', 50);
        $i = $this->submenuReportOperacionais($id, $i);
        //relatorios 2 **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Vendas', '', 50);
        $i = $this->submenuReportVendas($id, $i);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Vale Gás', 'report.valegas', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Checklists', 'report.checklist', 460);

        return $i;
    }

    //id = 50000
    private function submenuReportAdministrativo($p_id, $i)
    {
        $id = $i++;
        //relatorios 1 **PAI
        $this->checaIds($id, $p_id, 'Gestão de Comodatos', '', 50);
        $i = $this->submenuReportAdministrativoGestaoComodato($id, $i);
        //relatorios 1 **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Gestão de Pessoal', '', 50);
        $i = $this->submenuReportAdministrativoGestaoPessoal($id, $i);
        //relatorios 1 **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Documentos Fiscais', '', 50);
        $i = $this->submenuReportNotasFiscais($id, $i);

        return $i;
    }

    //id = 51000
    private function submenuReportAdministrativoGestaoComodato($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Comodatos Ativos', 'report.comodatosativos', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Controle de Comodatos', 'report.controlecomodato',
                460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Giro de Comodatos', 'report.girocomodato', 460);

        return $i;
    }

    //id = 52000
    private function submenuReportAdministrativoGestaoPessoal($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Colaboradores Aniversariantes',
                'report.colaboradoresaniversariantes', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Exames de Colaboradores',
                'report.colaboradoresexames', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Familiares por Faixa Etária',
                'report.colaboradoresfaixaetaria', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Férias de Colaboradores',
                'report.colaboradoresferiasvencimento', 460);

        return $i;
    }

    //id = 53000
    private function submenuReportNotasFiscais($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Entrada de Documentos Fiscais', 'report.nfentradas', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Notas Fiscais Emitidas', 'report.nfemitidas', 460);

        return $i;
    }

    //id = 300000
    private function submenuReportVendas($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Mapa de Metas x Vendas', 'report.mapametas', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Acompanhamento de Promoções',
                'report.acompanhamentopromo', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Pós-Venda', 'report.posvenda', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Vendas Convênio', 'report.vendaconvenio', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Vendas Diárias', 'report.vendadiaria', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Venda Direta', 'report.vendadireta', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Venda por Entregador', 'report.vendaentregador',
                460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Venda por Operações', 'report.vendaoperacoes', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Venda por Segmento', 'report.vendasegmento', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Vendas por Setor', 'report.vendasetor', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Vendas por App', 'report.vendasetor', 460);

        return $i;
    }

    //id = 100000
    private function submenuReportFinanceiro($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Comissões', 'report.comissoes', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Contas a Receber', 'report.contasreceber', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Contas a Pagar', 'report.contaspagar', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Despesas por Plano de Contas', 'report.despesas',
                460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Receitas por Plano de Contas', 'report.receitas',
                460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Despesas por Centro de Custos',
                'report.despesas_cc', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Receitas por Centro de Custos',
                'report.receitas_cc', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Movimentações de Caixas', 'report.fluxocaixa', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Malote por Colaborador',
                'report.colaboradormalote', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Lançamentos Retroativos', 'report.retroativo', 460);

        return $i;
    }

    //id = 200000
    private function submenuReportOperacionais($p_id, $i)
    {
        //relatorios 2 **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Estoque', '', 50);
        $i = $this->submenuReportOperacionaisEstoque($id, $i);

        $id = $i++;
        $this->checaIds($id, $p_id, 'Entregas', '', 50);
        $i = $this->submenuReportOperacionaisEntregas($id, $i);

        //relatorios 2 **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Gestão de Frota', '', 50);
        $i = $this->submenuReportOperacionaisGestaoFrota($id, $i);

        //relatorios 2 **PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relacionamentos', '', 50);
        $i = $this->submenuReportOperacionaisRelacionamentos($id, $i);

        return $i;
    }

    //215000
    private function submenuReportOperacionaisEntregas($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Mapa de Entregas', 'report.mapaentregas', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Mapa de Entregas Atrasadas', 'report.mapaentregasatrasadas',
                460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Mapa de Entregas Pendentes', 'report.mapaentregaspendentes',
                460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Mapa de Entregas Por Coordenadas',
                'report.mapaentregascoordenadas', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Tempo de Entregas', 'report.tempoentrega', 460);

        return $i;
    }

    //210000
    private function submenuReportOperacionaisEstoque($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Estoque Geral', 'report.estoquegeral', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Estoque GLP', 'report.estoqueglp', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Movimentações de Estoque', 'report.movimentacoes',
                460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Requisições de Estoque',
                'report.estoquerequisicao', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Transferências de Estoque',
                'report.estoquetransferencia', 460);

        return $i;
    }

    //220000
    private function submenuReportOperacionaisGestaoFrota($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Abastecimentos', 'report.abastecimento', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Movimentação Veicular', 'report.gestaofrota', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Trocas de Óleo', 'report.trocaoleo', 460);

        return $i;
    }

    //230000
    private function submenuReportOperacionaisRelacionamentos($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Mala Direta', 'maladireta.index', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Aniversariantes que Compram',
                'report.aniversariantescompram', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Clientes', 'report.clientes', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Clientes Aniversariantes',
                'report.clientesaniversariantes', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Clientes Inativos por Falta de Compra',
                'report.clientesInativosFaltaCompra', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Clientes Incompletos', 'report.incompletos', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Clientes por Bairro', 'report.clientesbairros',
                460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Clientes sem Compras', 'report.semcompras', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Convênios', 'report.convenio', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Fornecedores', 'report.fornecedores', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Funcionários de Convênios',
                'report.conveniofuncionarios', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Relatório de Interações com Clientes', 'report.interacoes', 460);

        return $i;
    }

    //filhos ADMINISRAÇÃO id => 10
    private function submenuAdministracao($p_id, $i)
    {
        $this->checaIds($i++, $p_id, 'Androids', 'android.index', 400);
        $this->checaIds($i++, $p_id, 'Configurações Gerais', 'configuracoesGerais.index', 385);
        $this->checaIds($i++, $p_id, 'Cst Cofins', 'nfcofins.index', 415);
        $this->checaIds($i++, $p_id, 'Cst/Csosn Icms', 'nficms.index', 425);
        $this->checaIds($i++, $p_id, 'Cst Ipi', 'nfipi.index', 410);
        $this->checaIds($i++, $p_id, 'Cst Pis', 'nfpis.index', 420);
        $this->checaIds($i++, $p_id, 'Grupos de Empresas', 'empresas_grupo.index', 390);
        $this->checaIds($i++, $p_id, 'Layout de Cobranças', 'layoutbancos.index', 390);
        $this->checaIds($i++, $p_id, 'Ocorrências de Remessas', 'ocorrenciasremessas.index', 390);
        $this->checaIds($i++, $p_id, 'Situação de NF', 'nfsituacao.index', 390);
        $this->checaIds($i++, $p_id, 'Tabela IBPT', 'ibpt.index', 390);
        $this->checaIds($i++, $p_id, 'Tipo de Usuário', 'roles.index', 169);
        $this->checaIds($i++, $p_id, 'Usuários', 'user.index', 170);

        return $i;
    }

    //clientes/fornecedores id => 11
    private function submenuClientesFornecedores($p_id, $i)
    {
        $this->checaIds($i++, $p_id, 'Clientes/Fornecedores', 'cliente.index', 30);
        $this->checaIds($i++, $p_id, 'Promoções', 'promocao.index', 380);
        $this->checaIds($i++, $p_id, 'Segmentos', 'segmento.index', 45);
        $this->checaIds($i++, $p_id, 'Situações de Contatos', 'clientecontatosituacao.index', 450);
        $this->checaIds($i++, $p_id, 'Tipos de Contatos', 'clientecontatotipo.index', 435);
        $this->checaIds($i++, $p_id, 'Tipos de Pessoas', 'tipopessoa.index', 55);
        $this->checaIds($i++, $p_id, 'Tipos de Telefones', 'telefonetipo.index', 55);

        return $i;
    }

    //filhos COLABORADORES id => 12
    private function submenuColaboradores($p_id, $i)
    {
        $this->checaIds($i++, $p_id, 'Cargos', 'cargo.index', 280);
        $this->checaIds($i++, $p_id, 'Colaboradores', 'colaborador.index', 250);
        $this->checaIds($i++, $p_id, 'Estados Civis', 'estadocivil.index', 90);
        $this->checaIds($i++, $p_id, 'Parentescos', 'parentesco.index', 100);
        $this->checaIds($i++, $p_id, 'Tipos de Exames', 'tipoexame.index', 120);

        return $i;
    }

    //financeiro id=>13
    private function submenuFinanceiroCadastros($p_id, $i)
    {
        $this->checaIds($i++, $p_id, 'Bancos', 'banco.index', 190);
        $this->checaIds($i++, $p_id, 'Centros de Custo', 'centrocusto.index', 360);
        $this->checaIds($i++, $p_id, 'Condições de Pagamento', 'condicaopagamento.index', 365);
        $this->checaIds($i++, $p_id, 'Contas', 'conta.index', 200);
        $this->checaIds($i++, $p_id, 'Planos de Conta', 'planoconta.index', 365);
        $this->checaIds($i++, $p_id, 'Tipo de Movimento de Contas', 'contamovimentotipo.index', 365);

        return $i;
    }

    //filhos GERAIS id=>14
    private function submenuGerais($p_id, $i)
    {
        //NFe *PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'NFe', '', 410);
        $i = $this->submenuNfe($id, $i);

        //Pedidos *PAI
        $id = $i++;
        $this->checaIds($id, $p_id, 'Pedidos', '', 400);
        $i = $this->submenuPedidos($id, $i);

        //Vendas
        $id = $i++;
        $this->checaIds($id, $p_id, 'Vendas', '', 510);
        $i = $this->submenuVendas($id, $i);

        //Recessos
        $id = $i++;
        $this->checaIds($id, $p_id, 'Recessos', '', 510);
        $i = $this->submenuRecessos($id, $i);

        //Setorização
        $id = $i++;
        $this->checaIds($id, $p_id, 'Setorização', '', 510);
        $i = $this->submenuSetorizacao($id, $i);

        //Bens
        $id = $i++;
        $this->checaIds($id, $p_id, 'Bens', 'empresabens.index', 510);

        //checklists
        $id = $i++;
        $this->checaIds($id, $p_id, 'Cadastros de Checklists', 'cadastrochecklist.index', 465);

        //pos-venda
        $id = $i++;
        $this->checaIds($id, $p_id, 'Cadastros de Pós-Venda', 'posvendacadastro.index', 465);

        return $i;
    }

    private function submenuSetorizacao($p_id, $i)
    {
        //Bairros
        $id = $i++;
        $this->checaIds($id, $p_id, 'Bairros', 'bairro.index', 505);
        //Cidades
        $id = $i++;
        $this->checaIds($id, $p_id, 'Cidades', 'cidade.index', 505);
        //Ruas
        $id = $i++;
        $this->checaIds($id, $p_id, 'Ruas', 'rua.index', 505);
        //Setores
        $id = $i++;
        $this->checaIds($id, $p_id, 'Setores', 'setor.index', 505);

        return $i;
    }

    //Produtos id => 15
    private function submenuProdutos($p_id, $i)
    {
        //filhos PRODUTOS
        $id = $i++;
        $this->checaIds($id, $p_id, 'Atualização de Preços', 'atualizarprecos.index', 325);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Classes de Produtos', 'produtoclasse.index', 325);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Produtos', 'produto.index', 320);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Unidades de Medida', 'unidademedida.index', 330);

        return $i;
    }

    //Veiculos id => 16
    private function submenuVeiculos($p_id, $i)
    {
        //filhos VEÍCULOS
        $id = $i++;
        $this->checaIds($id, $p_id, 'Tipos de Combustíveis', 'tipocombustivel.index', 340);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Tipos de Documentos', 'tipodocumento.index', 345);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Tipos de Veículos', 'veiculotipo.index', 350);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Veículos', 'veiculo.index', 335);

        return $i;
    }

    //pedidos id => 142
    private function submenuPedidos($p_id, $i)
    {
        //filhos Pedidos
        $id = $i++;
        $this->checaIds($id, $p_id, 'Motivos de Não Vendas', 'motivonaovenda.index', 405);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Motivos de Atrasos de Pedidos', 'pedidomotivoatraso.index', 410);
        //Operações de Pedidos
        $id = $i++;
        $this->checaIds($id, $p_id, 'Operações de Pedidos', 'pedidooperacao.index', 380);
        //status de pedidos
        $id = $i++;
        $this->checaIds($id, $p_id, 'Status de Pedidos', 'pedidosituacao.index', 380);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Tipos de Ocorrências', 'vendaativaocorrenciatipos.index', 380);

        return $i;
    }

    //estoque id => 20
    private function submenuEstoque($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Ajustes de Estoque', 'estoquesetor.index', 455);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Consultas de Estoque', 'consultaestoquesetor.index', 455);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Estoque Físico', 'estoquefisico.index', 455);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Requisições de Estoque', 'estoquerequisicao.index', 455);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Transferências de Estoque', 'estoquetransferencias.index', 455);

        return $i;
    }

    //fiscal id => 21
    private function submenuFiscal($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Controle de Inventário', 'inventario.index', 455);
        $id = $i++;
        $this->checaIds($id, $p_id, 'EFD Contribuições', 'spedcontribuicao.index', 455);
        $id = $i++;
        $this->checaIds($id, $p_id, 'EFD Contribuições Créditos', 'spedcreditos.index', 455);
        $id = $i++;
        $this->checaIds($id, $p_id, 'EFD ICMS IPI', 'spedfiscal.index', 456);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Emissões de Notas Fiscais', 'nfemitida.index', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Emissões de SAT CF-e', 'satcfe.index', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Lançamento de Documentos', 'nfrecebida.index', 461);

        return $i;
    }

    //Gestao de veiculos id => 22
    private function submenuGestaoVeiculos($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Abastecimentos', 'veiculoabastecimento.index', 350);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Movimentações de Veículos', 'veiculoentradasaida.index', 350);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Trocas de Óleo', 'veiculotrocaoleo.index', 350);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Trocas de Pneus', 'veiculopneu.index', 350);

        return $i;
    }

    //valegas id =>23
    private function submenuValeGas($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Cancelar Vale Gás', 'valegascancelar.index', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Consultar Vale Gás', 'valegasconsulta.index', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Imprimir Vale Gás', 'valegas.index', 460);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Vender Vale Gás', 'vendavalegas.index', 460);

        return $i;
    }

//filhos NFe id => 141
    private function submenuNfe($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Configuração NFCe Pedido', 'confgNfcePedido.index', 445);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Grupos Fiscais', 'grupofiscal.index', 445);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Impostos', 'nfimposto.index', 445);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Operações NFe', 'nfoperacao.index', 445);

        return $i;
    }

    //vendas id => 143
    private function submenuVendas($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Comissões', 'colaboradorcomissoes.index', 510);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Metas de Vendas', 'metavenda.index', 455);

        return $i;
    }

    //recessos id => 144
    private function submenuRecessos($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Recessos', 'recessos.index', 510);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Tipos de Recessos', 'tiporecessos.index', 455);

        return $i;
    }

    // Empresas id => 2424
    public function submenuEmpresas($p_id, $i)
    {
        $id = $i++;
        $this->checaIds($id, $p_id, 'Empresas', 'empresa.index', 385);
        $id = $i++;
        $this->checaIds($id, $p_id, 'Regional', 'regiao.index', 400);

        return $i;
    }

    private function checaIds($id, $p_id, $titulo, $descricao, $ordem)
    {
        $attr = [
            'id'        => $id,
            'parent_id' => $p_id,
            'titulo'    => $titulo,
            'descricao' => $descricao,
            'ordem'     => $ordem
        ];
        $row = Menu::find($attr['id']);
        $menu = new Menu();
        if ($row === null) {
            $menu->exists = false;
            return Menu::create($attr);
        } else {
            $menu->exists = true;
            dump($attr);
            dd("!travou!");
            return $menu->update($attr);
        }
    }

}
