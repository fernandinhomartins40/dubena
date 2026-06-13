@extends('layouts.mainmenu')
@section('content')

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Lançamentos Retroativos</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Lançamento Retroativo</a></li>
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
                                                {{ Form::label('empresa_id', 'Empresa:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('empresa_id',$empresas,null,['id' => 'empresa_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('user_id', 'Usuário:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('user_id',[],null,['id' => 'user_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('conta_id', 'Conta:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-5">
                                                    {{ Form::select('conta_id',[],null,['id' => 'conta_id','class'=>'form-control selectChosen input-sm', 'multiple', 'data-placeholder' => "Selecione" ]) }}
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.retroativo')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                    <button id="btnFiltrar" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                                                    <!--<button type="button" id='gerarPdfCompram' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>-->
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
<script type="text/javascript" src="{{URL::to('js/retroativo.js')}}"></script>
@endsection