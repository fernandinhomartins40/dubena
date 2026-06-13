
@extends('layouts.mainmenu')
@section('content')
<style>
    input[type=checkbox] {
        height: 15px;
    }
    .modal.modal-wide .modal-dialog {
        width: 90%;
    }
    .modal-wide .modal-body {
        overflow-y: auto;
    }
    .td-right{
        text-align: right;
    }
    body.modal-open-noscroll
    {
        margin-right: 0!important;
        overflow: hidden;
    }
    .modal-open-noscroll .navbar-default, .modal-open .navbar-default
    {
        margin-right: 0!important;
    }
        .info-box { margin-bottom: 5px; }
</style>
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <ul>
                <div class="nav-tabs-custom">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">
                                Contas a {{$tipo_lancamento=='P'?'Pagar':'Receber'}}
                            </h3>
                        </div>
                    </div>
                    @if(!isset($parcelas))
                        <div class=" col-md-10 col-md-offset-1">
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    <div class="col-md-12" style="margin-top:10px;">
                                        <div class="form-group crud_space col-sm-12">
                                            {!! Form::label('cliente_id', $tipo_lancamento=="P"?"Fornecedor:":"Cliente:", ['class'=>'col-sm-2 control-label input-sm text-right']) !!}
                                            <div class="col-sm-8">
                                                <select id="searchboxcliente" name="cliente_id" placeholder="Buscar {{$tipo_lancamento=='P'?'Fornecedor':'Cliente'}}" class="form-control" style="float:left;width:100%;" value="" data-selectize-value = '[]'></select>
                                            </div>
                                        </div>
                                        @if($tipo_lancamento=='R')
                                        <div class="form-group crud_space col-sm-12">
                                            {!! Form::label('colaborador_id', 'Colaborador:', ['class'=>'col-sm-2 control-label input-sm  text-right']) !!}
                                            <div class="col-sm-8">
                                                <select id="searchboxcolaborador" name="colaborador_id" placeholder="Colaborador" class="form-control" style="float:left;width:100%;" value="" data-selectize-value = '[]'></select>
                                            </div>
                                        </div>
                                        @endif
                                        <div class="form-group crud_space col-sm-12">
                                            {!! Form::label('tipodata', 'Tipo de Data:', ['class'=>'col-sm-2 control-label input-sm   text-right']) !!}
                                            <div class="col-sm-2">
                                                {{ Form::radio('tipodata', 1, true) }} Vencimento<br>
                                            </div>
                                            <div class="col-sm-2">
                                                {{ Form::radio('tipodata', 2, false) }} Emissão<br>
                                            </div>
                                            <div class="col-sm-2">
                                                {{ Form::radio('tipodata', 3, false) }} Pagamento<br>
                                            </div>
                                            <div class="col-sm-2">
                                                {{ Form::radio('tipodata', 4, false) }} Tudo
                                            </div>
                                        </div>
                                        <div class="form-group crud_space col-sm-12">
                                            <div class='col-sm-2 control-label input-sm'></div>
                                            <div class="input-group date control-label col-sm-3 generalDatePicker" style="padding-left: 15px;float:left;">
                                                {!! Form::text('datainicio',null,['class'=>'form-control','id'=>'datainicio']) !!}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                            <div class="input-group date control-label col-sm-3 generalDatePicker" style="padding-left: 15px;float:left;">
                                                {!! Form::text('datafinal',null,['class'=>'form-control','id'=>'datafinal']) !!}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="form-group crud_space col-sm-12">
                                            {!! Form::label('status_id', 'Status:', ['class'=>'col-sm-2 control-label input-sm   text-right']) !!}
                                            <div class="col-sm-2">
                                                {{ Form::radio('status_id', 1, true) }} Não Baixadas
                                            </div>
                                            <div class="col-sm-2">
                                                {{ Form::radio('status_id', 2, false) }} Baixadas
                                            </div>
                                            <div class="col-sm-2">
                                                {{ Form::radio('status_id', 4, false) }} Canceladas
                                            </div>
                                            <div class="col-sm-2">
                                                {{ Form::radio('status_id', 3, false) }} Todas
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.box-body -->
                            </div>
                        </div><!-- /.box -->
                        <div class="row">
                            <div class="col-md-8 col-md-offset-2">
                                <div class="col-md-7">
                                    <button type="button" id="btnPrint" class="btn btn-info" onclick="carregarLancamentosFiltro();">Visualizar</button>
                                    <button type="button" id="btnLimpar" class="btn btn-warning" onclick="limparPesquisa();">Limpar Campos</button>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-success">Pesquisar</button>
                                        <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                                            <span class="caret"></span>
                                            &nbsp;
                                        </button>
                                        <ul class="dropdown-menu" role="menu">
                                            <li><a onclick="carregarLancamentosPesquisa(1);" href="#">Cód. Lançamento</a></li>
                                            @if($tipo_lancamento=='R')
                                            <li><a onclick="carregarLancamentosPesquisa(3);" href="#">Nº Nota Fiscal</a></li>
                                            <li><a onclick="carregarLancamentosPesquisa(2);" href="#">Cód. Pedido</a></li>
                                            <li><a onclick="carregarLancamentosPesquisa(4);" href="#">Cód. Fechamento Convênio</a></li>
                                            @else
                                            <li><a onclick="carregarLancamentosPesquisa(5);" href="#">Nº Nota Fiscal</a></li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <br/>
                    @endif


                    <div id="divLancamentos" class="box" style="display:none;">
                        <div class="col-md-12" style="margin-top:10px;margin-bottom:10px;">
                            <div class="col-md-12 center text-center">
                                @if($tipo_lancamento == 'P')
                                    <!-- pode ou não pode pagar -->
                                    @can('pagar', App\Financeiro::class)
                                        <button type="button" class="btn btn-success" onclick="abrirTelaReceber();">Pagar</button>
                                    @endcan
                                    @cannot('pagar', App\Financeiro::class)
                                        <button disabled type="button" class="btn btn-success">Pagar</button>
                                    @endcannot

                                    @if(!isset($parcelas))
                                        <!-- pode ou não pode estornar recebimento ou pagamento -->
                                        @can('estornarPagamento', App\Financeiro::class)
                                            <button type="button" class="btn btn-warning" onclick="abrirTelaEstornar();">Estornar Pagamento</button>
                                        @endcan
                                        @cannot('estornarPagamento', App\Financeiro::class)
                                            <button disabled type="button" class="btn btn-warning">Estornar Pagamento</button>
                                        @endcannot
                                        <!-- pode ou não pode agrupar -->
                                        @can('agruparPagamento', App\Financeiro::class)
                                            <button type="button" class="btn btn-primary" onclick="abrirFinanceiroJuntar('P');">Agrupar/Reparcelar</button>
                                        @endcan
                                        @cannot('agruparPagamento', App\Financeiro::class)
                                            <button disabled type="button" class="btn btn-primary">Agrupar/Reparcelar</button>
                                        @endcannot

                                        <!-- pode ou não pode cancelar -->
                                        @can('cancelarPagamento', App\Financeiro::class)
                                            <button type="button" class="btn btn-danger" onclick="abrirTelaCancelar();">Cancelar Títulos</button>
                                        @endcan
                                        @cannot('cancelarPagamento', App\Financeiro::class)
                                            <button disabled type="button" class="btn btn-danger" onclick="abrirTelaCancelar();">Cancelar Títulos</button>
                                        @endcannot

                                        <!-- pode ou não pode alterar descrição -->
                                        @can('alterarPagamento', App\Financeiro::class)
                                            <button type="button" class="btn btn-warning" onclick="abrirTelaAlterarDescricao();">Alterar Parcela{{$tipo_lancamento == "R" ? "/Cartão" : ""}}</button>
                                        @endcan
                                        @cannot('alterarPagamento', App\Financeiro::class)
                                            <button disabled type="button" class="btn btn-warning">Alterar Parcela{{$tipo_lancamento == "R" ? "/Cartão" : ""}}</button>
                                        @endcannot
                                    @endif
                                    @can('pagar', App\Financeiro::class)
                                        <button type="button" class="btn btn-success" onclick="abrirTelaReceberFechado();">Pagar retroativo</button>
                                    @endcan
                                    @cannot('pagar', App\Financeiro::class)
                                        <button disabled type="button" class="btn btn-success">Pagar retroativo</button>
                                    @endcannot

                                    @if(!isset($parcelas))
                                        @can('create', App\Chequeemitido::class)
                                            <button type="button" class="btn btn-success" onclick="abrirTelaCheque();">Cheque</button>
                                        @endcan
                                        @cannot('create', App\Chequeemitido::class)
                                            <button disabled type="button" class="btn btn-success">Cheque</button>
                                        @endcannot

                                        @can('encontroPagamento', App\Financeiro::class)
                                            <button type="button" class="btn btn-info" onclick="abrirTelaEncontroContas();">Encontro de Contas</button>
                                        @endcan
                                        @cannot('encontroPagamento', App\Financeiro::class)
                                            <button disabled type="button" class="btn btn-info">Encontro de Contas</button>
                                        @endcannot
                                    @endif
                                @else
                                    <!-- pode ou não pode pagar -->
                                    @can('receber', App\Financeiro::class)
                                        <button type="button" class="btn btn-success" onclick="abrirTelaReceber();">Receber</button>
                                    @endcan
                                    @cannot('receber', App\Financeiro::class)
                                        <button disabled type="button" class="btn btn-success">Receber</button>
                                    @endcannot

                                    @if(!isset($parcelas))
                                        <!-- pode ou não pode estornar -->
                                        @can('estornarRecebimento', App\Financeiro::class)
                                            <button type="button" class="btn btn-warning" onclick="abrirTelaEstornar();">Estornar Recebimento</button>
                                        @endcan
                                        @cannot('estornarRecebimento', App\Financeiro::class)
                                            <button disabled type="button" class="btn btn-warning">Estornar Recebimento</button>
                                        @endcannot

                                        <!-- pode ou não pode agrupar -->
                                        @can('agruparReceber', App\Financeiro::class)
                                            <button type="button" class="btn btn-primary" onclick="abrirFinanceiroJuntar('R');">Agrupar/Reparcelar</button>
                                        @endcan
                                        @cannot('agruparReceber', App\Financeiro::class)
                                            <button disabled type="button" class="btn btn-primary">Agrupar/Reparcelar</button>
                                        @endcannot

                                        <!-- pode ou não pode cancelar -->
                                        @can('cancelarRecebimento', App\Financeiro::class)
                                            <button type="button" class="btn btn-danger" onclick="abrirTelaCancelar();">Cancelar Títulos</button>
                                        @endcan
                                        @cannot('cancelarRecebimento', App\Financeiro::class)
                                            <button disabled type="button" class="btn btn-danger" onclick="abrirTelaCancelar();">Cancelar Títulos</button>
                                        @endcannot

                                        <!-- pode ou não pode alterar descrição -->
                                        @can('alterarRecebimento', App\Financeiro::class)
                                            <button type="button" class="btn btn-warning" onclick="abrirTelaAlterarDescricao();">Alterar Parcela{{$tipo_lancamento == "R" ? "/Cartão" : ""}}</button>
                                        @endcan
                                        @cannot('alterarRecebimento', App\Financeiro::class)
                                            <button disabled type="button" class="btn btn-warning">Alterar Parcela{{$tipo_lancamento == "R" ? "/Cartão" : ""}}</button>
                                        @endcannot
                                        @can('create', App\Chequerecebido::class)
                                            <button type="button" class="btn btn-success" onclick="abrirTelaCheque();">Cheque</button>
                                        @endcan
                                        @cannot('create', App\Chequerecebido::class)
                                            <button disabled type="button" class="btn btn-success">Cheque</button>
                                        @endcannot

                                        @can('encontroRecbimento', App\Financeiro::class)
                                            <button type="button" class="btn btn-info" onclick="abrirTelaEncontroContas();">Encontro de Contas</button>
                                        @endcan
                                        @cannot('encontroRecbimento', App\Financeiro::class)
                                            <button disabled type="button" class="btn btn-info">Encontro de Contas</button>
                                        @endcannot
                                    @endif
                                    @can('receber', App\Financeiro::class)
                                        <button type="button" class="btn btn-success" onclick="abrirTelaReceberFechado();">Receber retroativo</button>
                                    @endcan
                                    @cannot('receber', App\Financeiro::class)
                                        <button disabled type="button" class="btn btn-success">Receber retroativo</button>
                                    @endcannot
                                @endif
                            </div>
                        </div>

                        {{ Form::open(['id'=>'fmCadastro', 'route' => 'caixa.index', 'class' => 'form-horizontal', 'files' => true]) }}
                        <div class="box-body" >
                            {!! Form::hidden('inputCartoes',null,['id'=>'inputCartoes']) !!}
                            {!! Form::hidden('inputData',null,['id'=>'inputData']) !!}
                            <input type="hidden" id="metodo" name="_method">
                            <div id="tabPresenca" class="col-md-10 col-md-offset-1">
                                <table class="table" id="tblLancamentos">
                                    <thead>
                                        <tr>
                                            <th field-id="checkbox" data-none="true"><input type="checkbox" id="checkbox-all"></th>
                                            <th field-id="classTr" hidden="true">classTr</th>
                                            <th field-id="id" sort-by="true"><nobr>Cód.</nobr></th>
                                            <th field-id="documento" sort-by="true">Docto</th>
                                            <th field-id="numero" sort-by="true">Nº Parc.</th>
                                            <th field-id="dataemissao" data-type="date" sort-by="true">Emissão</th>
                                            <th field-id="datavencimento" data-type="date" sort-by="true">Vencto</th>
                                            <th field-id="datahorabaixa" data-type="date" sort-by="true">Pagto</th>
                                            <th field-id="valor" data-type="money" sort-by="true">Valor</th>
                                            <th field-id="multa" data-type="money" sort-by="true">Multa</th>
                                            <th field-id="juros" data-type="money" sort-by="true">Juros</th>
                                            <th field-id="desconto" data-type="money" sort-by="true">Desconto</th>
                                            <th field-id="valorefetivado" data-type="money" sort-by="true">Líquido</th>
                                            <th field-id="nome" sort-by="true">Nome</th>
                                            <th field-id="descricao" sort-by="true">Descrição</th>
                                            <th field-id="numcheque" sort-by="true">Nº Cheque</th>
                                            @if($tipo_lancamento == "R")
                                                <th field-id="nossonumeroboleto" sort-by="true">Nosso nº boleto</th>
                                            @endif
                                            <th field-id="status" sort-by="true">Sit</th>
                                            <th field-id="cliente_id" sort-by="true">Cli</th>
                                            <th field-id="agrupamento_status" sort-by="true">Agr</th>
                                            @if($tipo_lancamento == "R")
                                                <th field-id="cartaoautorizacao" sort-by="true">Autorização</th>
                                            @endif
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div><!-- /.box -->
                        {!! Form::close() !!}
                    </div>
                    <div id="divTotal" class="box" style="display:none;">
                        <div class="box-body" >
                            <div class="col-md-12 center text-center">
                                <div class="form-group crud_space">
                                    {!! Form::label('total_receber', ($tipo_lancamento=='R'?'Total a Receber:':'Total a Pagar'), ['class'=>'col-sm-3 control-label input-sm text-right']) !!}
                                    <div class="col-sm-2">
                                        {!! Form::text('total_receber',null,['class'=>'form-control input-sm dinheiro', 'id'=>'total_receber', 'readonly']) !!}
                                    </div>
                                    {!! Form::label('total_recebido', ($tipo_lancamento=='R'?'Total Recebido:':'Total Pago'), ['class'=>'col-sm-2 control-label input-sm text-right']) !!}
                                    <div class="col-sm-2">
                                        {!! Form::text('total_recebido',null,['class'=>'form-control input-sm dinheiro', 'id'=>'total_recebido', 'readonly']) !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="divLegenda" class="box" style="display:none;">
                        <div class="box-body">
                            <div class="col-md-12 center text-center">
                                <div class="col-md-2 col-md-offset-1" id="divLegendaPendentes">
                                    <div class="col-md-10 col-md-offset-2">
                                        <span class="info-box-icon bg-blue" style="margin-left:15px;width:15px;height:15px;"></span>
                                        <span class="info-box-text fontSize_11">Pendentes</span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="col-md-10 col-md-offset-2">
                                        <span class="info-box-icon" style="margin-left:15px;width:15px;height:15px; background-color: red;"></span>
                                        <span class="info-box-text fontSize_11">Atrasadas</span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="col-md-10 col-md-offset-2">
                                        <span class="info-box-icon" style="margin-left:15px;width:15px;height:15px;background-color: green"></span>
                                        <span class="info-box-text fontSize_11">Baixadas</span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="col-md-10 col-md-offset-2">
                                        <span class="info-box-icon" style="margin-left:15px;width:15px;height:15px;background-color: #808080"></span>
                                        <span class="info-box-text fontSize_11">Canceladas</span>
                                    </div>
                                </div>
                                 <div class="col-md-2" id="divLegendaAgr">
                                    <div class="col-md-10 col-md-offset-2">
                                        <span class="info-box-icon" style="margin-left:15px;width:15px;height:15px;background-color: #D6B400"></span>
                                        <span class="info-box-text fontSize_11">Agrupadas</span>
                                    </div>
                                </div>
                                <div class="col-md-offset-3 col-md-6">
                                    <div class="info-box-content" style="margin-left:0px; margin-top: 10px">
                                        <span class="info-box-text"> Para consultar o título, clique na coluna "Cód."</span>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </ul>
        </div><!-- /.row -->

    </div>
    <meta name="csrf-token" content="{{ csrf_token() }}" />

<script src="{{URL::to('plugins/handsontable/dist/handsontable.full.js')}}"></script>

<script src="{{asset('js/lib/great-table.js')}}"></script>
<link href="{{URL::to('css/lib/great-table.css')}}" rel="stylesheet" type="text/css"/>

@include('financeiro.partials.contaspagar_receber_partial_js')
<!-- page script -->

</div>
<div style="display:none;">
    <form method="get" target="iframeReceberCaixa" id="fmAbrirRecebimento">
        <input type="submit" value="Do Stuff!" />
        <input type="text" id="conta_id_receber" name="conta_id_receber" value="-1"/>
        <input type="text" id="parcelas" name="parcelas" />
        <input type="text" id="encontrocontas" name="encontrocontas" />
        <input type="text" id="baixarfechado" name="baixarfechado" />
        <input type="text" id="qdeDuplicatas" name="qdeDuplicatas" />
    </form>
</div>
<div id="popup_recebercaixa" class="modal fade popupModal modal-wide" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document" id="fundo_popup">
        <div class="modal-content">
            <div id="popup_int" style="text-align:center;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-right: 20px;"><span aria-hidden="true">&times;</span></button>
                <iframe sandbox="allow-modals allow-forms allow-popups allow-scripts allow-same-origin" id="iframeReceberCaixa" name="iframeReceberCaixa" style="border: 0; width:100%; height:500px;margin-top:-20px;"></iframe>
            </div>
        </div>
    </div>
</div>
<div style="display:none;">
    <!-- <form method="post" target="iframeFinanceiro" id="fmAbrirFinanceiro"> -->
    {{ Form::open(['id'=>'fmAbrirFinanceiro', 'name'=>'fmAbrirFinanceiro', 'target' => 'iframeFinanceiro', 'class' => 'form-horizontal']) }}

    <input type="submit" value="Do Stuff!" />
    <input type="text" id="tipo_lancamento" name="tipo_lancamento" />
    <input type="text" id="conta_id" name="conta_id" />
    <input type="text" id="cliente_id" name="cliente_id" />
    <input type="text" id="nome" name="nome" />
    <input type="text" id="tipo_lancamento" name="tipo_lancamento" value="{{$tipo_lancamento}}"/>
    <input type="text" id="parcelas_financeiro" name="parcelas_financeiro" />
    <!-- </form> -->
    {!! Form::close() !!}
</div>
<div id="popup_financeiro" class="modal fade popupModal modal-wide" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document" id="fundo_popup">
        <div class="modal-content">
            <div id="popup_int" style="text-align:center;">
                <button type="button" id="btnCloseFinanceiro" class="close" data-dismiss="modal" aria-label="Close" style="margin-right: 20px;"><span aria-hidden="true">&times;</span></button>
                <iframe sandbox="allow-same-origin allow-scripts allow-popups allow-forms" id="iframeFinanceiro" name="iframeFinanceiro" style="border: 0; width:100%; height:500px;margin-top:-20px;"></iframe>
            </div>
        </div>
    </div>
</div>
<div id="popup_encontrocontas" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 60%;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCadastro">Encontro de Contas</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space">
                        <div class="col-sm-4">
                            <strong>Adicionar Parcelas:</strong>
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-11 margTop_15">
                        <div class="col-sm-12 col-sm-offset-1">
                            <table id="tblParcelasDisponiveis" class="table table-bordered table-hover table-condensed fontSize_14" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>Cód. Parcela</th>
                                        <th>Emissão</th>
                                        <th>Vencimento</th>
                                        <th>Valor</th>
                                        <th>Adicionar</th>
                                    </tr>
                                </thead>
                                <tbody>

                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-11 margTop_15">
                        <div class="col-sm-12 col-sm-offset-1">
                            <table id="tblParcelasAdicionadas" class="table table-bordered table-hover table-condensed fontSize_14" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>Cód. Parcela</th>
                                        <th>Emissão</th>
                                        <th>Vencimento</th>
                                        <th>Valor</th>
                                        <th>Remover</th>
                                    </tr>
                                </thead>
                                <tbody>

                                </tbody>
                            </table>
                        </div>
                    </div>
                    <form class="form-horizontal">
                        <div class="col-sm-12">
                            <div class="form-group crud_space">
                                @if($tipo_lancamento == "R")
                                {{Form::label('valorParcelasSelecionadas', 'A Receber: ', ['class' => 'input-sm control-label col-sm-2'])}}
                                @else
                                {{Form::label('valorParcelasSelecionadas', 'A Pagar: ', ['class' => 'input-sm control-label col-sm-2'])}}
                                @endif
                                <div class="col-sm-2">
                                    {{Form::text('valorParcelasSelecionadas', 'R$ 0,00', ['class' => 'input-sm form-control', 'id' => 'valorParcelasSelecionadas', 'readonly'])}}
                                </div>
                                @if($tipo_lancamento == "R")
                                {{Form::label('valorParcelasAdicionadas', 'A Pagar: ', ['class' => 'input-sm control-label col-sm-2'])}}
                                @else
                                {{Form::label('valorParcelasAdicionadas', 'A Receber: ', ['class' => 'input-sm control-label col-sm-2'])}}
                                @endif
                                <div class="col-sm-2">
                                    {{Form::text('valorParcelasAdicionadas', 'R$ 0,00', ['class' => 'input-sm form-control', 'id' => 'valorParcelasAdicionadas', 'readonly'])}}
                                </div>
                                {{Form::label('valorLiquidoEncontroContas', 'Restante: ', ['class' => 'input-sm control-label col-sm-2'])}}
                                <div class="col-sm-2">
                                    {{Form::text('valorLiquidoEncontroContas', 'R$ 0,00', ['class' => 'input-sm form-control', 'id' => 'valorLiquidoEncontroContas', 'readonly'])}}
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseModalEncontroContas" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                @can('receber', App\Financeiro::class)
                    <button type="button" id="btnReceberEncontroContas" disabled="disabled" class="btn btn-success">Receber</button>
                @endcan
                @cannot('receber', App\Financeiro::class)
                    <button type="button" disabled="disabled" class="btn btn-success">Receber</button>
                @endcannot

                @can('create', App\Chequerecebido::class)
                    <button type="button" id="btnReceberChequeEncontroContas" disabled="disabled" class="btn btn-primary">Cheque</button>
                @endcan
                @cannot('create', App\Chequerecebido::class)
                    <button type="button" disabled="disabled" class="btn btn-primary">Cheque</button>
                @endcannot
            </div>
        </div>
    </div>
</div>


<div id="popup_alterarparcelas" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 100%;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabelCadastro">Alterar Parcela</h4>
            </div>
            <div class="modal-body">
                <div class="box-body">
                    <form class="form-vertical">
                        <div class="col-sm-12">
                            <div class="form-group crud_space">
                                {{Form::label('descricao_parcela', 'Descrição: ', ['class' => 'input-sm control-label col-sm-4'])}}
                                <div class="col-sm-6" id="divDescricaoParcela">
                                    {{Form::text('descricao_parcela', '', ['class' => 'input-sm form-control', 'id' => 'descricao_parcela'])}}
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12">
                                <div class="form-group crud_space">
                                {{Form::label('documento_parcela', 'Documento: ', ['class' => 'input-sm control-label col-sm-4'])}}
                                <div class="col-sm-6" id="divDocumentoParcela">
                                    {{Form::text('documento_parcela', '', ['class' => 'input-sm form-control', 'id' => 'documento_parcela'])}}
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12">
                            <div class="form-group crud_space">
                                {{Form::label('vencimento_parcela', 'Vencimento: ', ['class' => 'input-sm control-label col-sm-4'])}}
                                <div class="input-group date control-label col-sm-5 generalDatePicker" style="padding-left: 15px;float:left;">
                                    {!! Form::text('vencimento_parcela',null,['class'=>'form-control','id'=>'vencimento_parcela']) !!}
                                    <span class="input-group-addon">
                                        <span class="glyphicon glyphicon-calendar"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        @if($tipo_lancamento == 'R')
                        <div id="divInfoCartao">
                            <div class="col-sm-12">
                                <div class="form-group crud_space">
                                        {{Form::label('cartaonsu', 'Nº Documento Cartão: ', ['class' => 'input-sm control-label col-sm-4'])}}
                                        <div class="col-sm-6">
                                            {{Form::text('cartaonsu', '', ['class' => 'input-sm form-control cartaoCredito', 'id' => 'cartaonsu'])}}
                                        </div>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                <div class="form-group crud_space">
                                    {{Form::label('cartaoautorizacao', 'Autorização: ', ['class' => 'input-sm control-label col-sm-4'])}}
                                    <div class="col-sm-6">
                                        {{Form::text('cartaoautorizacao', '', ['class' => 'input-sm form-control number', 'id' => 'cartaoautorizacao'])}}
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btnSalvarAlteraParc" class="btn btn-nw-registro">Salvar</button>
            </div>
        </div>
    </div>
</div>

@include('general.modal_senhamestra')

@endsection
