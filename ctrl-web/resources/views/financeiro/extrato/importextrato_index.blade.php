@extends('layouts.mainmenu')
@section('content')
<style>
    .box-container {
      position: relative;
      border: 1px solid #acabab;
      border-radius: 8px;
      padding: 10px;
      margin-top: 30px;
    }

    .box-titleh {
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

    .negativo {
        color: #ac2424 !important;
        font-weight: bold;
    }
    .positivo {
        color: #275f29;
        font-weight: bold;
    }

     .context-menu {
        position: absolute;
        background-color: #fff;
        border: 1px solid #ccc;
        box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
        z-index: 1000;
        list-style: none;
        padding: 5px 0;
        margin: 0;
    }

    .context-menu li {
        padding: 8px 12px;
        cursor: pointer;
    }

    .context-menu li:hover {
        background-color: #f0f0f0;
    }

    .alert-circle {
        border-radius: 15px;
        width: fit-content;
        padding-right: 10px;
        padding-left: 10px;
    }

    .chkStatus {
        margin-right: 5px !important; top: 2px; position: relative;
    }

    .modal {
        overflow-y: auto !important; /* Or scroll, depending on desired scrollbar visibility */
    }

</style>

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Importação de Extrato Bancário (OFX)</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Importação de Extrato</a></li>
                        </ul>
                        <div class="tab-content">
                            {{Form::open(['url' => @$url, 'id' => 'fmParcelas', 'method' => 'GET', 'class' => 'form-horizontal'])}}
                            <div class="tab-pane active" id="tab_1">
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            <div class="row p-b-5">
                                                <div class="col-sm-2">
                                                    <label class="mousehover-pointer" id="btnUpload">
                                                        <span class="btn btn-sm btn-nw-registro fa fa-upload fa-lg" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Arquivo">
                                                        </span>
                                                        &nbsp;&nbsp;&nbsp;&nbsp;<span>Selecione o arquivo OFX</span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="row p-b-5">
                                                <div class="col-sm-12 text-center" style="margin-top: 10px;">
                                                    <h4 id="tituloConta"></h4>
                                                </div>
                                            </div>
                                            <div class="row showRender" style="display:none;">
                                                <div class="col-sm-12 text-center" style="margin-left: 0px; padding-right: 2px; padding-left: 0px;">
                                                    <div class="box-container">
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                      <div class="box-titleh">
                                                              <h4>legenda</h4>
                                                      </div>
                                                    </div>
                                                    <div class="row" style="margin-top:10px;">
                                                        <div id="divLegenda" class="col-sm-12 text-center" style="display: flex;justify-content: center;">
                                                            
                                                        </div>
                                                    </div>
                                                  </div>
                                                </div>
                                            </div>
                                            <div class="row showRender" style="display:none;">
                                                <div class="col-sm-6" style="margin-left: 0px; padding-right: 2px; padding-left: 0px;">
                                                  <div class="box-container">
                                                    <div class="row" style="display: flex; justify-content: center;">
                                                      <div class="box-titleh">
                                                              <h4>Extrato</h4>
                                                      </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-sm-12" style="display:flex; justify-content: center;">
                                                            <div id="tbl_extrato"></div>
                                                        </div>
                                                    </div>
                                                  </div>
                                                </div>
                                                <div class="col-sm-6" style="padding-left: 2px; padding-right: 0px;">
                                                  <div class="box-container">
                                                        <div class="row" style="display: flex; justify-content: center;">
                                                        <div class="box-titleh">
                                                            <h4>Lançamentos Sistema</h4>
                                                        </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-sm-12" style="display:flex; justify-content: center;">
                                                                <div id="tbl_lancamentos"></div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-sm-12 text-center" style="margin-top: 10px;">
                                                                <button id="btnAtualizarLancamentos" class="btn btn-nw-registro" type="button">Processar Lançamentos</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{Form::close()}}
                        </div>
                    </div>
                </div><!-- /.panel-default -->
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="lancamento_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width:50%">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span
                        class="sr-only">Fechar</span></button>
                <h4 id="h4TituloLancamento" class="modal-title">Lançar Movimento no Caixa</h4>
            </div>
            <div class="modal-body col-md-12">
                <div class="form-horizontal">
                    <div class="col-sm-12">
                        <div class="form-group crud_space">
                            {{ Form::label('descricaolancamento', 'Histórico OFX:', ['class'=>'col-sm-3 control-label input-sm']) }}
                            <div class="col-sm-8">
                                {!! Form::text('descricaolancamento',null,['id'=>'descricaolancamento', 'class'=>'form-control input-sm', 'placeholder'=>'Texto', 'readonly'=>'readonly']) !!}
                                {{Form::hidden('acaolancamento',null, ['id'=>'acaolancamento'])}}
                                {{Form::hidden('datalancamento',null, ['id'=>'datalancamento'])}}
                                {{Form::hidden('valorlancamento',null, ['id'=>'valorlancamento'])}}
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {{ Form::label('uniqueidlancamento', 'Id. OFX:', ['class'=>'col-sm-3 control-label input-sm']) }}
                            <div class="col-sm-8">
                                {!! Form::text('uniqueidlancamento',null,['id'=>'uniqueidlancamento', 'class'=>'form-control input-sm', 'placeholder'=>'ID OFX', 'readonly'=>'readonly']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {{ Form::label('datalancamento_f', 'Data:', ['class'=>'col-sm-3 control-label input-sm']) }}
                            <div class="col-sm-4">
                                {!! Form::text('datalancamento_f',null,['id'=>'datalancamento_f', 'class'=>'form-control input-sm', 'placeholder'=>'Data', 'readonly'=>'readonly']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {{ Form::label('valorlancamento_f', 'Valor:', ['class'=>'col-sm-3 control-label input-sm']) }}
                            <div class="col-sm-4">
                                {!! Form::text('valorlancamento_f',null,['id'=>'valorlancamento_f', 'class'=>'form-control input-sm', 'placeholder'=>'Valor', 'readonly'=>'readonly']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space lancamentolancar">
                            {!! Form::label('clientelancamento_id', 'Cliente/Forn:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                            <div class="col-sm-8">
                                <select id="searchboxClienteLancamento" name="clientelancamento_id" placeholder="Buscar Cliente/Fornecedor" class="form-control" value="" data-selectize-value = '[]'></select>
                            </div>
                        </div>
                        <div class="form-group crud_space lancamentotransferir">
                            {!! Form::label('contalancamento_id', 'Conta Origem/Destino:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                            <div class="col-sm-8">
                                {!! Form::select('contalancamento_id', $contas, null, ['id'=>'contalancamento_id', 'class'=>'form-control input-sm selectChosen']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space lancamentolancar">
                            {!! Form::label('condicaopagamentolancamento_id', 'Condição Pagamento:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                            <div class="col-sm-8">
                                {!! Form::select('condicaopagamentolancamento_id', $condicaopagamentos, null, ['id'=>'condicaopagamentolancamento_id', 'class'=>'form-control input-sm selectChosen']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space lancamentolancar">
                            {!! Form::label('contamovimentotipolancamento_id', 'Tipo Recebimento:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                            <div class="col-sm-8">
                                {!! Form::select('contamovimentotipolancamento_id', $contamovimentotipos, null, ['id'=>'contamovimentotipolancamento_id', 'class'=>'form-control input-sm selectChosen']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space lancamentolancar">
                            {{ Form::label('pclancamento_id', 'P.Contas:', ['class'=>'col-sm-3 control-label input-sm']) }}
                            <div class="col-sm-8">
                                {{Form::hidden('pclancamento_id',null, ['id'=>'pclancamento_id'])}}
                                <div class="input-group">
                                    {{ Form::text('pclancamento_descricao',@$pclancamento_descricao,['id'=>'pclancamento_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPclancamento" onclick="abrirPlanoConta('jstreepc4','pclancamento_id','pclancamento_descricao');">Mudar</button>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group crud_space lancamentolancar">
                            {{ Form::label('cclancamento_id', 'C.Custos:', ['class'=>'col-sm-3 control-label input-sm']) }}
                            <div class="col-sm-8">
                                {{Form::hidden('cclancamento_id',@$cclancamento_id, ['id'=>'cclancamento_id'])}}
                                <div class="input-group">
                                    {{ Form::text('cclancamento_descricao',@$cclancamento_desc,['id'=>'cclancamento_descricao', 'class'=>'form-control input-sm', 'disabled']) }}
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCclancamento" onclick="abrirCentroCusto('jstreecc10','cclancamento_id','cclancamento_descricao');">Mudar</button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btnGravarLancamento" class="btn btn-nw-registro" type="button">Adicionar</button>
                <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="baixar_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span
                        class="sr-only">Fechar</span></button>
                <h4 id="h4TituloBaixa" class="modal-title">Baixar Título</h4>
            </div>
            <div class="modal-body col-md-12">
                <div class="form-horizontal">
                    <div class="col-sm-12">
                        <div class="form-group crud_space">
                            {{ Form::label('descricaobaixa', 'Histórico OFX:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-8">
                                {!! Form::text('descricaobaixa',null,['id'=>'descricaobaixa', 'class'=>'form-control input-sm', 'placeholder'=>'Texto', 'readonly'=>'readonly']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {{ Form::label('uniqueidbaixa', 'Id. OFX:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-3">
                                {!! Form::text('uniqueidbaixa',null,['id'=>'uniqueidbaixa', 'class'=>'form-control input-sm', 'placeholder'=>'ID OFX', 'readonly'=>'readonly']) !!}
                            </div>
                            {{ Form::label('databaixa_f', 'Data OFX:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-3">
                                {!! Form::text('databaixa_f',null,['id'=>'databaixa_f', 'class'=>'form-control input-sm', 'placeholder'=>'Data', 'readonly'=>'readonly']) !!}
                            </div>
                        </div>
                        <hr>
                        <div class="form-group crud_space divAddBaixa">
                            {{ Form::label('clientebaixa_id', "Cliente:", ['id'=>'lblclientebaixa_id', 'class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-8">
                                <select id="searchboxClienteBaixa" name="clientebaixa_id" placeholder="Nome" class="form-control" style="float:left;width:100%;" value="" data-selectize-value = '[]'></select>
                                <input type="hidden" id="pagarreceberbaixa">
                            </div>
                        </div>
                        <div class="form-group crud_space divAddBaixa">
                            {{ Form::label('datainibaixa', 'Vencimento Inicial:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-3">
                                <div class="input-group date generalDatePicker">
                                    {{ Form::text('datainibaixa',null,['id' => 'datainibaixa','class'=>'form-control input-sm generalDatePicker', 'required']) }}
                                    <span class="input-group-addon">
                                        <span class="glyphicon glyphicon-calendar"></span>
                                    </span>
                                </div>
                            </div>
                            {{ Form::label('datafimbaixa', 'Vencimento Final:', ['class'=>'col-sm-2 control-label input-sm', 'required']) }}
                            <div class="col-sm-3">
                                <div class="input-group date generalDatePicker">
                                    {{ Form::text('datafimbaixa',null,['id' => 'datafimbaixa','class'=>'form-control input-sm generalDatePicker']) }}
                                    <span class="input-group-addon">
                                        <span class="glyphicon glyphicon-calendar"></span>
                                    </span>
                                </div>
                            </div>
                            <div class="col-sm-0">
                                <button id="btnBuscarParcelas" class="btn btn-nw-registro" type="button" style="padding: 4px 10px;"><span class="fa fa-search fa-sm"></span></button>
                            </div>
                        </div>
                        <div class="form-group crud_space">
                            {{ Form::label('valorbaixaofx', 'Valor OFX:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-3">
                                {{ Form::text('valorbaixaofx',null,['id' => 'valorbaixaofx','class'=>'form-control input-sm', 'readonly']) }}
                            </div>
                            {{ Form::label('valorbaixasel', 'Valor Selec.:', ['class'=>'col-sm-2 control-label input-sm']) }}
                            <div class="col-sm-3">
                                {{ Form::text('valorbaixasel',null,['id' => 'valorbaixasel','class'=>'form-control input-sm', 'readonly']) }}
                            </div>
                        </div>
                         <div class="form-group crud_space">
                            {!! Form::label('contamovimentotipobaixa_id', 'Tipo Recebimento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-sm-8">
                                {!! Form::select('contamovimentotipobaixa_id', $contamovimentotipos, null, ['id'=>'contamovimentotipobaixa_id', 'class'=>'form-control input-sm selectChosen']) !!}
                            </div>
                        </div>

                    </div>
                    <div class="row">
                        <div class="col-sm-12" style="display:flex; justify-content: center;">
                            <div id="tbl_parcelas"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="divGravarParcelas">
                <button id="btnGravarParcelas" class="btn btn-nw-registro" type="button" style="display:none;">Adicionar</button>
                <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
@include('general.modals.upload_file')
<form action="" id="fmAux" method="post">
    <input type="hidden" name='file-upload' id="file">
</form>

<link href="{{URL::to('plugins/tabulator/css/tabulator_bootstrap3.min.css')}}" rel="stylesheet" type="text/css" />
<script src="{{URL::to('plugins/tabulator/js/tabulator.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('js/tabulatorLocalization.js')}}" type="text/javascript"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/luxon@latest/build/global/luxon.min.js"></script>
<script src="{{asset('js/lib/collection.js')}}"></script>

<script src="{{URL::to('plugins/selectize/js/standalone/selectize.min.js')}}"></script>

@include('financeiro.centrocustos_partial1_js')
@include('financeiro.centrocustos_partial2_js')
@include('financeiro.centrocustos_partial1')
@include('financeiro.planocontas_partial1_js')
@include('financeiro.planocontas_partial2_js')
@include('financeiro.planocontas_partial1')


<script type="text/javascript">
    var extratoconfigLancar = {{$extratoacaolancar->getValue()}};
    var extratoconfigTransferir = {{$extratoacaotransferir->getValue()}};
</script>

<script src="{{URL::to('js/importextrato.js')}}" type="text/javascript"></script>

@endsection
