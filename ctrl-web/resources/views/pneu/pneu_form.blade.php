@extends('layouts.mainmenu') @section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">

            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($veiculopneu))
            {{ Form::model($veiculopneu, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('veiculopneu.update', $veiculopneu->id))) }}
            @else 
            {{ Form::open(['id'=>'fmCadastro','route' => 'veiculopneu.store', 'class' => 'form-horizontal', 'files' => true]) }} 
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Troca de Pneus</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Informções Gerais</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                {!! Form::label('veiculo', 'Veículo: ', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::select('veiculo_id', $veiculo, null, ['id'=>'placa', 'class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;'])!!}
                                                </div>
                                                {!! Form::label('data', 'Data:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="generalDatePicker col-md-2">
                                                    <div class="input-group generalDatePicker">
                                                        {!! Form::text('data',null,['class'=>'form-control generalDatePicker input-sm']) !!}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {!! Form::label('kmatualpneu', 'Km no momento da troca:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::text('kmatualpneu',null,['id' => 'kmatualpneu','class'=>'form-control input-sm number']) !!}
                                                    {!! Form::hidden('kmatualpneu_hd',null,['id' => 'kmatualpneu_hd']) !!}
                                                </div>
                                            </div>

                                            <div class="form-group crud_space">
                                                {!! Form::label('valor', 'Valor:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::text('valor',null,['id' => 'valor', 'class'=>'dinheiro form-control input-sm']) !!}
                                                </div>
                                                {!! Form::label('medidapneus', 'Medida Pneus:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::text('medidapneus',null,['id' => 'medidapneus','class'=>'form-control input-sm']) !!}
                                                </div>
                                                {!! Form::label('vidautilkm', 'Vida útil km:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::text('vidautilkm',null,['id' => 'vidautilkm','class'=>'form-control input-sm number']) !!}
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {!! Form::label('quantidadepneu', 'Quantidade:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::text('quantidadepneu',null,['id' => 'quantidadepneu','class'=>'form-control input-sm number']) !!}
                                                </div>
                                                <div id="boxcheckalertapneu">
                                                    {!! Form::label('alertaantespneu', 'Alerta km', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                    <div class="col-md-2 checkbox">
                                                        {!! Form::checkbox('alertaantespneu',1) !!}
                                                    </div>
                                                </div>
                                                {!! Form::label('kmalertaantespneu', 'Alerta km antes:', ['class'=>'col-md-2 control-label input-sm']) !!}
                                                <div class="col-md-2">
                                                    {!! Form::text('kmalertaantespneu',null,['id' => 'kmalertaantespneu','class'=>'form-control input-sm number']) !!}
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
                                <a type="button" href="{{url('veiculopneu')}}" class="btn btn-nw-geral">Voltar</a>
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
@include('pneu.pneu_partials_js')
@endsection