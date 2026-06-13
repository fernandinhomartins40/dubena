@extends('layouts.mainmenu')
@section('content')

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                {{ Form::open(['id' => 'reportestoquetransferencia','class'=>'form-horizontal','url'=>'report.transferenciapdf'])}}
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Transferências de Estoques</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Transferência de Estoque Origem</a></li>
                            <li class=""><a href="#tab_2" data-toggle="tab">Transferência de Estoque Destino</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {{ Form::label('datainicio', 'Data Inicio:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
													<button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.estoquetransferencia')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
													<button type="button" id='gerarPdfTransferencia' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
													<button id="btnFiltroTransferencia" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
												</div>                                                
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('produto_id', 'Produto:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('produto_id',$produtos,null,['id' => 'produto_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('setores_id', 'Setores de Origem:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::select('setores_id',$setores,null,['id' => 'setores_id','class'=>'form-control selectChosen input-sm','multiple','data-placeholder'=>'Selecione...']) }}
                                                    {{ Form::hidden('setores_id_hd',null,['id' => 'setores_id_hd']) }}
                                                </div>
                                            </div>
                                        </div>
                                        <!-- /.box-body -->
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane" id="tab_2">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {{ Form::label('datast', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datast',null,['id'=>'datast','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('dataen', 'Data Fim:', ['class'=>'col-sm-2 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('dataen',null,['id'=>'dataen','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
													<button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.estoquetransferencia')}}?tab=2'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
													<button type="button" id='gerarPdfTransferenciaDestino' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button>
													<button id="btnFiltroTransferenciaDestino" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
												</div>                                                
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('produtoid', 'Produto:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('produtoid',$produtos,null,['id' => 'produtoid','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('setordestino_id', 'Setores de Destino:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::select('setordestino_id',$setores,null,['id' => 'setordestino_id','class'=>'form-control selectChosen input-sm','multiple','data-placeholder'=>'Selecione...']) }}
                                                </div>
                                            </div>
                                            {{ Form::hidden('tab', @$tab,['id'=>'tab']) }}
                                        </div>
                                        <!-- /.box-body -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{ Form::close() }}
                </div>
            <!-- /.content-wrapper -->
        </div>
    </div>
</div>
@include('general.modal_report_iframe')
<script type="text/javascript" src="{{URL::to('js/reportestoque.js')}}"></script>
<script type="text/javascript">
</script>
@endsection