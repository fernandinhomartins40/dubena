@extends('layouts.mainmenu') 
@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            {{ Form::open(['id'=>'fmCadastro','route' => 'posvenda.store', 'class' => 'form-horizontal', 'files' => true]) }} 
            <ul>
                <div class="panel panel-default">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Pesquisa de Pós-Venda</h3>
                        </div>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Pós-Venda</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                <div class="col-sm-6 col-sm-offset-1" style="text-align:center;padding-left:0px;">
                                                    <p style="font-size:16px;font-weight:600;text-align:left;">Pedido: {{$cliente->pedido_id}} - Telefone(s): {{$cliente->telefone}}</p>
                                                    <p style="font-size:14px;font-weight:400;text-align:left;"><span style="font-weight:600">Cliente:</span> {{$cliente->cliente_id}} - {{$cliente->cliente}} - <span style="font-weight:600">Setor:</span> {{$cliente->setor}} - {{$cliente->colaborador}}</p>
                                                    <p style="font-size:14px;font-weight:400;text-align:left;"><span style="font-weight:600">Produto(s):</span> {{$cliente->produto}} - <span style="font-weight:600">Valor Venda:</span> {{requestNumeroDecimalOracle($cliente->valorvenda)}}</p>
                                                </div>
                                            </div>
                                            {{ Form::hidden('idposvenda', $posvenda->id, ['id'=>'idposvenda']) }}
                                            {{ Form::hidden('idpedido', $cliente->pedido_id, ['id'=>'idposvenda']) }}
                                            <div class="form-group crud_space">
                                                @if(isset($posvenda))
                                                    @foreach($posvenda->perguntas as $pergunta)
                                                        <div class="form-group crud_space">
                                                            <label for="{{$pergunta->id}}" class="col-sm-4 col-sm-push-1 control-label input-sm" style="text-align:left;">{{$pergunta->descricao}}</label>
                                                        </div>
                                                        <div class="form-group crud_space">
                                                        @foreach($pergunta->respostas as $resposta)
                                                            @if ($loop->first)
                                                                <div class="col-md-10 col-md-push-1">
                                                            @endif
                                                            <div class="form-group crud_space">
                                                                <div class="col-sm-9 checkbox">
                                                                    <input id="{{$resposta->id}}" type="radio" name="{{$resposta->posvendapergunta_id}}" value="{{$resposta->id}}" tipo="radio" required>
                                                                    <span class="control-label input-sm" style="font-weight:400;text-align:left;font-size:12px">{{$resposta->descricao}}</span>
                                                                </div> 
                                                            </div> 
                                                            @if ($loop->last)
                                                                </div>
                                                            @endif                                                            
                                                        @endforeach
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                            <br>
                                            <br>
                                            <div class="form-group crud-space">
                                                {{ Form::label('observacoes', 'Observações:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-10">
                                                    {{ Form::textarea('observacoes', null, ['id'=>'observacoes','size' => '30x3','classe'=>'form-control input-sm','tipo'=>'text','style'=>'width:400px']) }}
                                                    {{ Form::hidden('respostas', null, ['id'=>'respostas']) }}
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
                </div>
            </ul>
            {{Form::close()}}
        </div>
    </div>
</div>
<script type="text/javascript" src="{{URL::to('js/posvendapesquisa.js')}}"></script>
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