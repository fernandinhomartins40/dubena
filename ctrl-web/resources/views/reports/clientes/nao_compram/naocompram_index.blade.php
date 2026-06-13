@extends('layouts.mainmenu')
@section('content')

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Clientes Sem Compras</h3>
                    </div><!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Cliente sem Compra</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1"><!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'reportsemcompra','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('empresa_id', 'Empresa:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('empresa_id',$empresas,null,['id' => 'empresa_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('setor_id',[],null,['id' => 'setor_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.semcompras')}}'" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                    <button id="btnFiltroCli" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
                                                    <button id="btnFiltroCliXls" type="button" class="btn btn-success btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Exportar Relatório"><span class="fa fa-file-excel-o fa-lg"></span></button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('tipopessoa_id', 'Tipo Pessoa:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('tipopessoa_id',$tipopessoa,null,['id' => 'tipopessoa_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('segmento_id', 'Segmento:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('segmento_id',$segmento,null,['id' => 'segmento_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('bairro_id', 'Bairro:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('bairro_id',$bairros,null,['id' => 'bairro_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('naocompram', 'Não Compram a(dias):', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-1">
                                                    {{ Form::text('naocompram', null, ['id'=>'naocompram', 'class' => 'form-control input-sm  number']) }}
                                                </div>
                                                {{ Form::label('temcompras', 'Tem compras a(dias):', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-1">
                                                    {{ Form::text('temcompras', null, ['id'=>'temcompras', 'class' => 'form-control input-sm  number']) }}
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
<script type="text/javascript" src="{{URL::to('js/reportclicompram.js')}}"></script>
@endsection