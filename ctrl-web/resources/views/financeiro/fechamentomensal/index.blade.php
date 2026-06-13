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
    .tabulator .tabulator-header .tabulator-col {
        background-color: #3f51b5;
        color: white;
        font-weight: bold;
        text-transform: uppercase;
        border-right: 1px solid white;
        font-size: 18px;
    }
    /* Estiliza a linha de "DIF (%)" */
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
                    <h3 class="panel-title">Fechamento Mensal Gerencial</h3>
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
                                                    <button id="btnFiltro" type="button" class="btn btn-nw-geral btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Dados"><span class="fa fa-search fa-lg"></span></button>
                                                    <button id="btnEnviarEmail" type="button" class="btn btn-nw-geral btn-sm m-l-1" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Enviar fechamento por e-mail para Diretoria"><span class="fa fa-envelope fa-lg"></span></button>
                                                </div>
                                            </div> 
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="box-container" id="divMainContainerDre" style="display:none;">
                                                    <div class="row" style="display: flex; justify-content: center; margin-bottom:15px;">
                                                        <div class="box-title">
                                                                <h4>DRE Mensal</h4>
                                                        </div>
                                                    </div>
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                        <button id="btnExportDreXls" type="button" class="btn btn-danger btn-sm m-l-1" style="margin-right: 3px;" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Exportar PDF"><span class="fa fa-file-pdf-o fa-lg"></span></button>
                                                        <button id="btnExportDrePdf" type="button" class="btn btn-success btn-sm m-l-1" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Exportar Excel"><span class="fa fa-file-excel-o fa-lg"></span></button>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-12" style="display:flex; justify-content:center;">
                                                            <div style="width:fit-content;">
                                                            <div id="tbl_dre"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="box-container" id="divMainContainerBalanco" style="display:none;">
                                                    <div class="row" style="display: flex; justify-content: center; margin-bottom:15px;">
                                                        <div class="box-title">
                                                                <h4>Balanço Financeiro</h4>
                                                        </div>
                                                    </div>
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                        <button id="btnExportBalancoXls" type="button" class="btn btn-danger btn-sm m-l-1" style="margin-right: 3px;" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Exportar PDF"><span class="fa fa-file-pdf-o fa-lg"></span></button>
                                                        <button id="btnExportBalancoPdf" type="button" class="btn btn-success btn-sm m-l-1" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Exportar Excel"><span class="fa fa-file-excel-o fa-lg"></span></button>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-12" style="display:flex; justify-content:center;">
                                                            <div style="width:fit-content;">
                                                            <div id="tbl_balanco"></div>
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

<script src="{{URL::to('js/fechamentomensalgestao.js')}}" type="text/javascript"></script>


@endsection