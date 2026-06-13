@extends('layouts.mainmenu') 
@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            @if(isset($checklist))
                {{ Form::model($checklist, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('checklist.update', $checklist->id))) }}
            @else 
                {{ Form::open(['id'=>'fmCadastro','route' => 'checklist.store', 'class' => 'form-horizontal', 'files' => true]) }} 
            @endif
            <ul>
                <div class="nav-tabs-custom">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Pesquisa de Checklist</h3>
                        </div>
                    </div>
                    <!-- /.box-header -->
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Checklists</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastro" class="col-md-12">
                                    <div class="box-body">
                                        <div class="form-group crud_space">
                                            <div class="col-sm-6 col-sm-push-2" style="text-align:center">
                                                <p style="font-size:16px;font-weight:600;">Formulário: {{@$descricao}}, Empresa: {{@$empresanome}}</p>
                                                <p style="font-size:14px;font-weight:600;">CNPJ: {{@$empresacnpj}} - Telefone: {{@$empresatelefone}}</p>
                                            </div>
                                        </div>
                                        {{ Form::hidden('idchecklist', @$id, ['id'=>'idchecklist']) }}
                                        @if(isset($checklisttopicos))
                                        @foreach($checklisttopicos as $topico)                                            
                                            <div class="form-group crud_space">
                                                <div class="col-sm-10 col-sm-push-1">
                                                    <h1 class="panel-title" style="font-size:20px;font-style:bold;text-align:left;">{{$topico->descricao}}</h1>
                                                </div>
                                            </div>
                                            @foreach($topico->perguntas as $pergunta)
                                                    <div class="form-group crud_space">
                                                        <label for="{{$pergunta->id}}" class="col-sm-5 col-sm-push-2 control-label input-sm" style="text-align:left;">{{$pergunta->descricao}}</label>
                                                    </div>
                                                    <div class="form-group crud_space">
                                                    @foreach($pergunta->respostas as $resposta)
                                                        @if ($loop->first)
                                                            <div class="col-sm-12 col-sm-push-2">
                                                        @endif
                                                        @if($resposta->tipopergunta == "0")
                                                            <div class="form-group crud_space">
                                                                <div class="col-sm-10 checkbox">
                                                                    <input id="{{$resposta->id}}" type="checkbox" name="{{$pergunta->id}}" value="{{$pergunta->id}}" idresposta="{{strtolower($resposta->descricao)}}" tipo="checkbox">
                                                                    <span class="control-label input-sm" style="font-weight:400;text-align:left;font-size:12px;padding-left:16px;">{{$resposta->descricao}}</span>
                                                                </div>
                                                            </div>
                                                            <script type="text/javascript">
                                                                if(typeof array_check == "undefined"){
                                                                    array_check = [];
                                                                }
                                                                if($.inArray("{{$pergunta->id}}",array_check) == -1)
                                                                    array_check.push("{{$pergunta->id}}");
                                                            </script>
                                                        @elseif($resposta->tipopergunta == "1")
                                                            <div class="form-group crud_space">
                                                                <div class="col-sm-2">
                                                                    <input id="{{$pergunta->id}}" type="text" class="form-control input-sm number" name="{{$resposta->id}}" idresposta="{{$resposta->id}}" tipo="texto" required/><br/>
                                                                </div>
                                                                <span class="control-label input-sm" style="font-weight:400;text-align:left;font-size:12px">{{$resposta->descricao}}</span>
                                                            </div>
                                                        @elseif($resposta->tipopergunta == "2")
                                                            <div class="form-group crud_space">
                                                                <div class="col-sm-3">
                                                                    <input id="{{$pergunta->id}}" type="text" class="form-control input-sm" name="{{$resposta->id}}" idresposta="{{$resposta->id}}" tipo="texto" required/><br/>
                                                                </div>
                                                                <span class="control-label input-sm" style="font-weight:400;text-align:left;font-size:12px">{{$resposta->descricao}}</span>
                                                            </div>
                                                        @elseif($resposta->tipopergunta == "3")
                                                            <div class="form-group crud_space">
                                                                <div class="col-sm-2">
                                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                                        <input id="{{$pergunta->id}}" type="text" class="generalDatePicker form-control input-sm" name="{{$resposta->id}}" tipo="data" required/><br/>
                                                                        <span class="input-group-addon">
                                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                                <span class="control-label input-sm" style="font-weight:400;text-align:left;font-size:12px">{{$resposta->descricao}}</span>
                                                            </div>
                                                        @elseif($resposta->tipopergunta == "4")
                                                            <div class="form-group crud_space">
                                                                <div class="col-sm-10 checkbox">
                                                                    <input id="{{$resposta->id}}" type="radio" name="{{strtolower($pergunta->descricao)}}_{{$pergunta->id}}" value="{{$pergunta->id}}" idresposta="{{strtolower($resposta->descricao)}}" tipo="radio" required>
                                                                    <span class="control-label input-sm" style="font-weight:400;text-align:left;font-size:12px">{{$resposta->descricao}}</span>
                                                                </div>
                                                            </div>
                                                        @endif
                                                        @if ($loop->last)
                                                            </div>
                                                        @endif                                                        
                                                    @endforeach
                                                    </div>
                                            @endforeach
                                        @endforeach
                                        @endif
                                        <div class="form-group crud-space">
                                            {{ Form::label('observacoes', 'Observações:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                            <div class="col-sm-10">
                                                {{ Form::textarea('observacoes', @$checklist->observacoes, ['id'=>'observacoes','size' => '30x3','classe'=>'form-control input-sm','tipo'=>'text','style'=>'width:400px']) }}
                                                {{ Form::hidden('respostasradio', null, ['id'=>'respostasradio']) }}
                                                {{ Form::hidden('respostastext', null, ['id'=>'respostastext']) }}
                                                {{ Form::hidden('respostascheckbox', null, ['id'=>'respostascheckbox']) }}
                                                {{ Form::hidden('respostas', @$pesquisaresposta, ['id'=>'respostas']) }}
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
                            <a id="goback" type="button" class="btn btn-nw-geral" >Voltar</a>
                        </div>
                    </div>
                </div>
            </ul>
            {{Form::close()}}
        </div>
    </div>
</div>
<script type="text/javascript" src="{{URL::to('js/pesquisachecklist.js')}}"></script>
<script type="text/javascript">
$(document).ready(function(){
    setTimeout(function () {
    @if(isset($show))
        showRespostas();
        desativarInputs();
    @endif
    });
});
</script>
@endsection