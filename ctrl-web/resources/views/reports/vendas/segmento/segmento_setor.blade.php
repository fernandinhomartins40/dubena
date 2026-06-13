@extends('layouts.mainmenu')
@section('content')

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Vendas por Segmentos</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Vendas por Segmento</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'reportvendassegmento','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.vendasegmento')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                    <button type="button" id='gerarPdfCompram' class="btn btn-nw-registro hidden btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
                                                    <button id="btnFiltroCompram" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('segmento_id', 'Segmento:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('segmento_id',$segmentos,null,['id' => 'segmento_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('setor_id',$setores,null,['id' => 'setor_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                <div class="col-sm-4">
                                                    <div class="col-sm-10">
                                                        {{ Form::label('compram', 'Compram', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                        <div class="col-sm-1 checkbox">
                                                            {{ Form::radio('compram', '1', true,['onclick'=>'naoCompram()']) }}
                                                        </div>
                                                        {!! Form::label('nao_compram', 'Não compram', ['class'=>'col-sm-4 control-label input-sm']) !!}
                                                        <div class="col-sm-1 checkbox">
                                                            {{ Form::radio('compram', '2', false,['onclick'=>'naoCompram()']) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- /.box-body -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>
@include('general.modal_report_iframe')
<script type="text/javascript" src="{{URL::to('js/reportvendapdv.js')}}"></script>
@endsection