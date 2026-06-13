@extends('layouts.mainmenu')

@section('content')

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
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
    .cell-clicavel {
        color: #0000ee !important;
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

    .tableTitleResumo {
      font-size: 20px;
      font-weight: bold;
      font-family: 'Segoe UI', sans-serif;
      margin-top: 0px;
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

    #tbl_dre1 {
        border: 1px solid #ccc;
    }
    .tbl_vendasetor {
        border: 1px solid #ccc;
    }
    .tabulator-col-title {
        text-align: center;
    }

    .tabulator-row.header-row {
        font-weight: bold;
        font-size: 13px !important;
        background-color: #C0C0C0;
    }

    .tabulator-row.header-table-row {
        font-weight: bold;
        font-size: 14px !important;
        background-color: #FFFFFF;
    }

    .tabulator-cell {
        border-right: 1px solid #ddd;
        border-bottom: 1px solid #ddd;
        padding: 2px 4px;
        font-size: larger;
    }

    .tabulator-cell.align-right {
        text-align: right;
        justify-content: end;
    }

    .tabulator-col:last-child .tabulator-cell {
        border-right: none;
    }

    .tabulator-calcs-bottom .tabulator-cell {
        border-right: none;
        padding-top: 3px;
        padding-bottom: 0px;
    }
        .custom-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5); /* Semi-transparent background */
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1000; /* Ensure it's above other content */
    }

    .custom-modal-content {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    /* max-width: 500px; */
    width: 50%; /* Responsive width */
    }

    .hidden {
    display: none; /* Initially hide the modal */
    }


</style>


<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-sm-12">
            <div class="panel panel-default form-horizontal">
                <div class="panel-heading">
                    <h3 class="panel-title">Dashboard Gerencial</h3>
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
                                            <div class="col-sm-2">
                                                <button type="button" id="btnRefresh" class="btn btn-nw-registro">
                                                    <div class="d-flex flex-row align-center justify-center" style="gap: 4px;">
                                                        <span>
                                                            <i class="fa fa-refresh" aria-hidden="true"></i>
                                                        </span>
                                                        <span>
                                                            Atualizar
                                                        </span>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="row dashboard" style="display:none;">
                                            <div class="col-sm-8" style="margin-left: 0px; padding-right: 2px; padding-left: 0px;">
                                                <div class="box-container">
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                        <div class="box-title">
                                                            <h4>Venda Diária</h4>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-12">
                                                            <div id="chart_vendadiaria" style="margin-top: 15px;"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-4" style="padding-left: 2px; padding-right: 0px;">

                                                <div class="box-container">
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                        <div class="box-title">
                                                            <h4>Venda Total Mês (Qtde P13)</h4>
                                                        </div>
                                                    </div>
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                        <div class="text-center">
                                                            <div class="tableTitle" id="chart_resumovenda_title"></div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-12">
                                                            <div id="chart_resumovenda" style="display: flex; justify-content: center;"></div>
                                                        </div>
                                                    </div>
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                        <div class="text-center">
                                                            <div class="tableTitleResumo" id="tituloQuantidadeResumo"></div>
                                                            <div class="tableTitleResumo" id="tituloValorResumo"></div>
                                                            <div class="tableSubTitle" id="tituloPeriodoResumo"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row dashboard" style="display:none;">
                                            <div class="col-sm-7" style="margin-left: 0px; padding-right: 2px; padding-left: 0px;">
                                                <div id="divDre" class="box-container">
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                        <div class="box-title">
                                                                <h4>Despesas do Mês</h4>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-10" style="display:flex; justify-content:center;">
                                                            <div id="tbl_dre"></div>
                                                        </div>
                                                        <div class="col-sm-2">
                                                                <div class="form-group crud_space">
                                                                    <div class="col-sm-12">
                                                                        {{ Form::select('ano', $years, null,['id' => 'ano','class'=>'form-control selectChosen input-sm']) }}
                                                                    </div>
                                                                </div>
                                                                <div class="form-group crud_space">
                                                                    <div class="col-sm-12">
                                                                        {{ Form::select('mes', $meses, null,['id' => 'mes','class'=>'form-control selectChosen input-sm']) }}
                                                                    </div>
                                                                </div>
                                                                <div class="form-group crud_space">
                                                                    <div class="col-sm-12 text-right">
                                                                        <button id="btnFiltroDre" type="button" class="btn btn-nw-geral btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Dados"><span class="fa fa-search fa-lg"></span></button>
                                                                    </div>
                                                                </div> 
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-5" style="padding-left: 2px; padding-right: 0px;">
                                                <div id="divBalanco" class="box-container">
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                        <div class="box-title">
                                                                <h4>Saldos Disponíveis</h4>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-12" style="display:flex; justify-content:center;">
                                                            <div id="tbl_balanco"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row dashboard" style="display:none;">
                                            <div class="col-sm-7" style="margin-left: 0px; padding-right: 2px; padding-left: 0px;">
                                                <div id="divEstoque" class="box-container">
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                        <div class="box-title">
                                                                <h4>Estoques</h4>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-12" style="display:flex; justify-content:center;">
                                                            <div id="tbl_saldos"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-5" style="padding-left: 2px; padding-right: 0px;">
                                                <div id="divMarketShare" class="box-container">
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                        <div class="box-title">
                                                                <h4>Market Share</h4>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-12">
                                                            <div id="chart_marketshare" style="margin-top: 15px;"></div>
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
            </div>
       </div>
    </div>
