@extends('monitora.layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($veiculo))
            {{ Form::model($veiculo, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('veiculo.update', $veiculo->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'veiculo.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
            <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Veículo</h3>
                    </div>
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastro" class="col-md-12">
                                    <div class="box-body">
                                        <div class="form-group crud_space">
                                            {!! Form::label('veiculotipo_id', 'Tipo Veículo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::select('veiculotipo_id', $veiculotipos, null, ['id'=>'veiculotipo_id', 'class' => 'selectChosen form-control', 'style'=>'padding:0px;max-height:24px;']) !!}
                                            </div>
                                            {!! Form::label('placa', 'Placa:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                            <div class="col-sm-1">
                                                {!! Form::text('placa',null,['id'=>'placa', 'class'=>'form-control input-sm placa']) !!}
                                            </div>
                                            {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                            <div class="col-sm-1 checkbox">
                                                {{ Form::checkbox('ativo') }}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-6">
                                                {!! Form::text('descricao',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('motorista', 'Motorista:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-6">
                                                {!! Form::text('motorista',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('deviceid', 'Id.Rastreador:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('deviceid',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                            {!! Form::label('km_atual', 'Km Atual:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('km_atual',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('veiculoerp_id', 'Id.ERP:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('veiculoerp_id',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-pane -->
                    </div><!-- /.tab-content -->
                            <div class="box-footer">
                                <div class="col-md-4">
                                    {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                    <a type="button" href="{{url('veiculo')}}" class="btn btn-nw-geral">Voltar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {!! Form::close() !!}
            </ul><!-- /.col -->
        </div>
    </div>

<script type="text/javascript">
    var confirm;
    var t;
    var root = '{{url("/")}}';
    $(".delete").on("submit", function () {
        return confirm("Quer remover o registro atual?");
    });
    jQuery(document).ready(function ($) {
        var errorElement = document.querySelector('#errorMsg');

        function errorMsg(msg, error) {
            errorElement.innerHTML += '<p>' + msg + '</p>';
            if (typeof error !== 'undefined') {
                console.error(error);
            }
        }
        $(document).ready(function() {
            @if ($errors -> any())
            @endif
        });
        @if (isset($show))
            desativarInputs();
            var ids = [".btn-danger", ".btn-nw-registro", '#btnAddDoc'];
            desativarInputsEspecificos(ids);
        @endif
    });
</script>
@endsection
