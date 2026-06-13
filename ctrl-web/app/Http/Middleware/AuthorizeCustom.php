<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Session;
use Illuminate\Auth\Access\AuthorizationException as AuthException;

class AuthorizeCustom
{
    /**
     * @param $request
     * @param Closure $next
     * @return mixed
     * @throws AuthException
     */
    public function handle(Request $request, Closure $next)
    {
        $authorize = $this->authorization($request);
        // dump($request->route()->getName());
        // dd($authorize);
        if ($authorize) {
            return $next($request);
        } else {
            throw new AuthException('Você não possui autorização para acessar esta página.');
        }
    }

    private function authorization($request)
    {
        $user = $request->user();
        $route = $request->route()->getName();
        $validar = $this->validar($user, $route);

        if (strpos($route, 'ajax.') !== false)
            return true;

        if ($request->ajax())
            return true;

        return $validar;
    }

    private function validar($user, $route)
    {
        $rota = explode('.', $route);

        if ($this->excecoes()->contains($route) || $this->excecoes()->contains($rota[0])) return true;

        if ($this->especiais()->has($route)) return $this->validacoesEspeciais($route);

        if (isset($rota[0]) && strpos($rota[0], 'report') !== false) {
            $validarReport = $this->validarReport($user, $route);

            return $validarReport;
        } else if (isset($rota[1]) && ($rota['1'] == "filtro" ||
            !hasStrIn($rota[1], ["index", "acesso"])) && $rota[0] != "financeiro") {
            $rotaindex = $rota[0] . ".index";
            $permissions = $this->getPermissoes($rotaindex);

            if (!is_null($permissions))
                return $this->validacoesGerais($permissions, $rota[1]);
            else
                return false;
        } else if (isset($rota[1]) && strpos($rota[1], 'index') !== false) {
            $permissions = $this->getPermissoes($route);

            return !is_null($permissions) && $permissions->visualizar == 1;
        }

        return true;
    }

    private function validarReport($user, $route)
    {
        if (strpos($route, '.pdf') !== false)
            $route = str_replace('.pdf', '', $route);

        else if (strpos($route, '.filtro') !== false)
            $route = str_replace('.filtro', '', $route);

        else if (strpos($route, 'filtro') !== false)
            $route = str_replace('filtro', '', $route);

        else if (strpos($route, 'pdf') !== false)
            $route = str_replace('pdf', '', $route);

        else if (strpos($route, '.sub') !== false)
            $route = str_replace('.sub', '', $route);

        else if (strpos($route, '.xls') !== false)
            $route = str_replace('.xls', '', $route);

        else if (strpos($route, 'xls') !== false)
            $route = str_replace('xls', '', $route);

        if ($this->excecoes()->contains($route))
            return true;

        $permissions = $this->getPermissoes($route);
        return !is_null($permissions) && $permissions->visualizar == 1;
    }

    private function validacoesEspeciais($route)
    {
        $rotaindex = (object) $this->especiais()->get($route);
        $permissoes = $this->getPermissoes($rotaindex->rota);

        if (strpos($route, "logs") !== false) {
            $support = \Auth::user()->support;
            return $support == '1';
        }

        if (is_null($permissoes)) return false;

        return $this->validacoesGerais($permissoes, $rotaindex->permissao);
    }

    private function validacoesGerais($permissions, $rota)
    {
        $vis = $permissions->visualizar == 1;
        $cri = $permissions->criar == 1;
        $edi = $permissions->editar == 1;
        $del = $permissions->deletar == 1;

        $ability = $this->abilities()->where("ability", $rota)->pluck('ability')->first();
        switch ($ability) {
            case 'show':
                return $vis;
                break;

            case 'create':
                return $vis && $cri;
                break;

            case 'store':
                return $vis && $cri;
                break;

            case 'edit':
                return $vis && $edi;
                break;

            case 'update':
                return $vis && $edi;
                break;

            case 'destroy':
                return $vis && $del;
                break;

            case 'filtro':
                return $vis;
                break;

            case 'pdf':
                return $vis;
                break;

            case 'xls':
                return $vis;
                break;

            case 'contrato':
                return $vis;
                break;

            case 'createDespesa':
                return $vis && $cri;
                break;

            case 'createReceita':
                return $vis && $cri;
                break;

            default:
                return false;
                break;
        }
    }

