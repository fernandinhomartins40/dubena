@extends('layouts.mainmenu')
@section('content')

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Relatório de Comissões</h3>
                    </div><!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Relatório de Comissão Entregadores</a></li>
                            <li><a href="#tab_2" data-toggle="tab">Relatório de Comissão Tonelagem</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1"><!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'reportvendacomissao','class'=>'form-horizontal'])}}
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
													<button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.comissoes')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
													<button type="button" id='gerarPdfComissao' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
													<button id="btnFiltroComissao" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
												</div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('colaborador_id', 'Colaborador:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('colaborador_id',$colaboradores,null,['id' => 'colaborador_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('resumo', 'Resumo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('resumo', 1, null, ['id'=>'resumo']) }}
                                                </div>
                                            </div>
                                        </div><!-- /.box-body -->
                                    </div>
                                    {{ Form::close() }}
                                </div>
                            </div>
                            <div class="tab-pane" id="tab_2"><!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'reportvendacomissao','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('datainicioton', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datainicioton',null,['id'=>'datainicioton','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datafimton', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datafimton',null,['id'=>'datafimton','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
													<button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.comissoes')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
													<button type="button" id='gerarPdfComissaoTon' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
													<button id="btnFiltroComissaoTon" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
												</div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('colaborador_idton', 'Colaborador:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('colaborador_idton',$colaboradoreston,null,['id' => 'colaborador_idton','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('resumoton', 'Resumo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('resumoton', 1, null, ['id'=>'resumoton']) }}
                                                </div>
                                            </div>
                                        </div><!-- /.box-body -->
                                    </div>
                                    {{ Form::close() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /.content-wrapper -->
        </div>
    </div>
</div>
@include('general.modal_report_iframe')
<script type="text/javascript" src="{{URL::to('js/reportcomissao.js')}}"></script>
<script type="text/javascript">
</script>
@endsection