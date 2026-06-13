@extends('layouts.mainmenu')
@section('content')
<script src='{{url('js/setor.js')}}'></script>
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($setor))
            {{ Form::model($setor, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('setor.update', $setor->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'setor.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Setor</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    {!! Form::text('descricao', null, ['id'=>'descricao', 'class' => 'form-control input-sm']) !!}
                                                </div>
                                                <div class="col-sm-1">
                                                    {!! Form::label('estoqueproprio', 'Estoque Próprio:', ['class'=>'col-sm-12 col-sm-pull-1 control-label input-sm']) !!}
                                                </div>
                                                <div class="col-sm-1">
                                                    {!! Form::radio('estoqueproprio', 1, true) !!} Sim <br />
                                                    {!! Form::radio('estoqueproprio', 0) !!} Não
                                                </div>
                                                {!! Form::label('estoqueproprio', 'Ativo:', ['class'=>'col-sm-1 control-label input-sm', 'style' => 'margin-top: -5px;']) !!}
                                                <div class="col-sm-1">

                                                    {{ Form::checkbox('ativo', 1, null, ['id'=>'ativo']) }}
                                                </div>
                                            </div>
                                            <div style="margin-top: 10px;">
                                                @include('general.endereco_form_partial')
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('pedidooperacao_id', 'Operação do Pedido:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                <div class="col-md-5">
                                                    {{ Form::select('pedidooperacao_id',$nfoperacao,null,['id' => 'pedidooperacao_id','class'=>'form-control selectChosen input-sm']) }}
                                                </div>
                                                 {{ Form::label('qtderesidencias', 'Qtde Habitantes:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                <div class="col-md-2">
                                                    {{ Form::number('qtderesidencias',null,['id' => 'qtderesidencias','class'=>'form-control input-sm', 'step'=>1]) }}
                                                </div>
                                            </div>
                                        
                                            <div class="form-group crud_space">
                                                {!! Form::label('colaboradores', 'Colaboradores:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-5">
                                                    {!! Form::select('colaboradores', $colaboradores, null, ['id'=>'colaboradores', 'class' => 'form-control input-sm selectDisableSearch']) !!}
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="button" id="addColaborador" class='btn btn-nw-buscas btn-xs'>Adicionar</button>
                                                </div>
                                                {!! Form::label('usarastreamento', 'Usa Rastreamento:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-1 checkbox">
                                                    {{ Form::checkbox('usarastreamento') }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-10 col-sm-push-2">
                                            <hr class='thin'>
                                            <table id="tblListaColaboradores" class="table table-bordered table-hover table-condensed  bg-success">
                                                <thead>
                                                    <tr>
                                                        <th style="width:75px">C&oacute;digo</th>
                                                        <th style="width:300px">Colaborador</th>
                                                        <th style="width:50px;">Operação</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbodyColaboradoresList" name="tbodyColaboradoresList">
                                                    @if (isset($colaboradoresList))
                                                    @foreach ($colaboradoresList as $colaborador)
                                                    <tr id="tr{!! $colaborador->colaborador_id !!}">
                                                        <td>{{$colaborador->colaborador_id}}</td>
                                                        <td>{{$colaborador->colaborador->nome}}</td>
                                                        <td>
                                                            <button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover{!! $colaborador->colaborador_id !!}'>Remover</button>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                    @endif
                                                </tbody>
                                                {!! Form::text('inputColaboradoresList', $inputColaboradoresList, ['id'=>'inputColaboradoresList', 'class' => 'form-control input-sm hidden']) !!}
                                                {!! Form::text('inputColaboradoresListId', $inputColaboradoresListId, ['id'=>'inputColaboradoresListId', 'class' => 'form-control input-sm hidden']) !!}
                                            </table>
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-content -->
                        <div class="box-footer">
                            <div class="col-md-4">
                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                <a type="button" href="{{url('setor')}}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
            </ul><!-- /.col -->
        </div>
        {!! Form::close() !!}

    </div>
</div>
@include('general.popupbairrocidade_form_partial')
<script>
    setTimeout(function () {
        @if (isset($show))
            desativarInputs();
            var ids = [".btnBuscarEndereco", '#btnBuscarCEP',
                    '.novoCadEndereco', '#addColaborador', '.btn'];
            desativarInputsEspecificos(ids);
        @endif
        @if ($errors -> any())
            // carregarTelefonesErro();
        @endif
    }, $(document).ready());

@if(isset($setor))
    setInputsEnderecoPadrao('#cep', '#cidade_id', '#uf', '#bairro_id', '#rua_id');
    let cidade_id = {{$setor->cidade_id}};
    let bairro_id = {{$setor->bairro_id}};
    let rua_id = {{$setor->rua_id}};
    carregarEndereco(cidade_id, bairro_id, rua_id, 'geral');
@endif
</script>
@endsection
