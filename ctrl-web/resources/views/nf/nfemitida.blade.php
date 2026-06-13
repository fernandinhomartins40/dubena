@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="col-sm-6" style="margin-bottom:1%">
                                @can('create', App\Nfemitida::class)
                                    <a id="btnNovo" class="btn btn-nw-registro" href="{{ $fullUrl }}">Novo Registro</a>
                                    <button type="button" class="btn btn-nw-geral btnInutilizar">Inutilizar NFs</button>
                                @endcan
                                <button type="button" class="btn btn-nw-geral btnExportarXMLs">Exportar XMLs</button>
                            </div> <!--col-sm-6-->
                        </div> <!--col-sm-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Emissão de Nota Fiscal</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="form-horizontal">
                                <div class="form-group crud_space">
                                    {!! Form::label('empresa_id', 'Empresa:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                    <div class="col-sm-4">
                                        {!! Form::select('empresa_id', $empresasSelect, $empresa_id,['class'=>'form-control input-sm selectDisableSearch']) !!}
                                    </div>
                                    {!! Form::label('dataInicial', 'Data Inicial:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                    <div class="col-sm-2">
                                        <div class="input-group generalDatePicker">
                                            {!! Form::text('dataInicial',$dataInicial,['class'=>'form-control input-sm generalDatePicker', 'id' => 'dataInicial']) !!}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                    {!! Form::label('dataFinal', 'Data Final:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                    <div class="col-sm-2">
                                        <div class="input-group generalDatePicker">
                                            {!! Form::text('dataFinal',$dataFinal,['class'=>'form-control input-sm generalDatePicker', 'id' => 'dataFinal']) !!}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group crud_space">
                                    {!! Form::label('nfmodelo', 'Modelo:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                    <div class="col-sm-2">
                                        <div class="input-group">
                                            {!! Form::select('nfmodelo', $nfmodelos, null,['id' => 'nfmodelo', 'class'=>'form-control input-sm selectDisableSearch']) !!}
                                        </div>
                                    </div>
                                    {!! Form::label('numnf', 'Número:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                    <div class="col-sm-2">
                                        {!! Form::text('numnf', null,['class'=>'form-control input-sm number', 'id' => 'numnf']) !!}
                                    </div>
                                    <div class="col-sm-2 col-sm-offset-1">
                                        <button class="btn btn-sm btn-nw-buscas" id='btnFiltros' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                            <span class="fa fa-search fa-lg"></span>
                                        </button>
                                        <a class="btn btn-sm btn-github" href="{{route('nfemitida.index')}}" type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                            <span class="fa fa-recycle fa-lg"></span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                @include('nf.partials.nf_table')
                                <div id='rotaDel' class="hidden">{{url('nfemitida')}}/</div>
                                <!--Rota para redirecionar via ajax-->
                                <div id='rotaIndex' class="hidden">{{$fullUrl}}</div>
                            </div>
                            <div class="col-sm-12">
                                <label for="filtro" class="col-sm-12 control-label input-sm" style="text-align:right;">Total NF Autorizadas {{$valorTotalAutorizadas}}</label>
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->

                    <div class="row">
                        <div class="col-sm-12">
                            <div class="col-sm-5">
                                @can('create', App\Nfemitida::class)
                                    <a id="btnNovo" class="btn btn-nw-registro" href="{{$fullUrl}}">Novo Registro</a>
                                    <button type="button" class="btn btn-nw-geral btnInutilizar">Inutilizar NFs</button>
                                @endcan
                                <button type="button" class="btn btn-nw-geral btnExportarXMLs">Exportar XMLs</button>
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->

        </div><!-- /.content-wrapper -->
    </div>
    <div class="modal fade" id="myModalInutilizar" tabindex="-1" role="dialog" aria-labelledby="myModalLabelInutilizar" aria-hidden="true">
        <div class="modal-dialog" style="width: 60%">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                    <h4 class="modal-title" id="myModalLabelInutilizar">Inutilizar NF</h4>
                </div>
                {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmInutilizarAjax']) }}
                <div class="modal-body">
                    <div class="box-body">
                        <div class="form-group crud_space col-sm-12">
                            {!! Form::label('nfmodeloinutilizar', 'Modelo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-sm-2">
                                {!! Form::select('nfmodeloinutilizar', $nfmodelos, null, ['class'=>'selectChosen', 'id'=>'nfmodeloinutilizar']) !!}
                            </div>
                            {!! Form::label('empresainutilizar', 'Empresa:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-sm-6">
                                {!! Form::text('empresainutilizar',null,['class'=>'form-control input-sm', 'disabled' => true, 'id'=>'empresainutilizar']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space col-sm-12">
                            {!! Form::label('xJust', 'Justificativa:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-sm-10">
                                {!! Form::text('xJust',null,['class'=>'form-control input-sm', 'id'=>'xJust']) !!}
                            </div>
                        </div>
                        <div class="form-group crud_space col-sm-12">
                            {!! Form::label('nIni', 'Número Inícial:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                            <div class="col-sm-3">
                                {!! Form::text('nIni',null,['class'=>'form-control input-sm number', 'id'=>'nIni']) !!}
                            </div>
                            {!! Form::label('nFin', 'Número Final:', ['class'=>'col-sm-4 control-label input-sm']) !!}
                            <div class="col-sm-3">
                                {!! Form::text('nFin',null,['class'=>'form-control input-sm number', 'id'=>'nFin']) !!}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="btnCloseInutilizar" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-nw-registro" id="btnProcessInutilizar" onclick="confirmarInutilizacao();">Inutilizar</button>
                    <div id="saveError" class="alert alert-danger alert-dismissable" style="display:none;">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <span class="glyphicon glyphicon-remove"></span>
                        <div id="save_result"></div>
                    </div>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</div>
<script src="{{asset('js/nfEmitidaIndex.js')}}" type="text/javascript"></script>
@endsection
