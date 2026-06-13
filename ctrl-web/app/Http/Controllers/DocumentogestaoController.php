<?php

namespace App\Http\Controllers;

use App\Configuracoesgerais;
use App\Documentotipo;
use App\Empresaconfig;
use App\Setor;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
    use PhpOffice\PhpPresentation\IOFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentogestaoController extends Controller
{
    public function index()
    {
        return view("documentos.gestao");
    }

    public function getDashboard()
    {
        try {
            $dataTabela60dias = $this->getDataTabela60dias();
            $dataTabelaDocumentos = $this->dataTabelaDocumentos();
            
            return responseSuccess(["dataTabela60dias" => $dataTabela60dias,
                                    "dataTabelaDocumentos" => $dataTabelaDocumentos]);
        } catch(Exception $e){
            return getErrorsException($e);
        }
    }

    public function dataTabelaDocumentos(){
        $tipos = Documentotipo::where('grupo_id', Session::get("empresa_padrao")->grupo_id)->get();
        $tree = [];
        foreach($tipos as $tipo){
            $docs = [];
            foreach($tipo->documentos as $documento){
                $vers = [];
                foreach($documento->versoesAtivas as $versao){
                    $qtdiasvencer = Carbon::now()->startOfDay()->diffInDays(Carbon::parse($versao->datavencimento)->startOfDay(), false);
                    $alerta = 'green';
                    if($qtdiasvencer < 0){
                        $alerta = 'red';
                    } elseif($qtdiasvencer <= $tipo->diasalerta){
                        $alerta = 'orange';
                    }
                    array_push($vers, ["id"=>$versao->id, "descricao"=>$versao->numeroversao, "dataemissao"=>$versao->dataemissao, 
                                       "datavencimento"=>$versao->datavencimento, "qtdiasvencer"=>$qtdiasvencer, "alerta"=>$alerta]);
                }
                if(count($vers)>0){
                    array_push($docs, ["id"=>$documento->id, "descricao"=>$documento->descricao, "_children"=>$vers]);
                }
            }
            if(count($docs)>0){
                array_push($tree, ["id"=>$tipo->id, "descricao"=>$tipo->descricao, "_children"=>$docs]);
            }
        }
        return $tree;
    }

    public function getDataTabela60dias(){
        $query = 
        "  SELECT qry.*, case when qtdiasvencer < 0 then 'red' else case when qtdiasvencer <= diasalerta then 'orange' else 'green' end end as alerta FROM ( " .
        "  SELECT " .
        "  d.DOCUMENTOTIPO_ID, t.DESCRICAO AS tipodescricao,d.DESCRICAO AS documentodescricao, v.DATAVENCIMENTO, v.NUMEROVERSAO, v.id, " .
        "  trunc(datavencimento) - trunc(sysdate) AS qtdiasvencer, t.diasalerta " .
        "  FROM DOCUMENTOS d  " .
        "  INNER JOIN DOCUMENTOVERSAOS v ON v.DOCUMENTO_ID = d.ID  " .
        "  INNER JOIN DOCUMENTOTIPOS t ON t.id = d.DOCUMENTOTIPO_ID  " .
        "  WHERE d.EMPRESA_ID = ".Session::get("empresa_padrao")->id." AND v.ATIVO = 1 " .
        "  ) qry WHERE QTDIASVENCER <=60 " .
        "  ORDER BY QTDIASVENCER ";

        return DB::select($query);
    }


}