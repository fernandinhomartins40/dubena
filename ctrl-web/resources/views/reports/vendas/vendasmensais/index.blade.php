
@extends('layouts.mainmenu')

@section('content')

<link href="{{URL::to('plugins/tabulator/css/tabulator_bootstrap3.min.css')}}" rel="stylesheet" type="text/css" />
<script src="{{URL::to('plugins/tabulator/js/tabulator.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('js/tabulatorLocalization.js')}}" type="text/javascript"></script>

<style>
    label {
        display: inline-block;
        max-width: 100%;
        margin-bottom: 5px;
        font-weight: normal;
        margin-right: 20px;
        margin-left: 5px;
    }
    .box-container {
      position: relative;
      border: 1px solid #acabab;
      border-radius: 8px;
      padding: 10px;
      margin-top: 30px;
    }

    .box-title {
      position: absolute;
      top: -20px;
      background: white;
      padding: 0 10px;
      font-weight: bold;
      color: #333;
      font-size: 16px;
    }

    .box-content {
      font-size: 14px;
      color: #444;
    }

    .content {
      padding: 15px;
      margin-right: auto;
      margin-left: auto;
      padding-left: 5px;
      padding-right: 5px;
    }
   .tableTitle {
      font-size: 16px;
      font-weight: bold;
      font-family: 'Segoe UI', sans-serif;
      margin-top: 15px;
   }

    .tableSubTitle {
      font-size: 13px;
      font-weight: normal;
      font-family: 'Segoe UI', sans-serif;
      margin-top: 5px;
      margin-bottom: 20px;
      font-style: italic;
   }

   .tabulator {
        background-color: #fff;
        border: none;
        margin-bottom: 0px;
    }
    #tbl_vendageral {
        border: 1px solid #ccc;
    }
    .tbl_vendasetor {
        border: 1px solid #ccc;
    }
    .tabulator-col-title {
        text-align: center;
    }
    .tabulator .tabulator-header .tabulator-col {
        background-color: #3f51b5;
        color: white;
        font-weight: bold;
        text-transform: uppercase;
        border-right: 1px solid white;
        font-size: 18px;
    }
    /* Estiliza a linha de "DIF (%)" */
    .tabulator-row.diff-row {
        font-weight: bold;
        font-size: 13px !important;
        background-color: #f0f0f0;
    }

    .tabulator-cell {
        border-right: 1px solid #ddd;
        border-bottom: 1px solid #ddd;
        padding: 8px 10px;
        font-size: larger;
    }
    .
    /* Cores condicionais para a linha DIF (%) */
    .tabulator-row.diff-row .negativo {
        color: #d32f2f !important;
    }
    .tabulator-row.diff-row .positivo {
        color: #388e3c;
    }
    /* Bordas das células */
    .tabulator-cell {
        border-right: 1px solid #ddd;
        border-bottom: 1px solid #ddd;
        padding: 8px 10px;
    }
    .tabulator-col:last-child .tabulator-cell {
        border-right: none;
    }
    /* Alinhamento dos dados das células */
    .tabulator-cell[tabulator-field^="20"] {
        text-align: center;
    }

    .legendMaps > span{font-size: 17px; }
    .legendMaps{
        font-family: Arial, sans-serif;
        background: rgba(255, 255, 255, 0.5);
        padding: 10px;
        margin: 10px;
        border: 1.5px solid #000;
    }

    .sales-info-card {
        width: 25rem;
        margin-top: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        text-align: center;
        border-radius: 0.5rem;
        overflow: hidden;
        border: 1px solid #d1d5db;
    }

    .sales-info-title {
        font-weight: bold;
        padding: 0.5rem;
        font-size: 1.5rem;
        border-bottom: 1px solid black;
    }

    .sales-info-value {
        font-weight: bold;
        padding: 0px;
        font-size: 3.75rem;
    }

    .sales-info-value-perc {
        font-weight: bold;
        padding: 0px;
        font-size: 4.75rem;
        color: black;
    }

    .bg-gray-200 { background-color: #e5e7eb; color: #374151; }
    .bg-white { background-color: #ffffff; color: #374151; }
    .bg-black { background-color: #000000; color: #ffffff; }
    .bg-lime-500 { background-color: #84cc16; color: #ffffff; }
</style>

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-sm-12">
            <div class="panel panel-default form-horizontal">
                <div class="panel-heading">
                    <h3 class="panel-title">Apresentação Mensal de Vendas</h3>
                </div>
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <div class="row">
                                <div id="tabCadastro" class="col-sm-12">
                                    <div class="box-body">
                                        <div class="row p-b-5">
                                            <div class="form-group crud_space">
                                                {{ Form::label('ano', 'Ano:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('ano', $years, null,['id' => 'ano','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('mes', 'Mês:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('mes', $meses, null,['id' => 'mes','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                <div class="col-sm-2">
                                                    <button id="btnFiltro" type="button" class="btn btn-nw-geral btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Metas"><span class="fa fa-search fa-lg"></span></button>
                                                    <button style="display:none;" id="btnUpdate" type="button" class="btn btn-nw-geral btn-sm m-l-1" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Atualizar dados no servidor"><span class="fa fa-refresh fa-lg"></span></button>
                                                    <button style="display:none;" id="btnExport" type="button" class="btn btn-nw-geral btn-sm m-l-1" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Dowload Apresentação"><span class="fa fa-file-powerpoint-o fa-lg"></span></button>
                                                </div>
                                            </div> 
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="box-container" id="divMainContainer" style="display:none;">
                                                    <div class="row" style="display: flex; justify-content: center; margin-bottom:15px;">
                                                        <div class="box-title">
                                                                <h4>Venda Geral</h4>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-8 col-sm-offset-2"  style="display: flex; justify-content: center;">
                                                            <div id="chart_vendageral"></div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-12" style="display:flex; justify-content:center;">
                                                            <div style="width:fit-content;">
                                                            <div id="tbl_vendageral"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="div_setores">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="{{asset('js/lib/collection.js')}}"></script>
<script>
    function setLatLgtEmpresa(){
        longitude = parseFloat("{{Session::get('empresa_padrao')->longitude}}");
        latitude = parseFloat("{{Session::get('empresa_padrao')->latitude}}");
        if(isEmpty(latitude) || isEmpty(longitude))
            bootbox.alert("Não foi possível localizar a latitude e longitude da empresa.");
    }
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key={{$keygooglemaps}}&libraries=marker"></script>
<script src="{{URL::to('js/vendasmensais.js')}}" type="text/javascript"></script>
@endsection