    private function abilities()
    {
        return collect([
            ["ability" => "index"],
            ["ability" => "show"],
            ["ability" => "edit"],
            ["ability" => "update"],
            ["ability" => "store"],
            ["ability" => "create"],
            ["ability" => "destroy"],
            ["ability" => "filtro"],
            ["ability" => "pdf"],
            ["ability" => "xls"],
            ["ability" => "contrato"],
            ["ability" => "createDespesa"],
            ["ability" => "createReceita"],
        ]);
    }

    private function especiais()
    {
        return collect([
            "vendaativa.ocorrencia"           => ["rota" => "vendaativa.index",          "permissao" => "create"],
            "vendavalegas.imprimirvalegas"    => ["rota" => "valegas.index",             "permissao" => "edit"],
            "empresaconfig.senhamestre"       => ["rota" => "empresaconfig.senhamestre", "permissao" => "show"],
            "nfemitida"                       => ["rota" => "nfemitida.index",           "permissao" => "show"],
            "report.mcmm"                     => ["rota" => "mcmm.index",                "permissao" => "show"],
            "cliente.editFromPedidos"         => ["rota" => "cliente.index",             "permissao" => "edit"],
            "cliente.createFromPedidos"       => ["rota" => "cliente.index",             "permissao" => "create"],
            "nfemitida.consultarnf"           => ["rota" => "nfemitida.index",           "permissao" => "show"],
            "logs.reports"                    => ["rota" => "logs",                      "permissao" => "show"],
            "logs.filtrar"                    => ["rota" => "logs",                      "permissao" => "show"],
            "nfemitida.exportarxls"           => ["rota" => "nfemitida.index",           "permissao" => "show"],
            "nfemitida.cartacorrecaonf"       => ["rota" => "nfemitida.index",           "permissao" => "show"],
            "nfemitida.transmitir"            => ["rota" => "nfemitida.index",           "permissao" => "create"],
            "empresaconfig.email"             => ["rota" => "empresaconfig.index",       "permissao" => "view"],
            "nfemitida.import.txt"            => ["rota" => "nfemitida.index",           "permissao" => "create"],
            "definir.store"                   => ["rota" => "definir.index",             "permissao" => "create"],
            "report.segSetorProd"             => ["rota" => "report.clientes",           "permissao" => "show"],
            "nfrecebida.import.xml"           => ["rota" => "nfrecebida.index",          "permissao" => "create"],
            "nfimposto.createByNf"            => ["rota" => "nfimposto.index",           "permissao" => "create"],
            "satcfe.transmitir"               => ["rota" => "satcfe.index",              "permissao" => "create"],
            "report.nfrecebidacomprovantepdf" => ["rota" => "nfrecebida.index",          "permissao" => "show"],
            "report.clientesativospdf"        => ["rota" => "report.clientes",           "permissao" => "show"],
            "pix.baixar"                      => ["rota" => "pix.index",                 "permissao" => "show"],
            "documento.downloadversao"        => ["rota" => "documentogestao.index",     "permissao" => "show"],
            "fechamentomensalgestao.download" => ["rota" => "fechamentomensalgestao.index", "permissao" => "show"],
            "comodatogestao.vencidosPdf"      => ["rota" => "comodatogestao.index",      "permissao" => "show"],
            "comodatogestao.giroPdf"          => ["rota" => "comodatogestao.index",      "permissao" => "show"],
        ]);
    }

    private function excecoes()
    {
        return collect([
            "0"     => "empresa.change",
            "1"     => "sped.dowload",
            "2"     => "importReportCartao",
            "3"     => "boleto.retornoparcelas",
            "4"     => "pedido.index",
            "5"     => "pedido",
            "6"     => "financeiro",
            "7"     => "comanda",
            "8"     => "redirect_pdf_blank",
        ]);
    }

    private function getPermissoes($route)
    {
        return Session::get('permissoes')->where('descricao', $route)->first();
    }
}
