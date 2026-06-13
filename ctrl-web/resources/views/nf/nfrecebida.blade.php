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
                                @can('create', App\Nfrecebida::class)
                                    <a id="btnNovo" class="btn btn-nw-registro" href="{{$fullUrl}}">Novo Registro</a>
                                @endcan
                                @can('delete', Auth::user(), App\Nfrecebida::class)
                                    <button id="btnInutilizar" type="button" class="btn btn-nw-geral btnInutilizar">Inutilizar Documentos</button>
                                @endcan
                            </div> <!--col-sm-6-->
                        </div> <!--col-sm-12-->
                    </div><!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Lançamento de Documentos</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="form-horizontal">
                                <div class="form-group crud_space">
                                    {!! Form::label('dataInicial', 'Data Início:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-2">
                                        <div class="input-group generalDatePicker">
                                            {!! Form::text('dataInicial',$dataInicial,['class'=>'form-control input-sm generalDatePicker', 'id' => 'dataInicial']) !!}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                    {!! Form::label('dataFinal', 'Data Fim:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-2">
                                        <div class="input-group generalDatePicker">
                                            {!! Form::text('dataFinal',$dataFinal,['class'=>'form-control input-sm generalDatePicker', 'id' => 'dataFinal']) !!}
                                            <span class="input-group-addon">
                                                <span class="glyphicon glyphicon-calendar"></span>
                                            </span>
                                        </div>
                                    </div>
                                    {{ Form::label('tipolancamento', 'Emissão:', ['class'=>'col-sm-1 control-label input-sm', 'autofocus' => 'true']) }}
                                    <div class="col-sm-2">
                                        {{ Form::select('tipolancamento', ["9" => "Ambos", "0" => "Própria", "1" => "Terceiros"], null, ['class'=>'form-control input-sm selectDisableSearch']) }}
                                    </div>
                                </div>
                                <div class="form-group crud_space">
                                    {!! Form::hidden('cliente_id',$cliente_id,['id' => 'cliente_id']) !!}
                                    {!! Form::hidden('clientenome',$clientenome,['id' => 'clientenome']) !!}

                                    {{Form::hidden('cliente_id_erro',null, ['id'=>'cliente_id_erro'])}}
                                    {{Form::hidden('cliente_nome_erro',null, ['id'=>'cliente_nome_erro'])}}
                                    {!! Form::label('cliente_id', 'Cliente/Fornecedor:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                    <div class="col-sm-6">
                                        <select id="nomecliente" name="nomecliente" placeholder="Buscar Cliente/Fornecedor"  class="form-control input-sm" value="" data-selectize-value = '[]'></select>
                                    </div>
                                    <div class="col-sm-3 col-sm-offset-1">
                                        <button class="btn btn-sm btn-nw-buscas" id='btnFiltros' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                            <span class="fa fa-search fa-lg"></span>
                                        </button>
                                        <a class="btn btn-sm btn-github" href="{{route('nfrecebida.index')}}" type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar">
                                            <span class="fa fa-recycle fa-lg"></span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12">
                                @include('nf.partials.nf_table')
                                <div id='rotaDel' class="hidden">{{url('nfrecebida')}}/</div>
                                <div id='rotaIndex' class="hidden">{{$fullUrl}}</div>
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->

                    <div class="row">
                        <div class="col-sm-12">
                            <div class="col-sm-5">
                                @can('create', App\Nfrecebida::class)
                                    <a id="btnNovo" class="btn btn-nw-registro" href="{{ $fullUrl }}">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div><!-- /.col -->
            </div><!-- /.row -->


        </div><!-- /.content-wrapper -->
    </div>
</div>
<div id='rotaDel' class="hidden">{{url('nfrecebida')}}/</div>

<div class="modal fade" id="modalDel" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabel">Remover Registro</h4>
            </div>
            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCadastroDel']) }}
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('id_del', 'Código:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-9">
                            {!! Form::text('id_del',null,['class'=>'form-control input-sm', 'id'=>'id_del', 'readonly','tabindex'=>'-1']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('nfnumero_del', 'Núm NF:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                        <div class="col-sm-9">
                            {!! Form::text('nfnumero_del',null,['class'=>'form-control input-sm', 'id'=>'nfnumero_del', 'readonly','tabindex'=>'-1']) !!}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnCloseCadastroDel" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                {!! Form::submit('Remover', ['class' => 'btn btn-nw-registro']) !!}
                <div id="saveErrorDel" class="alert alert-danger alert-dismissable" style="display:none;">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <span class="glyphicon glyphicon-remove"></span>
                    <div id="save_result"></div>
                </div>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
<div class="modal fade" id="modalInutilizar" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="min-width: 80%">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabel">Inutilizar Documentos</h4>
            </div>
            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmInut']) }}
            <div class="modal-body">
                <div class="box-body">
                    <div class="form-group crud_space col-sm-12">
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('nfmodelo', 'Modelo:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-1">
                            {!! Form::text('nfmodelo', null, ['class'=>'form-control input-sm', "maxlength" => 2, 'id'=>'nfmodelo']) !!}
                        </div>
                        {!! Form::label('nini', 'Núm Inicial:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('nini',null,['class'=>'form-control input-sm', 'id'=>'nini']) !!}
                        </div>
                        {!! Form::label('nfim', 'Núm Final:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                        <div class="col-sm-2">
                            {!! Form::text('nfim',null,['class'=>'form-control input-sm', 'id'=>'nfim']) !!}
                        </div>
                        {!! Form::label('nfserie', 'Série:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-1">
                            {!! Form::text('nfserie', null, ['class'=>'form-control input-sm', "maxlength" => 20, 'id'=>'nfserie']) !!}
                        </div>
                    </div>
                    <div class="form-group crud_space col-sm-12">
                        {!! Form::label('xjust', 'Motivo:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                        <div class="col-sm-11">
                            {!! Form::text('xjust', null, ['class'=>'form-control input-sm', "maxlength" => 249, 'id'=>'xjust']) !!}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>
                {!! Form::submit('Inutilizar', ['class' => 'btn btn-nw-registro']) !!}
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>

<script type="text/javascript" src="{{asset('js/nfRecebidaIndex.js')}}"></script>
@include('general.modal_report_iframe')
@endsection
