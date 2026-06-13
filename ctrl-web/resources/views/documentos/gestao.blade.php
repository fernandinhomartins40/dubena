
@extends('layouts.mainmenu')

@section('content')

<link href="{{URL::to('plugins/tabulator/css/tabulator_bootstrap3.min.css')}}" rel="stylesheet" type="text/css" />
<script src="{{URL::to('plugins/tabulator/js/tabulator.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('js/tabulatorLocalization.js')}}" type="text/javascript"></script>

<style>
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
    .tabulator-row.tabulator-tree-level-0 {
        background-color: #e0f2f7; /* Light blue background */
        font-weight: bold;
    }

    /* Style for second-level child rows */
    .tabulator-row.tabulator-tree-level-1 {
        background-color: #f0f8ff !important; /* Alice blue background */
        font-style: italic;
    }

    .alert-circle {
        border-radius: 28px;
        width: 15px;
        height: 15px;
    }

</style>

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-sm-12">
            <div class="panel panel-default form-horizontal">
                <div class="panel-heading">
                    <h3 class="panel-title">Controle de Documentos</h3>
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
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="box-container" id="divMainContainer">
                                                    <div class="row" style="display: flex; justify-content: center; margin-bottom:15px;">
                                                        <div class="box-title">
                                                                <h4>Documentos A Vencer nos Próximos 60 dias</h4>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-12" style="display:flex; justify-content:center;">
                                                            <div style="width:fit-content;">
                                                            <div id="tbl_vencer60"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="box-container" id="divTreeContainer">
                                                    <div class="row" style="display: flex; justify-content: center; margin-bottom:15px;">
                                                        <div class="box-title">
                                                                <h4>Árvore de Documentos</h4>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-12" style="display:flex; justify-content:center;">
                                                            <div style="width:fit-content;">
                                                            <div id="tbl_documentos"></div>
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
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/luxon@latest/build/global/luxon.min.js"></script>
<script src="{{asset('js/lib/collection.js')}}"></script>
<script>
    var tblDoc60 = undefined;
</script>

<script src="{{URL::to('js/documentoGestao.js')}}" type="text/javascript"></script>

@endsection