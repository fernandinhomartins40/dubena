
@extends('layouts.mainmenu')

@section('content')

<link href="{{URL::to('plugins/tabulator/css/tabulator_bootstrap3.min.css')}}" rel="stylesheet" type="text/css" />
<script src="{{URL::to('plugins/tabulator/js/tabulator.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('js/tabulatorLocalization.js')}}" type="text/javascript"></script>

<style>
    .alert-circle {
        border-radius: 28px;
        width: 15px;
        height: 15px;
    }
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

  .tabulator .tabulator-row .tabulator-cell:last-child {
    border-right: none;
  }
  .tabulator .tabulator-tableHolder {
    overflow-x: auto;
  }



</style>

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-sm-12">
                <div class="panel panel-default form-horizontal">
                    <div class="panel-heading">
                        <h3 class="panel-title">Acompanhamento de Convênios e Gás de Bolso</h3>
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
                                                <div class="col-sm-6" style="margin-left: 0px; padding-right: 2px; padding-left: 0px;">
                                                  <div class="box-container">
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                      <div class="box-title">
                                                              <h4>Convênios</h4>
                                                      </div>
                                                    </div>
                                                      <div class="row" style="margin-top: 15px;">
                                                          <div class="col-sm-12">
                                                              <div id="checkboxConvenioContainer" class="text-center"></div>
                                                          </div>
                                                      </div>
                                                    
                                                      <div class="row">
                                                          <div class="col-sm-12">
                                                              <div id="chart_convenio12meses"></div>
                                                          </div>
                                                      </div>
                                                      <div class="row">
                                                          <div class="col-sm-12">
                                                              <div id="tbl_convenio12meses"></div>
                                                          </div>
                                                      </div>
                                                      <div class="row">
                                                          <div class="col-sm-12">
                                                              <div id="checkboxConvenioClientesContainer" class="text-center"></div>
                                                          </div>
                                                      </div>
                                                      <div class="row">
                                                          <div class="col-sm-12">
                                                              <div id="chart_convenioClientes12meses"></div>
                                                          </div>
                                                      </div>

                                                      <div class="row">
                                                          <div class="col-sm-12">
                                                              <div id="tbl_convenioClientes12meses"></div>
                                                          </div>
                                                      </div>
                                                       <div class="row" style="display: flex; justify-content: center;">
                                                        <div class="text-center">
                                                              <div class="tableTitle" id="tituloConvenioMes">Quantidade de Conveniados x Vendas (GlpP13) no Mês Atual</div>
                                                              <div class="tableSubTitle">Clique na quantidade da tabela acima para escolher outro produto/mês</div>
                                                        </div>
                                                      </div>
                                                      <div class="row">
                                                          <div class="col-sm-12">
                                                              <div id="tbl_convenioMes"></div>
                                                          </div>
                                                      </div>
                                                  </div>
                                                </div>
                                                <div class="col-sm-6" style="padding-left: 2px; padding-right: 0px;">
                                                  <div class="box-container">
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                      <div class="box-title">
                                                          <h4>Vale Gás</h4>
                                                      </div>
                                                    </div>
                                                      <div class="row" style="margin-top: 15px;">
                                                          <div class="col-sm-12">
                                                              <div id="checkboxValegasContainer" class="text-center"></div>
                                                          </div>
                                                      </div>
                                                    
                                                      <div class="row">
                                                          <div class="col-sm-12">
                                                              <div id="chart_valegas12meses"></div>
                                                          </div>
                                                      </div>
                                                      <div class="row">
                                                          <div class="col-sm-12">
                                                              <div id="tbl_valegas12meses"></div>
                                                          </div>
                                                      </div>
                                                      <div class="row" style="display: flex; justify-content: center;">
                                                        <div class="text-center">
                                                              <div class="tableTitle" id="tituloValegasMes">Vendas de Vale Gás (Glp P13) no Mês Atual</div>
                                                              <div class="tableSubTitle">Clique na quantidade da tabela acima para escolher outro produto/mês</div>
                                                        </div>
                                                      </div>
                                                      <div class="row">
                                                          <div class="col-sm-12">
                                                              <div id="tbl_valegasMes"></div>
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
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="{{URL::to('js/conveniogbGestao.js')}}" type="text/javascript"></script>

@endsection