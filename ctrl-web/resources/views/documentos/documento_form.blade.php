@extends('layouts.mainmenu')
@section('content')
<style>
    .dropzone .dz-preview .dz-progress {
        display: none !important;
    }
</style>
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            @if(isset($documento))
            {{ Form::model($documento, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('documento.update', $documento->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'documento.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
            <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Documentos</h3>
                    </div>
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                        @if(isset($documento))
                        <li class=""><a href="#tab_2" data-toggle="tab">Versões</a></li>
                        @endif
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastro" class="col-md-12">
                                    <div class="box-body">
                                        <div class="form-group crud_space">
                                            {!! Form::label('documentotipo_id', 'Tipo Documento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::select('documentotipo_id', $documentotipos, null, ['id'=>'documentotipo_id', 'class' => 'selectChosen form-control', 'style'=>'padding:0px;max-height:24px;']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-6">
                                                {!! Form::text('descricao',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('colaborador_id', 'Responsável:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-6">
                                                {!! Form::select('colaborador_id', $colaboradors, null, ['id'=>'colaborador_id', 'class' => 'selectChosen form-control', 'style'=>'padding:0px;max-height:24px;']) !!}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-pane -->
                        <div class="tab-pane" id="tab_2">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastroVersao" class="col-md-10">
                                    <div class="box-body">
                                        <div class="col-md-12  col-md-offset-1">
                                            <button type="button" id="btnAddVersao" class="btn btn-xs btn-nw-buscas" onclick="addVersao();">Adicionar</button>
                                        </div>
                                        <div class="col-md-12  col-md-offset-1">
                                            {{ Form::hidden('versoes',"", ['id'=>'versoes']) }}
                                            <table id="tblVersoes" class="table table-bordered table-hover table-condensed">
                                                <thead>
                                                    <tr>
                                                        <th>Id</th>
                                                        <th>Versão</th>
                                                        <th>Descrição</th>
                                                        <th>Emissão</th>
                                                        <th>Vencimento</th>
                                                        <th>Ativo</th>
                                                        <th>Arquivo</th>
                                                        <th style='width: 12%'>Operação</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="versoes-list" name="versoes-list">
                                                    @if(isset($documento) && isset($documento->versoes))
                                                    @foreach ($documento->versoes as $versao)
                                                    <tr id="doc{{$versao->id}}">
                                                        <td>{{$versao->id}}</td>
                                                        <td>{{$versao->numeroversao}}</td>
                                                        <td>{{$versao->descricao}}</td>
                                                        <td>{{Carbon\Carbon::parse($versao->dataemissao)->format('d/m/Y')}}</td>
                                                        <td>{{Carbon\Carbon::parse($versao->datavencimento)->format('d/m/Y')}}</td>
                                                        <td>{{$versao->ativo == 1 ? 'Sim' : 'Não'}}</td>
                                                        <td>{!!$versao->nomearquivo!!}</td>
                                                        <td><button type='button' onclick="editarVersao({{$versao->id}})" class='btnEditarVersao btn btn-nw-geral btn-xs' id='btnEditarVersao'><span class="fa fa-pencil-square-o fa-lg" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar Versão"></span></button>
                                                            <button type='button' onclick="downloadVersao({{$versao->id}})" class='btn btn-nw-geral btn-xs' id='btnDownloadVersao'><span class="fa fa-download fa-lg" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Download do Arquivo"></span></button>
                                                            <button type='button' onclick="removerVersao({{$versao->id}}, {{$versao->numeroversao}})" class='btn btn-nw-registro btn-xs' id='btnRemoverVersao' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Remover Versão"><span class="fa fa-trash fa-lg"></span></button>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div><!-- /.box -->
                                    </div>
                                </div>
                            </div>
                        </div><!-- /.tab-pane -->
                    </div><!-- /.tab-content -->
                            <div class="box-footer">
                                <div class="col-md-4">
                                    {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                    <a type="button" href="{{url('documento')}}" class="btn btn-nw-geral">Voltar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {!! Form::close() !!}
            </ul><!-- /.col -->
        </div>
    </div>
    <div class="modal fade" id="versao_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog modal-lg" role="document" style="width:50%">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span><span
                            class="sr-only">Fechar</span></button>
                    <h4 class="modal-title">Versão</h4>
                </div>
                <div class="modal-body col-md-12">
                    <div class="form-horizontal">
                        <div class="col-sm-12">
                            <div class="form-group crud_space">
                                {{ Form::label('numeroversao', 'Número da Versão:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                <div class="col-sm-5">
                                    {!! Form::number('numeroversao',null,['id'=>'numeroversao', 'class'=>'form-control input-sm number', 'placeholder'=>'Nº Versão', 'step'=>'1']) !!}
                                    <input type="hidden" id="versao_id">
                                </div>
                            </div>
                            <div class="form-group crud_space">
                                {{ Form::label('descricaoversao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                <div class="col-sm-8">
                                    {!! Form::text('descricaoversao',null,['id'=>'descricaoversao', 'class'=>'form-control input-sm', 'placeholder'=>'Descrição']) !!}
                                </div>
                            </div>
                            <div class="form-group crud_space">
                                {{ Form::label('emissaoversao', 'Emissão:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                <div class="col-sm-5">
                                    <div class="input-group generalDatePicker">
                                        {!! Form::text('emissaoversao',null,['id'=>'emissaoversao', 'class'=>'form-control input-sm generalDatePicker', 'placeholder'=>'Emissão']) !!}
                                        <span class="input-group-addon">
                                            <span class="glyphicon glyphicon-calendar"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group crud_space">
                                {{ Form::label('vencimentoversao', 'Vencimento:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                <div class="col-sm-5">
                                    <div class="input-group generalDatePicker">
                                        {!! Form::text('vencimentoversao',null,['id'=>'vencimentoversao', 'class'=>'form-control input-sm generalDatePicker', 'placeholder'=>'Vencimento']) !!}
                                        <span class="input-group-addon">
                                            <span class="glyphicon glyphicon-calendar"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group crud_space col-sm-12">
                                <label for="ativoversao" class="col-sm-2 control-label input-sm required">Ativo:</label>
                                <div class="col-sm-9 checkbox">
                                    
                                    {{ Form::checkbox('ativoversao', 1, null, ['id'=>'ativoversao', 'checked']) }}
                                </div>
                            </div>
                            <div class="form-group crud_space col-sm-12" id="divDesativarVersao" style="margin-bottom: 20px;">
                                <label for="desativarversao" class="col-sm-2 control-label input-sm required">Desativar versões existentes:</label>
                                <div class="col-sm-9 checkbox">
                                    {{ Form::checkbox('desativarversao', 1, null, ['id'=>'desativarversao', 'checked']) }}
                                </div>
                            </div>
                            <div class="form-group crud_space col-sm-12">
                                <label for="fmVersao" class="col-sm-2 control-label input-sm required">Arquivo:</label>
                                <div class="col-sm-5">
                                    <form action="/seu-endpoint" id="fmVersao" class="dropzone">
                                        <div id="divVersaoUpload" class="dz-message needsclick">
                                            Solte seu arquivo aqui ou clique para selecionar.
                                        </div>
                                    </form>
                                </div>
                            </div>
                        
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="btnGravarVersao" class="btn btn-nw-registro" type="button">Gravar</button>
                    <button type="button" class="btn btn-nw-geral" data-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
<script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" type="text/css" />

<script type="text/javascript">
    Dropzone.autoDiscover = false; // Desabilita a autodescoberta do Dropzone
    var root = '{{url("/")}}';
    var tblVersoes;
    var operacaoVersao;
    var documento_id = {{isset($documento)?$documento->id:'-1'}};
    var urlLanguage = "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}";
    var show = {{isset($show)?'true':'false'}};
</script>
<script src="{{URL::to('js/documento.js')}}" type="text/javascript"></script>

@endsection
