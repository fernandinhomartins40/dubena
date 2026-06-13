@extends('layouts.mainmenu')

@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Clientes por Bairros</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Cliente por Bairro</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'filtroclientesbairros','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space">
                                                {{ Form::label('bairro', 'Bairro:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('bairro',$bairros,null,['id' => 'bairro','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('setor', 'Setores:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('setor',$setores,null,['id' => 'setor','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                {{ Form::label('segmento', 'Segmento:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('segmento',$segmentos,null,['id' => 'segmento','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('tipopessoa', 'Tipo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('tipopessoa',$tipopessoas,null,['id' => 'tipopessoa','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                <div class="col-sm-2 col-sm-offset-1">
                                                    <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('report.clientesbairros')}}'" class="btn btn-sm btn-github"
                                                        data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                    <button id="btnFiltro" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom"
                                                        title="Visualizar Relatório"><span class="fa fa-print fa-lg"></span></button>
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
</div>
@include('general.modal_report_iframe')
<script type="text/javascript" src="{{URL::to('js/reportclientes.js')}}"></script>
@endsection