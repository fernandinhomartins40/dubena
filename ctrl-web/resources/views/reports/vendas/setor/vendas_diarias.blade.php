@extends('layouts.mainmenu')
@section('content')

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Vendas Diárias</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Vendas Diária</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'filtrovendatodossetor','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('datainicio', 'Data:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('produto_id', 'Produto(s):', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::select('produto_id',$produtos,null,['id' => 'produto_id','class'=>'form-control selectChosen input-sm','multiple','data-placeholder'=>'Selecione...']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('setor_id',$setor,null,['id' => 'setor_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                <div class="col-sm-2 col-sm-offset-1">
													<button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.vendadiaria')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
													<button type="button" id='gerarPdfDiaria' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
													<button id="btnFiltroDiaria" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
												</div>
                                            </div>
                                        </div>
                                        <!-- /.box-body -->
                                    </div>
                                    {{ Form::close() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <!-- /.content-wrapper -->
        </div>
    </div>
</div>
@include('general.modal_report_iframe')
<script type="text/javascript" src="{{URL::to('js/reportvenda.js')}}"></script>
<script type="text/javascript">
</script>
@endsection