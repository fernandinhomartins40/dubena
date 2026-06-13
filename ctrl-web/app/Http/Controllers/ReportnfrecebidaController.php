<?php

namespace App\Http\Controllers;

use DB;
use Session;
use App\Services\CarbonCustom as Carbon;
use App\Empresa;
use App\Colaborador;
use App\Nfrecebida;
use App\Pedidosituacao;
use Illuminate\Http\Request;
use App\Repository\SelectRepository;

class ReportnfrecebidaController extends Controller
{
    private $repository;
    private $empresa_id;
    private $grupo_id;

    public function __construct(SelectRepository $repository){
        $this->repository = $repository;
    }

    public function filtroComprovante(){
        $this->definition();
        $nf_id = $_GET["id"];
        $nf = Nfrecebida::find($nf_id);
        $titulo = 'AUTORIZAÇÃO DE DESCARREGAMENTO';
        $empresa = Empresa::find($this->empresa_id);
        //return view('reports.nf.recebidas.comprovante_pdf')->with(compact('nf', 'titulo', 'empresa'));
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4','portrait')->loadView('reports.nf.recebidas.comprovante_pdf', compact('nf','titulo','empresa'));
        return $pdf->stream();
    }


    private function definition(){
        //busca id da empresa logada
        $this->empresa_id = Session::get('empresa_padrao')->id;
        //busca id do grupo
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
        //set empresa repository
        $this->repository->setEmpresa($this->empresa_id);
        //set grupo repository
        $this->repository->setGrupo($this->grupo_id);
    }
}

