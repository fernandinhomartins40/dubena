
@extends('layouts.mainmenu')
@section('content')
<style type="text/css">
    .bootstrap-table{margin-top: -25px !important;}
</style>
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="row">
                    <div class="col-md-12" style="margin-bottom:1%">
                        <div class="col-md-6">
                            @can('create', App\Pedido::class)
                                <a href="{{ URL::route('pedido.create') }}" target='_blank' class="btn btn-nw-registro">Novo Registro</a>
                            @endcan
                        </div>
                    </div>
                </div>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Acompanhamento de Pedidos</h3>
                    </div>
                    <div class="panel-body">
                        <div id="tabCadastro" class="col-md-12">
                            <div class="box-body">
                                {{Form::open(['method' => 'get', 'id' => 'fmGetPedidos', 'class' => 'form-horizontal'])}}
                                    <div class="form-group crud_space">
                                        {{ Form::label('datainicial', 'Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            <div class="input-group generalDatePicker">
                                                {{ Form::text('datainicial',null,['class'=>'form-control input-sm generalDatePicker ', 'id' => 'datainicial']) }}
                                                <span class="input-group-addon">
                                                    <i class="glyphicon glyphicon-calendar"></i>
                                                </span>
                                            </div>
                                        </div>
                                        {{ Form::label('datafinal', 'Até:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            <div class="input-group generalDatePicker">
                                                {{ Form::text('datafinal',@$datafinal,['class'=>'form-control input-sm generalDatePicker', 'id' => 'datafinal']) }}
                                                <span class="input-group-addon">
                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                </span>
                                            </div>
                                        </div>
                                        {{ Form::label('empresa_id', 'Empresa:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-3">
                                            {{ Form::select('empresa_id',$empresas,@$empresa_id,['class'=>'form-control input-sm selectChosen', 'id' => 'empresa_id']) }}
                                        </div>
                                        {{ Form::label('atualizacaoAuto', 'Automático:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-1 checkbox-inline">
                                            {{ Form::checkbox('atualizacaoAuto', 0, true, ['id' => 'atualizacaoAuto'])}}
                                        </div>
                                    </div>
                                    <div class="form-group crud_space">
                                        {{ Form::label('status_id', 'Status:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::select('status_id',$status,null,['class'=>'form-control input-sm selectChosen', 'id' => 'status_id']) }}
                                        </div>
                                        {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-2">
                                            {{ Form::select('setor_id',[],null,['class'=>'form-control input-sm selectChosen','id' => 'setor_id']) }}
                                        </div>
                                        {{ Form::label('colaborador_id', 'Colaborador:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                        <div class="col-sm-3">
                                            {{ Form::select('colaborador_id',[],@$colaborador_id,['class'=>'form-control input-sm selectChosen', 'id' => 'colaborador_id']) }}
                                        </div>
                                        <div class="col-sm-2">
                                            <button class="btn btn-sm btn-nw-buscas" id='btnBuscaAcompanhamentos' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                                <span class="fa fa-search fa-lg"></span>
                                            </button>
                                            <button class="btn btn-sm btn-github" id='btnLimpaCamposAcompanhamento' type="reset" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                                <span class="fa fa-recycle fa-lg"></span>
                                            </button>
                                        </div>
                                    </div>
                                {{Form::close()}}
                                <iframe class="margTop_20" sandbox="allow-same-origin allow-scripts allow-popups allow-forms allow-modals"
                                        id="iframeTable" style="border: 0; width:100%; height:480px;">
                                </iframe>
                                <div class="col-sm-2 fright">
                                    <i>Pressione F1 para obter ajuda.</i>
                                </div>
                            </div>
                        </div>
                    </div><!-- /.panel-body -->
                </div><!-- /.row -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="col-md-5">
                            @can('create', App\Pedido::class)
                                <a href="{{ URL::route('pedido.create') }}" target='_blank' class="btn btn-nw-registro">Novo Registro</a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div><!-- /.content-wrapper -->

@include('pedido.partials.modal_editapedido')
@include('pedido.partials.modal_editavariospedidos')
@include('general.modal_senhamestra')
@include('pedido.partials.modal_pedidomotivoatraso')
@include('pedido.partials.modal_validagasbolso')
@include('general.modal_report_iframe')
@include('pedido.partials.modal_tiponf')
@include('pedido.partials.modal_ajudamonitoramento')
{{Form::hidden('setores', $setores, ['id' => 'setores'])}}
{{Form::hidden('colaboradores', $colaboradores, ['id' => 'colaboradores'])}}
<script src="{{asset('plugins/qz-tray/js/dependencies/rsvp-3.1.0.min.js')}}"></script>
<script src="{{asset('plugins/qz-tray/js/dependencies/sha-256.min.js')}}"></script>
<script src="{{asset('plugins/qz-tray/js/qz-tray.js')}}"></script>
<script src="{{asset('js/thermalPrint.js')}}"></script>
<script src="{{asset('js/pedidoNf.js')}}"></script>
<script src="{{asset('js/pedidoGeneral.js')}}"></script>
<script src="{{asset('js/pedidosMonitoramento.js')}}"></script>
@endsection
