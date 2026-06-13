@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">      

            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($veiculotrocaoleo))
            {{ Form::model($veiculotrocaoleo, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('veiculotrocaoleo.update', $veiculotrocaoleo->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'veiculotrocaoleo.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Troca de Óleo</h3>
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
                                                {!! Form::label('data', 'Data:', ['class'=>'col-sm-2     control-label input-sm','style'=>'text-align:right;']) !!}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {!! Form::datetime('data',null,['class'=>'form-control input-sm generalDatePicker']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>     
                                                {!! Form::label('veiculo', 'Veículo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-6">
                                                    {!! Form::select('veiculo_id',$veiculo, null, ['id'=>'veiculo_id','class' => 'form-control  selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('condutor', 'Condutor:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-6">
                                                    {!! Form::select('colaborador_id', $colaborador, $veiculo, ['id'=>'colaborador_id','class' => 'form-control  selectChosen']) !!}
                                                </div>
                                                {!! Form::label('kmultimatrocaoleo', 'Km ultima troca:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('kmultimatrocaoleo',null,['id'=>'kmultimatrocaoleo','class'=>'form-control input-sm', 'readonly']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('kmtrocaoleo', 'Km no momento da troca:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('kmtrocaoleo',null,['id'=>'kmtrocaoleo', 'class'=>'form-control input-sm number','id' => 'kmtrocaoleo']) !!}
                                                </div>
                                                {!! Form::label('oleorendimento', 'Óleo para x km:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('oleorendimento',null,['class'=>'form-control input-sm number', 'id' => 'oleorendimento']) !!}
                                                </div>
                                                {!! Form::label('oleoproximatroca', 'Km próxima troca:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('oleoproximotroca',null,['id' => 'oleoproximotroca','class'=>'form-control input-sm','readonly']) !!}
                                                </div>
                                            </div>

                                            <div class="form-group crud_space">
                                                <div id="boxalertaoleo">
                                                    {!! Form::label('alertaantesoleo', 'Alerta KM', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                    <div class="col-md-2 checkbox">
                                                        {!! Form::checkbox('alertaantesoleo',1) !!}
                                                    </div>
                                                </div>
                                                {!! Form::label('kmalertaantesoleo', 'Km antes:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                                <div class="col-sm-2">
                                                    {!! Form::text('kmalertaantesoleo',null,['id'=>'kmalertaantesoleo','class'=>'form-control input-sm number']) !!}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div><!-- /.tab-pane -->
                            </div><!-- /.tab-pane -->
                            <div class="box-footer">
                                <div class="col-md-4">
                                    {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                    <a type="button" href="{{url('veiculotrocaoleo')}}" class="btn btn-nw-geral">Voltar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </ul><!-- /.col -->
        </div>
        {!! Form::close() !!}
    </div>
</div>
<!-- DATA TABES SCRIPT -->
<!-- page script -->
<script type="text/javascript" src="{{URL::to('js/veiculoManutencao.js')}}"></script>
<script type="text/javascript">
    setTimeout(function () {        
        @if (isset($show))
            desativarInputs();
            $("#kmultimatrocaoleo").val({{ $veiculotrocaoleo->kmultimatrocaoleo }});
            $("#kmtrocaoleo").val({{ $veiculotrocaoleo->kmtrocaoleo }});
            $("#oleorendimento").val({{ $veiculotrocaoleo->oleorendimento }});
            $("#oleoproximotroca").val({{ $veiculotrocaoleo->oleoproximatroca }});
            @if ($veiculotrocaoleo->alertaantes == 1)
                $("#alertaantesoleo").prop("checked", true);
                $("#kmalertaantesoleo").val({{ $veiculotrocaoleo->kmalertaantes}});
            @endif       
            
        @endif
    }, $(document).ready());
@if($errors->any())
    errorsany = true;
@else
    errorsany = false;
@endif

buscarUrl = '{{URL::to("veiculo/buscarveiculosajax/:id")}}';

@if(!isset($show))
    $(document).ready(function(){
        carregarDadosCondVei(buscarUrl);
    });
@endif
</script>
@include('trocaoleo.troca_partials_js')

@endsection
