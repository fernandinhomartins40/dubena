@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Relatório de Logs</h3>
                    </div>
                    <!-- /.box-header -->
                    {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Relatório de Log</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="row">
                                        <div id="tabCadastro" class="col-sm-12">
                                            <div class="box-body">
                                                <div class="form-group crud_space">
                                                    {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        <div class="input-group generalDatePicker">
                                                            {{Form::text('datainicio', null, ['id' => 'datainicio', 'class' => 'input-sm form-control generalDatePicker'])}}
                                                            <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                            </span>
                                                        </div>
                                                    </div>  
                                                    {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        <div class="input-group generalDatePicker">
                                                            {{Form::text('datafim', null, (['id' => 'datafim', 'class' => 'input-sm form-control generalDatePicker']))}}
                                                            <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    {{ Form::label('tela_id', 'Tela:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{Form::select('tela_id', $telas, null, ['id'=>'tela_id','class' =>'selectChosen'])}}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('empresa_id', 'Empresa:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{Form::select('empresa_id', $empresas, null, ['id'=>'empresa_id','class' =>'selectChosen'])}}
                                                    </div>
                                                    {{ Form::label('user_id', 'Usuário:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{Form::select('user_id', [], null, ['id'=>'user_id','class' =>'selectChosen'])}}
                                                    </div>
                                                    <div class="col-sm-2 col-sm-push-1">
                                                        <button type="button" onclick="window.location.href = '{{route('logs.reports')}}'" id='btnLimpar' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                        <button id="btnIframe" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /.content-wrapper -->
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>
</div>
@include('general.modal_report_iframe')
<script type="text/javascript" src="{{asset('js/logs.js')}}"></script>
@endsection