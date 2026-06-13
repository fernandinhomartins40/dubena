@extends('layouts.mainmenu')

@section('content')


<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            {{ Form::open(['id'=>'fmCadastro','url' => 'vendavalegas.imprimirvalegas', 'class' => 'form-horizontal', 'files' => true, 'target'=>'_blank']) }}
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Imprimir Vale Gás</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Imprimir</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {{ Form::label('nomecliente', 'Cliente:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                <div class="col-md-4">
                                                    {{ Form::select('cliente_id', $clientes, null, ['class' => 'form-control selectChosen']) }}
                                                </div>
                                                {{ Form::label('apartir', 'A partir:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;']) }}
                                                <div class="col-sm-1 ">
                                                    {{ Form::text('apartir', null,['id'=>'apartir','class'=>'input-sm form-control number']) }}
                                                </div>
                                                <div id="boxprevenda">
                                                    {{ Form::label('checkprevenda', 'Pré-Venda', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-md-1 checkbox">
                                                        {{ Form::checkbox('checkprevenda',1) }}
                                                    </div>
                                                </div>
                                                <div class="col-sm-2 ">
                                                    {!! Form::submit('Gerar Etiquetas', ['id'=>'btnimprimir','class' => 'btn btn-nw-registro']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                <br/>
                                                <div class="col-md-8 col-md-offset-2">
                                                    <!--
                                                    <strong>Atenção: Apenas 90 etiquetas serão geradas! E assim que geradas o status da mesma mudará para impresso.</strong>
                                                    -->
                                                    <strong>Atenção: As etiquetas serão geradas! E assim que geradas o status da mesma mudará para impresso.</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </ul>
            {{ Form::close() }}
        </div>
    </div>
</div>
<script type="text/javascript" src="{{URL::to('js/valegasimpressao.js')}}"></script>
<script type="text/javascript">
url = '{{URL::to("/vendavalegas/confirmacao/:confirma")}}';
@if ($errors->any())
    errorsany = true;
@else
    errorsany = false;
@endif
</script>
@endsection
