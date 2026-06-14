<?php

namespace App\Monitora\Http\Controllers;
use DB;
use Excel;
use Session;
use Redirect;
use App\Monitora\Models\Empresa;
use App\Monitora\Models\Veiculo;
use App\Monitora\Models\Position;
use Carbon\Carbon;
use Barryvdh\DomPDF;
use App\Monitora\Models\Veiculotipo;
use App\Http\Requests;
use Illuminate\Http\Request;
use PHPExcel_Worksheet_Drawing;
use PHPExcel_Worksheet_MemoryDrawing;
use Illuminate\Support\Facades\Response;

class ReportController extends Controller
{

    protected $empresa_id;
    protected $grupo_id;

    public function __construct()
    {
        $this->middleware('auth');
    }

    private function definition()
    {
        $this->empresa_id = Session::get('empresa_padrao')->id;
        $this->grupo_id = Session::get('empresa_padrao')->grupo_id;
    }

    public function reportEventosVeiculo($pdf = false)
    {
        $filtros = $_GET;
        $veiculo_id = $filtros['veiculo_id'];

        $dataInicio = $filtros['datainicio'];
        $dataTermino = $filtros['datafim'];
        $dataini = Carbon::createFromFormat('d/m/Y H:i', $dataInicio);
        $datafin = Carbon::createFromFormat('d/m/Y H:i', $dataTermino);

        $veiculo = Veiculo::find($veiculo_id);
        $veiculotipo = $veiculo->veiculotipo;

        $postraccar = Array();
        $query = array('uniqueId' => $veiculo->unique_id);
        try {
            $response = buscarDadosTraccar('devices', $query);
            $veiculos_traccar = json_decode($response);
            if(count($veiculos_traccar)==0){
                echo "Veículo não encontrado na base do serviço de rastreamento";
                return;
            }
            $from = substr(($dataini->toIso8601String()),0,19)."Z";
            $to = substr(($datafin->toIso8601String()),0,19)."Z";
            $deviceid = $veiculos_traccar[0]->id;
            $query = array('deviceId' => $deviceid, 'from' => $from, 'to' => $to);
            $response = buscarDadosTraccar('reports/route', $query);
            $postraccar = json_decode($response);
            
            DB::beginTransaction();
            foreach($postraccar as $pos){
                 $dt = Carbon::parse($pos->fixTime)->timezone('America/Sao_Paulo');
                if($pos->valid && $pos->address != null){
                    $positions = Position::where('deviceid', $veiculo->unique_id)
                                         ->where('dhposition', $dt)
                                         ->get();
                    foreach($positions as $position){
                        if($position->address == null){
                            $position->address = $pos->address;
                            $position->save();
                        }
                    }
                }
            }
        } catch (ValidationException $e) {
            DB::rollback();
            echo $e->getMessage();
            return;
        } catch (\Exception $e) {
            DB::rollback();
            echo $e->getMessage();
            return;
        }
        DB::commit();
        
        $positions = Position::where('deviceid', $veiculo->deviceid)
                ->whereBetween('dhposition', [$dataini, $datafin])
                ->orderBy('dhposition', 'desc')
                ->get();        
        
        $velocidades = Array();
        $parados = Array();
        foreach($positions as $position){
            if($position->speed > $veiculotipo->velocidade_maxima){
                $position->velocidade_maxima = $veiculotipo->velocidade_maxima;
                array_push($velocidades, $position);
            }
        }
        $i = 0;
        while($i<count($positions)){
            $tempo_parado = 0;
            $menor = ($positions[$i]->speed < 1);
            if($menor){
                $position_parado = $positions[$i];
                $position_parado["data_fim"] = Carbon::parse($positions[$i]->dhposition);
                $dtfim = Carbon::parse($positions[$i]->dhposition);
                while($menor){
                    $position_parado["data_inicio"] = Carbon::parse($positions[$i+1]->dhposition);
                    $dtini = Carbon::parse($positions[$i+1]->dhposition);
                    $position_parado["tempo_parado"] = $dtfim->diffInSeconds($dtini);
                    $tempo_parado = $position_parado["tempo_parado"];
                    if ((count($positions) + 1) == $i) {
                            $position_parado["latitude"] = $positions[$i + 1]->latitude;
                            $position_parado["longitude"] = $positions[$i + 1]->longitude;
                    }
                    $i++;
                    if ($i < count($positions)-1){
                        $menor = $positions[$i + 1]->speed < 1;
                    }
                    else {
                        $menor = false;
                    }
                }
            }
            if ($tempo_parado > 300){
                array_push($parados, $position_parado);
            }
            $i++;
        }
        $filtro = "Período: ".$dataInicio." a ".$dataTermino;
        $titulo = 'Relatório de Paradas/Excesso de Velocidade por Veículo';
        return view('monitora.reports.eventosVeiculoPDF', compact('titulo', 'filtro', 'parados', 'velocidades', 'veiculo', 'veiculotipo'));
    }

}
