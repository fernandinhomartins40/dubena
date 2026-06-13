
@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            @if(isset($checklist))
            {{ Form::model($checklist, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('cadastrochecklist.update', $checklist->id))) }}
            @else 
            {{ Form::open(['id'=>'fmCadastro','route' => 'cadastrochecklist.store', 'class' => 'form-horizontal', 'files' => true]) }} 
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Cadastro Checklist</h3>
                        </div>
                    </div><!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Checklists</a></li>
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
                                                {{ Form::label('checklisttipo_id', 'Tipo Checklist:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::select('checklisttipo_id',$tipochecklist, null, ['id'=>'checklisttipo_id', 'class' => 'form-control  selectChosen']) }}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('datainicio', 'Data início:', ['class'=>'col-sm-2     control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datafim', 'Data fim:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker']) }}
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
                                            <br>
                                            <div class="form-group crud_space" >
                                                <div class="col-sm-6 col-md-push-1">
                                                    <h1 class="panel-title">Tópicos</h1>
                                                </div>
                                                <div class="col-sm-6">
                                                    <h1 class="panel-title">Perguntas</h1>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space" >
                                                <div class="col-md-12 col-md-push-1">
                                                    {{ Form::label('descricaotopicos', 'Descrição:', ['class'=>'col-sm-1 control-label input-sm','style'=>'margin-left:-2.59%']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::text('descricaotopicos',null,['id'=>'descricaotopicos','class'=>'form-control input-sm']) }}
                                                    </div>
                                                    <div class="col-md-1">
                                                        <button class="btn btn-nw-buscas btn-xs" type='button' id="btnAddQuestionTopicos">Adicionar</button>
                                                    </div>
                                                    {{ Form::label('descricaoperguntas', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm','style'=>'margin-left:4%']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::text('descricaoperguntas',null,['id'=>'descricaoperguntas','class'=>'form-control input-sm']) }}
                                                    </div>
                                                    <div class="col-md-1">
                                                        <button class="btn btn-nw-buscas btn-xs" type='button' id='btnAddPerguntas'>Adicionar</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space" >
                                                <div class="col-md-11 col-md-push-1">
                                                    <div class="col-md-5" style='background-color: white'>
                                                        {{ Form::hidden('topicos_hd', null, ['id'=>'topicos_hd']) }}
                                                        {{ Form::hidden('perguntas_hd', @$perguntas, ['id'=>'perguntas_hd']) }}
                                                        {{ Form::hidden('respostas_hd', @$respostas, ['id'=>'respostas_hd']) }}
                                                        {{ Form::hidden('edit', @$edit, ['id'=>'edit']) }}
                                                        {{ Form::hidden('inativar', @$inativar, ['id'=>'inativar']) }}
                                                        {{ Form::hidden('id', @$checklist->id, ['id'=>'id']) }}
                                                        <div class="hidden" id="topicosconteudo"></div>
                                                        <table id="tblTopicos" class="table table-bordered table-striped table-hover table-condensed" style="max-height:200px;max-width:300px">
                                                            <thead>
                                                                <tr>
                                                                    <th>id</th>
                                                                    <th>Tópicos</th>
                                                                    <th>Operações</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="topicos-list" class="tblPedidos" name="topicos-list">
                                                                @if(isset($checklisttopicos))
                                                                @foreach($checklisttopicos as $topico)
                                                                    <tr>
                                                                        <td>{{$topico->id}}</td>
                                                                        <td>{{$topico->descricao}}</td>
                                                                        <td>
                                                                            <button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover' style='max-height:30px;font-size:12px;margin-top:-5px;'>Remover</button>
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                                @endif
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="col-md-5 col-md-push-1" style='background-color:white;max-height:200px;'>
                                                        <div class="hidden" id="perguntasconteudo"></div>
                                                        <table id="tblPerguntas" class="table table-bordered table-striped table-hover table-condensed" style="max-height:200px;max-width:300px;">
                                                            <thead>
                                                                <tr>
                                                                    <th>id</th>
                                                                    <th>idstrange</th>
                                                                    <th>Perguntas</th>
                                                                    <th>Operações</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="perguntas-list" class="tblPedidos" name="perguntas-list">

                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space" >
                                                <div class="col-md-3 col-md-push-1">
                                                    <h3 class="panel-title ">Respostas</h3>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space" >
                                                {{ Form::label('descricaoresposta', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm','style'=>'margin-left:-1.2%']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::text('descricaoresposta',null,['id'=>'descricaoresposta','class'=>'form-control input-sm']) }}
                                                </div>
                                                <div id="checkboxalerta">
                                                    {{ Form::label('alerta', 'Alerta:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-1 checkbox">
                                                        {{Form::checkbox('alerta',1)}}
                                                    </div>
                                                </div>
                                                {{ Form::label('tiporesposta', 'Tipo resposta:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('tiporesposta',$tiporesposta, null, ['id'=>'tiporesposta', 'class' => 'form-control  selectChosen']) }}
                                                </div>
                                                <div class="col-md-1">
                                                    <button class="btn btn-nw-buscas btn-xs" type='button' id='btnAddResposta'>Adicionar</button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space" >
                                                <div class="col-md-11 col-md-push-1 ">
                                                    <div class="col-md-6" style='background-color: white'>
                                                        <table id="tblRespostas" class="table table-bordered table-striped table-hover table-condensed" style="max-height:200px;max-width:300px">
                                                            <thead>
                                                                <tr>
                                                                    <th>id</th>
                                                                    <th>idstrange</th>
                                                                    <th>Resposta</th>
                                                                    <th>Tipo Resposta ID</th>
                                                                    <th>Tipo Resposta</th>
                                                                    <th>Alerta</th>
                                                                    <th>Operações</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="clientes-list" class="tblPedidos" name="clientes-list">

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
                            <a id="habilitaredicao" href="{{ route('cadastrochecklist.index') }}" type="button" class="btn btn-nw-geral" >Voltar</a>
                        </div>
                    </div>
                </div>
            </ul>
            {{Form::close()}}
        </div>
    </div>
</div><!-- /.box-body -->
<script type="text/javascript" src="{{URL::to('js/checklist.js')}}"></script>
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
        @if(isset($inativar))
            inativarRegistro();
        @endif
        showCall();
    @endif
    });
});

</script>
@endsection
