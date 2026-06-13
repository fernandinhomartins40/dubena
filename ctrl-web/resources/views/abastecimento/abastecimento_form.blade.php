@extends('layouts.mainmenu') @section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">

            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($abastecimento))
            {{ Form::model($abastecimento, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('veiculoabastecimento.update', $abastecimento->id))) }}
            @else 
            {{ Form::open(['id'=>'fmCadastro','route' => 'veiculoabastecimento.store', 'class' => 'form-horizontal', 'files' => true]) }} 
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Abastecimento</h3>
                    </div>
                    <div id="alertaKm" class="alert alert-informacao" role="alert" hidden="true">
                        <strong>Atenção</strong> O KM atual não pode ser menor ou igual ao anterior.
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Informções Gerais</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('data', 'Data:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="generalDateTimePicker col-md-2">
                                                    <div class="input-group generalDateTimePicker">
                                                        {!! Form::text('data',null,['class'=>'form-control generalDateTimePicker input-sm']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('veiculo', 'Veículo: ', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::select('veiculo_id', $veiculo, null, ['id'=>'placa', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;'])!!}
                                                    {!! Form::hidden('placa_hd',null,['id'=>'placa_hd','class'=>'form-control input-sm']) !!}
                                                </div>
                                                {!! Form::label('kmanterior', 'KM Anterior:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::text('kmanterior',null,['id'=>'kmanterior','class'=>'form-control input-sm number ','disabled' => 'true']) !!}
                                                    {!! Form::hidden('kmanterior_hd',null,['id'=>'kmanterior_hd','class'=>'form-control input-sm number']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('colaborador_id', 'Contudor:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-6">
                                                    {!! Form::select('colaborador_id', $colaborador, $veiculo, ['id'=>'colaborador_id', 'class' => 'form-control selectChosen'])!!}
                                                    {!! Form::hidden('colaborador_id_hd',null,['class'=>'form-control input-sm','id'=>'colaborador_id_hd']) !!}
                                                </div>
                                                {!! Form::label('kmatual', 'KM Atual:', ['class'=>'col-md-2 control-label input-sm ']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::text('kmatual',null,['id'=>'kmatual','class'=>' form-control input-sm number']) !!}
                                                </div>                                                
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('totallitros', 'Total Litros:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::text('totallitros',null,['id'=>'totallitros','class'=>'form-control input-sm quantidadeLitros']) !!}
                                                </div>
                                                {!! Form::label('kmrodado', 'KM Rodado:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::text('kmrodado',null,['id'=>'kmrodado','class'=>' form-control input-sm ', 'disabled' => 'true']) !!}
                                                    {!! Form::hidden('kmrodado_hd',null,['id'=>'kmrodado_hd','class'=>' form-control input-sm ']) !!}
                                                </div>
                                                {!! Form::label('mediaconsumo', 'Média de Consumo:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::text('mediaconsumo',null,['id' => 'mediaconsumo', 'class'=>' form-control input-sm', 'disabled' => 'true']) !!}
                                                    {!! Form::hidden('mediaconsumo_hd',null,['id' => 'mediaconsumo_hd', 'class'=>' form-control input-sm']) !!}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="box-footer">
                            <div class="col-md-4">
                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                <a type="button" href="{{route('veiculoabastecimento.index')}}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </ul>
            {{Form::close()}}
        </div>
    </div>
</div>
<script type="text/javascript" src="{{URL::to('js/veiculoManutencao.js')}}"></script>

<script>
buscarUrl = '{{URL::to("veiculo/buscarveiculosajax/:id")}}';
@if($errors->any())
    errorsany = true;
    $("#kmatual").focus();
    calcularMedia();
@else
    errorsany = false;
@endif

setTimeout(function () {
    @if (isset($show))
        desativarInputs();
        $("#mediaconsumo").val({{ $abastecimento->mediaconsumo }});
        $("#kmanterior").val({{ $abastecimento->kmanterior }});
        $("#kmatual").val({{ $abastecimento->kmatual }});
        $("#totallitros").val({{ conversaoLitros($abastecimento->totallitros) }});
        $("#kmrodado").val({{ $abastecimento->kmrodado }});
    @else            
        $("#placa").change(function () {
            if(!isEmpty($(this).val())){
                var placa = $("#placa").val();
                $("#placa_hd").val(placa);
                carregarDadosCondVei(buscarUrl);
            }else{
                $("#colaborador_id").val('').trigger('chosen:updated');
                $("#kmanterior").val('');
                $("#totallitros").val('');
                $("#kmrodado").val('');
                $("#placa_hd").val('');
            }
        });
    @endif
}, $(document).ready());
@if(!isset($show))
$(document).ready(function(){
    var placa = $("#placa").val();
    $("#placa_hd").val(placa);
    $("#placa").val($("#placa_hd").val()).trigger('chosen:updated');
    carregarDadosCondVei(buscarUrl);
});
@endif
</script>
@endsection