</div>
<div id="modal_detalhes" class="custom-modal-overlay hidden">
  <div id="modalContent" class="custom-modal-content">
    <div class="row">
        <div class="col-sm-12" style="display:flex; justify-content:center;">
            <div style="width:fit-content;">
            <div id="tbl_detalhes"></div>
            </div>
        </div>
    </div>
    <hr>
    <div class="row text-center">
        <button class="btn btn-primary" id="closeModalBtnDetalhes">Fechar</button>
    </div>
  </div>
</div>
<div id="modal_centrocustos" class="custom-modal-overlay hidden" style="z-index: 1001;">
  <div id="modalContent" class="custom-modal-content">
    <div class="row">
        <div class="col-sm-12" style="display:flex; justify-content:center;">
            <div style="width:fit-content;">
            <div id="tbl_centrocustos"></div>
            </div>
        </div>
    </div>
    <hr>
    <div class="row text-center">
        <button class="btn btn-primary" id="closeModalBtnCentroCustos">Fechar</button>
    </div>
  </div>
</div>
<div id="modal_loader" class="custom-modal-overlay hidden" style="z-index: 1002;">
  <div id="modalContent" class="custom-modal-content" style="max-width: 300px; padding: 10px;">
    <h5 id="loader_title" style="margin: 0px;">Aguarde</h5>
    <hr style="margin-top: 10px; margin-bottom: 20px;">
    <div class="row">
        <div class="col-sm-12" style="display:flex; justify-content:center;">
            <div style="width:fit-content;">
                <p><i class="fa fa-spin fa-spinner"></i> <span id="loader_message"></span></p>
            </div>
        </div>
    </div>
  </div>
</div>
<div id="modal_alert" class="custom-modal-overlay hidden" style="z-index: 1003;">
  <div id="modalContent" class="custom-modal-content" style="padding: 10px;">
    <h4 id="alert_title" style="margin: 0px;"></h4>
    <hr style="margin-top: 10px; margin-bottom: 20px;">
    <div class="row">
        <div class="col-sm-12" style="display:flex; justify-content:center;">
            <div style="width:fit-content;">
                <p id="alert_message"></p>
            </div>
        </div>
    </div>
    <hr>
    <div class="row text-center">
        <button class="btn btn-primary" id="closeModalBtnAlert">Ok</button>
    </div>
  </div>
</div>

<script src="{{URL::to('js/dashboardgerencial.js')}}" type="text/javascript"></script>

<script>
    var mes = {!! $mes !!};
    var ano = {!! $ano !!};
</script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

@endsection