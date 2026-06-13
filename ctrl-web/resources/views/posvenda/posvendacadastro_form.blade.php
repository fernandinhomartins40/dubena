
@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($posvenda))
            {{ Form::model($posvenda, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('posvendacadastro.update', $posvenda->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro','route' => 'posvendacadastro.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Cadastro de Pós-Venda</h3>
                        </div>
                    </div><!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Pós-Venda</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-11">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {{ Form::label('descricao', 'Descriçao:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::text('descricao',null,['id'=>'descricao','class'=>'form-control input-sm']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('datahorainicio', 'Data início:', ['class'=>'col-sm-2     control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datahorainicio',null,['id'=>'datahorainicio','class'=>'form-control input-sm generalDateTimePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datahorafim', 'Data fim:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datahorafim',null,['id'=>'datahorafim','class'=>'form-control input-sm generalDateTimePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('ativo', 'Ativo:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2 checkbox">
                                                    {{Form::checkbox('ativo')}}
                                                </div>
                                            </div>
                                            <br>
                                            <div class="form-group crud_space" >
                                                <div class="col-sm-6 col-md-push-1">
                                                    <h1 class="panel-title">Perguntas</h1>
                                                </div>
                                                <div class="col-sm-6">
                                                    <h1 class="panel-title">Respostas</h1>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space" >
                                                <div class="col-md-12 col-md-push-1">
                                                    {{ Form::label('descricaoperguntas', 'Descrição:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::text('descricaoperguntas',null,['id'=>'descricaoperguntas','class'=>'form-control input-sm']) }}
                                                        {{ Form::hidden('edit',@$edit,['id'=>'edit']) }}
                                                        {{ Form::hidden('inativar',@$inativar,['id'=>'inativar']) }}
                                                    </div>
                                                    <div class="col-md-1">
                                                        <button class="btn btn-nw-buscas btn-xs" type='button' id="btnAddPergunta">Adicionar</button>
                                                    </div>
                                                    {{ Form::label('descricaoresposta', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::text('descricaoresposta',null,['id'=>'descricaoresposta','class'=>'form-control input-sm']) }}
                                                    </div>
                                                    <div class="col-md-1">
                                                        <button class="btn btn-nw-buscas btn-xs" type='button' id='btnAddResposta'>Adicionar</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space" >
                                                <div class="col-md-11 col-md-push-1">
                                                    <div class="col-md-5" style='background-color: white'>
                                                        <div class="hidden" id="perguntasconteudo"></div>
                                                        {{ Form::hidden('perguntas_hd',@$perguntas,['id'=>'perguntas_hd']) }}
                                                        {{ Form::hidden('respostas_hd',@$respostas,['id'=>'respostas_hd']) }}
                                                        <table id="tblPerguntas" class="table table-bordered table-striped table-hover table-condensed" style="max-height:320px;max-width:300px">
                                                            <thead>
                                                                <tr>
                                                                    <th>id</th>
                                                                    <th>Perguntas</th>
                                                                    <th>Operações</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="perguntas-list" name="perguntas-list">
                                                                @if(isset($perguntas))
                                                                @foreach($perguntas as $pergunta)
                                                                    <tr>
                                                                        <td>{{$pergunta->id}}</td>
                                                                        <td>{{$pergunta->descricao}}</td>
                                                                        <td>
                                                                            <button type='button' class='btn btn-danger btn-xs' id='btnRemover'>Remover</button>
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                                @endif
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="col-md-5 col-md-push-1" style='background-color: white'>
                                                        <table id="tblRespostas" class="table table-bordered table-striped table-hover table-condensed" style="max-height:320px;max-width:300px;">
                                                            <thead>
                                                                <tr>
                                                                    <th>id</th>
                                                                    <th>idstrange</th>
                                                                    <th>Respostas</th>
                                                                    <th>Operações</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="respostas-list" name="respostas-list">

                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="col-md-4">
                            {!! Form::submit('Gravar', ['id'=>'btngravar','class' => 'btn btn-nw-registro']) !!}
                            <a id="goback" href="{{ route('posvendacadastro.index') }}" type="button" class="btn btn-nw-geral" >Voltar</a>
                        </div>
                    </div>
                </div>
            </ul>
            {{Form::close()}}
        </div>
    </div>
</div><!-- /.box-body -->
<script type="text/javascript" src="{{URL::to('js/posvendacadastro.js')}}"></script>
<script type="text/javascript">
@if($errors->any())
    errorsany = true;
@else
    errorsany = false;
@endif
$(document).ready(function(){
    setTimeout(function () {
    @if(isset($show) || isset($edit))
        if($("#edit").val() == ""){
            desativarInputs();
            $("#btnAddPergunta").prop('disabled',true);
            $("#btnAddResposta").prop('disabled',true);
        }
        var inativar = $("#inativar").val();
        if(inativar != ""){
            inativarCadastro();
        }
        callShow();
    @endif
    });
});

</script>
@endsection
