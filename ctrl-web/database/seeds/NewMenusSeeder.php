<?php

use App\Menu;
use Illuminate\Database\Seeder;

class NewMenusSeeder extends Seeder
{

    protected $parents;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->setParents();

        $this->createCupomMenu();
        $this->createPromover();
        $this->createReportPromotores();
        $this->createPixBaixa();
        $this->createReportVendasGerais();
        $this->createMaloteFechamento();
        $this->createMenuComodatos();
        $this->createMenuDocumentacao();
        $this->createMenuExtrato();
        $this->createConciliacaoContabil();
        $this->createAppSub();
        $this->createMenuFechamentoMensal();
        $this->createMenuDashboardgerencial();
        $this->createMenuUsoSenha();
        $this->createMenuInconsistencia();
        $this->createMenuLogCercas();
        $this->createMenuVideo();
        $this->createNfcst();
        $this->createNfclastrib();
        $this->createSorteio();
    }

    private function setParents()
    {
        $titulos = [
            "Cadastros",
            "Operações",
            "Financeiros",
            "Ferramentas",
            "Relatórios",
            "Relacionamentos",
            "Comodatos",
            "Gerais",
            "Setorização",
            "Operacionais"
        ];

        $parents = Menu::whereIn("titulo", $titulos)
            ->whereNull("descricao")
            ->get();

        $this->parents = $parents;
    }

    private function getParent($parent, $base = true)
    {
        $menu = $this->parents->filter(function ($item) use ($parent, $base) {
            $isTitle = $item->titulo == $parent;
            $isBase = true;

            if ($base) $isBase = is_null($item->parent_id);

            return $isTitle && $isBase;
        })->first();

        if (is_null($menu)) {
            throw new \Exception("Menu parente não encontrado: $parent");
        }

        return $menu;
    }

    private function getMenu($needle)
    {
        return Menu::where("descricao", $needle)->first();
    }

    private function createMenu($parent_id, $titulo, $descricao, $ordem)
    {
        $data = [
            'parent_id' => $parent_id,
            'titulo'    => $titulo,
            'descricao' => $descricao,
            'ordem'     => $ordem
        ];

        $menu = $this->getMenu($descricao);

        if (is_null($menu)) {
            $menu = Menu::create($data);
        } else {
            $menu->update($data);
        }

        return $menu;
    }

    private function createCupomMenu()
    {
        $parent = $this->getParent("Operações");

        $this->createMenu($parent->id, 'Cupons Gás em Casa', 'cupons.index', 460);
    }

    private function createPromover()
    {
        $parent = $this->getParent("Operações");

        $this->createMenu($parent->id, 'Promover Vendas', 'promover.index', 460);
    }

    private function createReportPromotores()
    {
        $parent = $this->getParent("Relacionamentos", false);

        $this->createMenu($parent->id, 'Relatório de Ausentes', 'report.promoausentes', 461);
        $this->createMenu($parent->id, 'Relatório de Visitas', 'report.promovisitas', 462);
    }

    private function createPixBaixa()
    {
        $parent = $this->getParent("Financeiros");

        $this->createMenu($parent->id, 'Baixa do PIX', 'pix.index', 5);
    }

    private function createReportVendasGerais()
    {
        $parent = $this->getParent("Relatórios");

        $vendasMenu = Menu::whereNull("descricao")
            ->where("parent_id", $parent->id)
            ->where("titulo", "Vendas")
            ->first();

        $this->createMenu($vendasMenu->id, 'Relatório de Vendas Geral GLP', 'report.vendasgerais', 192);
        $this->createMenu($vendasMenu->id, 'Acompanhamento de Convênios e Gás de Bolso', 'conveniogbgestao.index', 193);
        $this->createMenu($vendasMenu->id, 'Apresentação Mensal de Vendas', 'vendasmensaisgestao.index', 194);
        $this->createMenu($vendasMenu->id, 'Resumo de Venda Diária por Setor', 'report.resumovendadia', 195);
    }

    private function createMaloteFechamento()
    {
        $parent = $this->getParent("Financeiros");

        $this->createMenu($parent->id, 'Fechamento de Malotes', 'malotefechamento.index', 120);
    }

    private function createMenuComodatos()
    {
        $parent = $this->getParent("Operações");

        $com = Menu::where("descricao", "comodato.index")->first();

        if ($com->parent_id == $parent->id) {
            $com->update([
                "descricao" => null,
            ]);
        } else {
            $com = $this->getParent("Comodatos", false);
        }

        $this->createMenu($com->id, 'Comodatos', 'comodato.index', 461);

        $this->createMenu($com->id, 'Gestão de Comodatos', 'comodatogestao.index', 462);
    }

    private function createMenuDocumentacao()
    {
        $parent = $this->getParent("Gerais", false);

        $com = Menu::where("titulo", "Documentação")->where("parent_id", $parent->id)->first();

        if ($com) {
            $com->update([
                "descricao" => null,
                "ordem" => 510,
            ]);
        } else {
            $com = $this->createMenu($parent->id, 'Documentação', null, 510);
        }

        $this->createMenu($com->id, 'Tipos de Documentos', 'documentotipo.index', 461);
        $this->createMenu($com->id, 'Documentos', 'documento.index', 471);
        $this->createMenu($com->id, 'Controle de Documentos', 'documentogestao.index', 472);
    }

    private function createMenuExtrato()
    {
        $parent = $this->getParent("Financeiros");

        $this->createMenu($parent->id, 'Importação Extrato Bancário', 'importExtrato.index', 130);
    }

    private function createMenuFechamentoMensal()
    {
        $parent = $this->getParent("Financeiros");

        $this->createMenu($parent->id, 'Fechamento Mensal Gerencial', 'fechamentomensalgestao.index', 140);
    }

    private function createConciliacaoContabil()
    {
        $parent = $this->getParent("Financeiros");

        $this->createMenu($parent->id, 'Conciliação Contábil x Financeiro', 'conciliacao.index', 120);
    }

    private function createMenuDashboardgerencial()
    {
        $parent = $this->getParent("Relatórios");

        $this->createMenu($parent->id, 'Dashboard Gerencial', 'dashboardgerencial.index', 480);
    }

    private function createAppSub()
    {
        $mainParent = $this->getParent("Operações");

        $parent = Menu::where("parent_id", $mainParent->id)
            ->where("descricao", null)
            ->where("titulo", "App Gás em Casa")
            ->first();

        if (is_null($parent)) {
            $parent = Menu::create([
                "parent_id" => $mainParent->id,
                "descricao" => null,
                "titulo" => "App Gás em Casa",
                "ordem" => 130
            ]);
        }

        $appNoti = Menu::where("descricao", "appnotification.index")->first();
        if ($appNoti->parent_id !== $parent->id) {
            $appNoti->update([
                "parent_id" => $parent->id
            ]);
        }

        $cupom = Menu::where("descricao", "cupons.index")->first();
        if ($cupom->parent_id !== $parent->id) {
            $cupom->update([
                "parent_id" => $parent->id
            ]);
        }

        $this->createMenu($parent->id, "Controle de Giro", "appgiro.index", 240);
    }

    private function createMenuUsoSenha()
    {
        $mainParent = $this->getParent("Relatórios");

        $parent = Menu::where("parent_id", $mainParent->id)
            ->where("descricao", null)
            ->where("titulo", "Gestão")
            ->first();

        if (is_null($parent)) {
            $parent = Menu::create([
                "parent_id" => $mainParent->id,
                "descricao" => null,
                "titulo" => "Gestão",
                "ordem" => 130
            ]);
        }

        $this->createMenu($parent->id, "Uso de Senha Mestre", "report.logsenha", 535);
    }

    private function createMenuInconsistencia()
    {
        $parent = $this->getParent("Setorização", false);

        $this->createMenu($parent->id, 'Inconsistências Cadastrais', 'inconsistencia.index', 540);
    }

    private function createMenuLogCercas()
    {
        $parent = $this->getParent("Operacionais", false);

        $this->createMenu($parent->id, 'Entrada/Saída de Setores', 'report.logcercas', 540);
    }

    private function createMenuVideo()
    {
        $mainParent = $this->getParent("Operações");

        $parent = Menu::where("parent_id", $mainParent->id)
            ->where("descricao", null)
            ->where("titulo", "App Gás em Casa")
            ->first();

        $this->createMenu($parent->id, "Video de Inicialização", "appvideo.index", 240);
    }

    private function createNfcst()
    {
        $mainParent = $this->getParent("Cadastros");

        $parent = Menu::where("parent_id", $mainParent->id)
            ->where("descricao", null)
            ->where("titulo", "Administração")
            ->first();

        $this->createMenu($parent->id, "CST IBS/CBS", "nfcst.index", 430);
    }

    private function createNfclastrib()
    {
        $mainParent = $this->getParent("Cadastros");

        $parent = Menu::where("parent_id", $mainParent->id)
            ->where("descricao", null)
            ->where("titulo", "Administração")
            ->first();

        $this->createMenu($parent->id, "Classificação Tributária", "nfclastrib.index", 430);
    }

    private function createSorteio()
    {
        $parent = $this->getParent("Operações");

        $this->createMenu($parent->id, 'Sorteios', 'sorteio.index', 460);
    }
}
